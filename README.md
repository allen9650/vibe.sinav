# vibe.sinav

> **Enterprise-Grade, Secure, LAN-Optimized Assessment & Examination Platform**  
> Developed by **Ahsan Raza**

---

## Table of Contents
1. [Overview & Features](#overview--features)
2. [System Requirements](#system-requirements)
3. [Architecture & Technology Stack](#architecture--technology-stack)
4. [Installation & Setup](#installation--setup)
5. [LAN & Multi-PC Deployment](#lan--multi-pc-deployment)
6. [Daily Operations Workflow](#daily-operations-workflow)
7. [Anti-Cheating & Telemetry Security](#anti-cheating--telemetry-security)
8. [Calculation & Ranking Engine](#calculation--ranking-engine)
9. [Reports, Export & Certificate Generation](#reports-export--certificate-generation)
10. [Database Backup & Disaster Recovery](#database-backup--disaster-recovery)
11. [Troubleshooting & FAQs](#troubleshooting--faqs)

---

## 1. Overview & Features

The **Marr Typing Competition System** is a standalone, browser-based, high-performance examination and assessment engine engineered specifically for large-scale institutional typing competitions and computer laboratory speed testing.

### Key Capabilities:
- **Core RBAC & Security**: Role-based access control (`Super Admin`, `Admin`, `Invigilator`, `Candidate`), CSRF tokens on state mutations, bcrypt password hashing, brute-force lockout, and full database audit logging.
- **Candidate & Attendance Management**: Bulk CSV import, biometric/QR-ready manual attendance, roll number assignment, and printable candidate admission cards.
- **Dynamic Typing Paragraph Library**: Multi-difficulty paragraph management, word/character counting, assignment to competitions, and random selection pools.
- **Candidate Portal & Robust Engine**: Server-backed countdown timer, background auto-save (every 5 seconds), mid-test refresh recovery, accidental tab closure recovery, and clean submission handling.
- **High-Accuracy Levenshtein DP Alignment**: Dynamic programming character and word sequence alignment that isolates substitutions, deletions, and insertions without cascading false errors.
- **Anti-Cheating Telemetry**: Server-side detection and logging of Tab Switches (`Visibility API`), Window Blurs, Fullscreen Exits, Clipboard actions (Copy/Paste/Cut), Context Menu attempts, and automatic threshold disqualification.
- **Real-Time Proctoring & Leaderboards**: Live monitor auto-polling every 3s, real-time typing progress, deterministic ranking hierarchy (Score > Accuracy > Net WPM > Errors > Duration), and frozen rank locking.
- **Comprehensive Reports & Analytics**: 14 distinct analytical reports, multi-filter query engine, and formula-injection-sanitized Excel-compatible CSV streaming with UTF-8 BOM.
- **Certificate System**: Unique sequential numbering (`MITC-CERT-YYYY-XXXXX`), Achievement and Participation templates, snapshot immutability, and landscape A4 print styling.
- **Disaster Recovery**: One-click pure SQL database backup and safe restoration utilities.

---

## 2. System Requirements

### Server Machine (Host PC)
- **Operating System**: Windows 10, Windows 11, or Windows Server.
- **Web Server**: Apache 2.4+ (Included with XAMPP).
- **PHP Version**: PHP 8.0 or higher (PHP 8.2+ recommended).
- **Database**: MySQL 5.7+ or MariaDB 10.4+.
- **Required PHP Extensions**:
  - `pdo_mysql` (Database PDO driver)
  - `mbstring` (Multibyte UTF-8 Unicode string support)
  - `session` (Session management)
  - `json` (Payload encoding/decoding)
  - `openssl` (Cryptographic token generation)
  - `filter` (Data sanitization)
- **Local Network**: 100 Mbps or 1 Gbps Local Area Network (LAN / Ethernet / Wi-Fi Router).

### Candidate Examination Client PCs
- **Browser**: Google Chrome 90+, Microsoft Edge 90+, Mozilla Firefox 88+.
- **Resolution**: 1280x720 or higher recommended.
- **Hardware**: Standard desktop/laptop with physical keyboard.

---

## 3. Architecture & Technology Stack

- **Backend**: Clean, procedural-OOP Hybrid PHP 8 with Zero Heavy Framework Overhead.
- **Frontend**: Vanilla HTML5, High-Contrast CSS3 Design System, Bootstrap 5 UI Components, FontAwesome Icons, Vanilla JavaScript.
- **Zero External CDN Dependencies**: All CSS, fonts, and JS bundles are stored locally within `public/assets/` to ensure 100% functionality on air-gapped/offline LAN networks.
- **Database**: Relational MySQL with strict foreign keys, transactional queries, and index optimization across 18 tables.

---

## 4. Installation & Setup

### Step 1: Copy Project to XAMPP
Place the project folder into your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\ptmtest\
```

### Step 2: Start Apache and MySQL
1. Open **XAMPP Control Panel**.
2. Start the **Apache** and **MySQL** services.

### Step 3: Run the One-Time Web Installer
1. Open your browser and navigate to:
   ```
   http://localhost/ptmtest/install.php
   ```
2. The installer will automatically:
   - Create database `PTM` (if not already existing).
   - Execute all table schemas (`database/schema.sql`).
   - Seed default roles, permissions, settings, and administrator account (`database/seed.sql`).
   - Create `install.lock` and `storage/installed.lock` to prevent accidental reinstallations.

### Step 4: Initial Administrator Login
1. Navigate to:
   ```
   http://localhost/ptmtest/
   ```
2. Enter default credentials:
   - **Username**: `admin`
   - **Password**: `admin123`
3. You will be prompted to set a new, secure password immediately upon first login.

---

## 5. LAN & Multi-PC Deployment

To run typing competitions across multiple computers in your laboratory:

### 1. Obtain Server IPv4 Address
1. On the Server PC, open Command Prompt (`cmd`).
2. Run:
   ```cmd
   ipconfig
   ```
3. Locate your **IPv4 Address** (e.g., `192.168.1.100` or `192.168.10.50`).

### 2. Configure Windows Firewall on Server PC
Ensure Apache is allowed through Windows Defender Firewall:
1. Open **Windows Defender Firewall** &rarr; **Allow an app through firewall**.
2. Ensure **Apache HTTP Server** (`httpd.exe`) is checked for both **Private** and **Public** networks.

### 3. Connect Candidate PCs
On each candidate PC in the lab, open Google Chrome or Microsoft Edge and navigate to:
```
http://<SERVER_IP>/ptmtest/
```
*(Example: `http://192.168.1.100/ptmtest/`)*

Candidates click **Candidate Test Portal** and log in using their allocated **Roll Number**.

---

## 6. Daily Operations Workflow

### 1. Before Competition
1. **Create Competition**: Admin panel &rarr; *Competitions* &rarr; *Create Competition* (Set date, venue, title).
2. **Assign Paragraph**: *Typing Paragraphs* &rarr; create or select passages &rarr; assign to competition.
3. **Configure Settings**: *Competitions* &rarr; *Settings* (Duration, Passing WPM, Passing Accuracy, Max Violations, Fullscreen, Copy/Paste block).
4. **Register Candidates**: *Candidates* &rarr; *Add Candidate* or *Bulk Import (CSV)*.
5. **Print Candidate Slips**: *Candidates* &rarr; *Print Candidate List*.

### 2. Competition Day
1. **Mark Attendance**: *Attendance* &rarr; mark present candidates.
2. **Open Live Monitor**: *Live Test Monitor* &rarr; monitor candidate timers, typing progress, and live security telemetry.
3. **Candidates Test**: Candidates log in, review instructions, start countdown timer, type text, and submit.

### 3. Post Competition
1. **Review Standings**: *Leaderboard* &rarr; view real-time ranked candidates with medals (🥇, 🥈, 🥉).
2. **Finalize Competition**: *Leaderboard* &rarr; *Finalize Competition* (Verifies all attempts completed and saves authoritative frozen ranks).
3. **Lock Final Results**: Click *Lock Results* to permanently protect scores against tampering.
4. **Generate Certificates**: *Certificates* &rarr; *Bulk Certificate Generator* &rarr; select policy &rarr; Generate & Print A4 certificates.
5. **Create Database Backup**: *System* &rarr; *Database Backup* &rarr; *Create Full Backup Now*.

---

## 7. Anti-Cheating & Telemetry Security

| Security Event | Detection Mechanism | System Action |
| :--- | :--- | :--- |
| **Tab Switch** | HTML5 Visibility API (`visibilitychange`) | Logs `TAB_SWITCH` event & increments violation count. |
| **Window Blur** | Window focus loss event (`blur`) | Logs `WINDOW_BLUR` event. |
| **Fullscreen Exit** | Fullscreen API state change | Logs `FULLSCREEN_EXIT` & warns candidate to return to fullscreen. |
| **Copy / Cut / Paste** | DOM clipboard interception | Blocks action via `preventDefault()`, logs `COPY_ATTEMPT` / `PASTE_ATTEMPT`. |
| **Right Click** | Context menu interception | Blocks context menu via `preventDefault()`, logs `RIGHT_CLICK_ATTEMPT`. |
| **Violation Action** | Threshold evaluation (`max_violations`) | Automatically disqualifies attempt, halts typing, and locks attempt. |

---

## 8. Calculation & Ranking Engine

### Speed & Accuracy Formulas
- **Gross WPM**:
  $$\text{Gross WPM} = \frac{\text{Total Typed Characters} / 5}{\text{Duration in Minutes}}$$
- **Net WPM**:
  $$\text{Net WPM} = \max\left(0, \text{Gross WPM} - \frac{\text{Total Errors}}{\text{Duration in Minutes}}\right)$$
- **Accuracy %**:
  $$\text{Accuracy \%} = \frac{\text{Correct Characters}}{\max(\text{Reference Characters}, \text{Typed Characters})} \times 100$$
- **Final Score**:
  $$\text{Final Score} = \text{Net WPM} \times \left(\frac{\text{Accuracy}}{100}\right)$$

### Deterministic Tie-Breaking Rules
1. Highest Final Score (`score DESC`)
2. Highest Accuracy % (`accuracy DESC`)
3. Highest Net WPM (`net_wpm DESC`)
4. Lowest Total Errors (`error_count ASC`)
5. Shortest Scoring Duration (`time_taken_seconds ASC`)
*Exact ties across all metrics receive shared ranks with corresponding medals.*

---

## 9. Reports, Export & Certificate Generation

- **14 Analytical Reports**: Competition Results, Candidate Performance, Top Performers, Attendance, Absent Candidates, Course-wise, Shift-wise, Branch-wise, WPM Distribution, Accuracy Distribution, Error Analysis, Qualification, Security Violations, Final Ranking.
- **Anti-Formula Injection**: Sanitizes cells starting with `=`, `+`, `-`, `@`, `\t`, `\r` to protect Excel users from DDE exploits.
- **Print Sheets**:
  - A4 Individual Result Sheet with signature lines.
  - Compact 80mm Thermal Receipt Slip.
  - Landscape A4 Certificate with gold/navy dual borders.

---

## 10. Database Backup & Disaster Recovery

### Creating a Backup
1. In Admin Panel, navigate to **System & Maintenance &rarr; Database Backup**.
2. Click **Create Full Backup Now**.
3. Download the generated `.sql` file and store it on external media.

### Restoring a Backup
To restore a snapshot in MySQL / phpMyAdmin:
```bash
mysql -u root -p PTM < ptm_backup_YYYY-MM-DD_HHiiss.sql
```

---

## 11. Troubleshooting & FAQs

### Q1: Candidate PCs cannot connect to Host Server
- Verify both computers are on the same Wi-Fi / LAN network.
- Confirm Host Server IP using `ipconfig`.
- Allow `httpd.exe` (Apache) in Windows Defender Firewall on the Server PC.
- Check that both Server and Client PCs are connected to the same local subnet.

### Q2: Installer says "Already Installed"
- For security, `install.php` is protected by `install.lock` and `storage/installed.lock`.
- To re-install, delete `install.lock` and `storage/installed.lock` (Note: This will overwrite data if re-run).

### Q3: Candidate refreshed or closed browser during active test
- The system automatically restores the active session, typing progress, paragraph, and authoritative remaining countdown time.

---
**Marr** &bull; *Department of Information Technology*
