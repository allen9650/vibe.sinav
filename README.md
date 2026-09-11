# vibe.Sınav — Assessment System

> **Enterprise-Grade, Secure, Offline/LAN-Ready Assessment, Examination & Live Proctoring Platform**  
> Developed by **Ahsan Raza** &bull; Powered by **vibe.Sınav**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%7C%20MariaDB-orange.svg)](https://www.mysql.com/)
[![Network](https://img.shields.io/badge/Deployment-LAN%20%7C%20Offline%20Ready-green.svg)](#lan--multi-pc-lab-deployment)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](#)

---

## Table of Contents
1. [Overview & Features](#overview--features)
2. [Dual Examination Engines](#dual-examination-engines)
3. [Architecture & Technology Stack](#architecture--technology-stack)
4. [System Requirements](#system-requirements)
5. [Quick Installation & Setup](#quick-installation--setup)
6. [Environment Configuration (.env)](#environment-configuration-env)
7. [One-Click Batch Launchers](#one-click-batch-launchers)
8. [LAN & Multi-PC Lab Deployment](#lan--multi-pc-lab-deployment)
9. [Anti-Cheating & Live Telemetry Security](#anti-cheating--live-telemetry-security)
10. [Certificates, Reports & Analytics](#certificates-reports--analytics)
11. [Institutional Branding & Customization](#institutional-branding--customization)
12. [Disaster Recovery & Backup](#disaster-recovery--backup)
13. [Troubleshooting & FAQs](#troubleshooting--faqs)

---

## 1. Overview & Features

**vibe.Sınav Assessment System** is a standalone, browser-based, high-performance examination and assessment platform engineered specifically for schools, colleges, universities, professional testing centers, and computer laboratories.

Designed from the ground up for mission-critical institutional testing, the system operates seamlessly in **air-gapped or offline local area networks (LAN)** with zero reliance on external CDNs or cloud dependencies.

### Core Capabilities:
- **Comprehensive Assessment Builder**: Create rich cognitive exams, quizzes, and aptitude tests featuring Single Choice (MCQs), Multiple Choice (Multi-Select), True/False, Fill in the Blanks, and Short Answer questions with automated evaluation.
- **Typing & Speed Testing Engine**: Dynamic passage testing with character-level Levenshtein DP sequence alignment, Gross/Net WPM, accuracy calculation, and error classification.
- **Robust Candidate Experience**: Server-synchronized countdown timers, 5-second automatic state persistence, mid-test refresh recovery, and accidental tab closure protection.
- **Live Proctoring Telemetry**: Real-time invigilator dashboard monitoring active candidate timers, question progress, tab switching, window blurs, fullscreen exits, and automated disqualification thresholds.
- **Multi-Role RBAC**: Granular role-based security (`Super Admin`, `Admin`, `Teacher / Invigilator`, `Candidate`), bcrypt password hashing, CSRF token validation, brute-force lockout, and immutable audit logs.
- **Workstation & Device Management**: Computer lab workstation allocation, IP/device restrictions, roll number assignment, attendance tracking, and printable admission slips.
- **Customizable Certificate Generator**: Multi-template landscape A4 certificates with dynamic watermark, custom institute logo scaling, signature blocks, unique serial numbering, and QR verification codes.
- **Institutional Branding**: Live sliding logo presentation on the login portal, customizable institute name, and matching dynamic favicons across all public and admin pages.
- **Analytical Reporting**: 14+ analytical reports, Excel-compatible CSV exports sanitized against CSV formula injection (`=`, `+`, `-`, `@`), and 80mm thermal receipt printing.

---

## 2. Examination Engines

vibe.Sınav provides specialized testing engines within a single unified platform:

### A. Modern Quiz & Cognitive Assessment Engine
- **Visual Question Builder**: Drag-and-order question structuring with rich text, categories, difficulty tags, points, and explanation notes.
- **5 Supported Question Types**:
  1. `Single Choice (MCQ)`: Standard multiple choice with single radio option.
  2. `Multiple Choice`: Multi-select checkboxes with partial or all-or-nothing scoring.
  3. `True / False`: Rapid conceptual validation buttons.
  4. `Fill in the Blanks`: Keyword and phrase matching with case-sensitivity toggles.
  5. `Short Answer`: Open-ended answers evaluated against model answer key patterns.
- **Real-Time Telemetry & Progress**: Candidates navigate questions with answered/unanswered indicators, question flags for review, and instant submission safeguards.

---

## 3. Architecture & Technology Stack

- **Backend**: Clean, procedural-OOP hybrid PHP 8.1+ with zero heavy framework bloat, delivering sub-millisecond response times.
- **Frontend**: Vanilla HTML5, High-Contrast CSS3 Design System, Bootstrap 5 UI Components, FontAwesome Icons, and Vanilla JavaScript.
- **100% Offline / Air-Gapped Ready**: All CSS stylesheets, fonts, and JS bundles are stored locally in `public/assets/` — zero external CDN requests.
- **Database Layer**: MySQL 5.7+ / MariaDB 10.4+ with transactional integrity, foreign key constraints, and indexed queries across 20+ optimized tables.
- **Session & State Management**: Server-authoritative state engine storing question progress and test timers to prevent client-side tampering.

---

## 4. System Requirements

### Host Server Machine
- **Operating System**: Windows 10, Windows 11, Windows Server, or Linux (Ubuntu/Debian/RHEL).
- **Web Server**: Apache 2.4+ (Included with XAMPP) or Nginx.
- **PHP Version**: PHP 8.1 or higher (PHP 8.2+ recommended).
- **PHP Extensions**: `pdo_mysql`, `mbstring`, `openssl`, `session`, `json`, `filter`.
- **Database**: MySQL 5.7+ or MariaDB 10.4+.
- **Network**: Standard 100 Mbps or 1 Gbps Local Area Network (LAN / Ethernet / Wi-Fi Router).

### Candidate Workstations (Client PCs)
- **Browser**: Google Chrome 90+, Microsoft Edge 90+, Mozilla Firefox 88+, Safari 14+.
- **Resolution**: 1280x720 or higher recommended.
- **Hardware**: Standard desktop or laptop with keyboard and mouse.

---

## 5. Quick Installation & Setup

### Step 1: Place Project in Web Root
Clone or copy the project to your web server root (e.g., XAMPP `htdocs`):
```bash
git clone https://github.com/allen9650/vibe.sinav.git C:\xampp\htdocs\ptmtest
```

### Step 2: Configure Environment (.env)
Copy the provided `.env.example` template to `.env`:
```bash
cp .env.example .env
```
Open `.env` in any text editor and configure your database credentials and application settings:
```ini
APP_NAME="vibe.Sınav"
APP_URL=http://localhost/ptmtest
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ptm
DB_USERNAME=root
DB_PASSWORD=
```

### Step 3: Run the Web Installer
1. Open XAMPP and start **Apache** and **MySQL**.
2. Navigate in your browser to:
   ```
   http://localhost/ptmtest/install.php
   ```
3. The installer will automatically:
   - Create the database (if not existing).
   - Execute base schema (`database/schema.sql`).
   - Execute assessment system migrations (`database/migrations/assessment_system.sql`).
   - Seed default roles, system settings, and administrator credentials (`database/seed.sql`).
   - Create `install.lock` to secure the installation.

### Step 4: Default Credentials
| Portal | URL | Default Username | Default Password |
| :--- | :--- | :--- | :--- |
| **Super Admin / Examiner** | `/index.php?page=login` | `admin` | `Admin@123` |
| **Teacher / Invigilator** | `/index.php?page=login` | `teacher` | `Teacher@123` |
| **Quiz Candidate Portal** | `/index.php?page=quiz-login` | *(Candidate Roll No)* | *(Password if set)* |
| **Typing Test Portal** | `/index.php?page=test-portal` | *(Candidate Roll No)* | *(None / Direct)* |

---

## 6. Environment Configuration (.env)

The application uses an environment configuration file (`.env`) for portable deployment across development, local testing, and production servers.

| Variable | Default Value | Description |
| :--- | :--- | :--- |
| `APP_NAME` | `"vibe.Sınav"` | Brand title displayed throughout portals |
| `APP_ENV` | `development` | `development` or `production` |
| `APP_DEBUG` | `true` | Show diagnostic messages on errors |
| `APP_URL` | `http://localhost/ptmtest` | Canonical base URL |
| `APP_TIMEZONE` | `Asia/Karachi` | System timezone for audit and test timestamps |
| `DB_HOST` | `127.0.0.1` | MySQL server hostname or IP address |
| `DB_PORT` | `3306` | MySQL server port |
| `DB_DATABASE` | `ptm` | Database name |
| `DB_USERNAME` | `root` | Database username |
| `DB_PASSWORD` | `""` | Database password |
| `SESSION_LIFETIME` | `30` | Session idle timeout in minutes |
| `LOGIN_MAX_ATTEMPTS` | `5` | Maximum failed logins before account lockout |
| `LOGIN_LOCKOUT_MINUTES`| `15` | Duration of lockout period |

> **Security Note**: Never commit your active `.env` file containing production credentials to public version control. Use `.env.example` as the clean template.

---

## 7. One-Click Batch Launchers

For rapid, frictionless operation without opening command line tools, convenient Windows batch scripts are included in the root directory:

| Script | Target Functionality |
| :--- | :--- |
| `START_SERVER_LAN.bat` | Starts the host server on port 8000, detects server IPv4, and displays URLs for all candidate PCs in the lab. |
| `START_ADMIN.bat` | Starts local server and automatically launches the Admin & Examiner Control Panel in your default browser. |
| `START_TEACHER.bat` | Starts local server and launches the Teacher / Invigilator Portal. |
| `START_QUIZ_STUDENT.bat` | Starts server and launches the Candidate Interactive Quiz Portal (`quiz-login`). |
| `START_STUDENT.bat` | Starts server and launches the Candidate Typing Examination Portal (`test-portal`). |

---

## 8. LAN & Multi-PC Lab Deployment

Deploying vibe.Sınav in a computer laboratory across 10 to 100+ workstations requires no external internet connection:

### 1. Identify Server IPv4 Address
On the host server PC:
1. Double-click `START_SERVER_LAN.bat` (or run `ipconfig` in Command Prompt).
2. Note your host IP address (e.g. `192.168.1.100` or `10.0.0.50`).

### 2. Configure Windows Firewall on Server
Ensure Apache or PHP is permitted to accept inbound connections:
1. Open **Windows Defender Firewall** &rarr; **Allow an app or feature through Windows Defender Firewall**.
2. Check **Apache HTTP Server** (or `php.exe`) for both **Private** and **Public** networks.

### 3. Connect Candidate Workstations
On each candidate PC in the lab, open Google Chrome or Microsoft Edge and navigate to:
- **For Quizzes & Assessments**:  
  `http://<SERVER_IP>:8000/index.php?page=quiz-login`  
  *(e.g., `http://192.168.1.100:8000/index.php?page=quiz-login`)*
- **For Typing Speed Tests**:  
  `http://<SERVER_IP>:8000/index.php?page=test-portal`

Candidates log in using their pre-assigned **Roll Number** or **Workstation Terminal**.

---

## 9. Anti-Cheating & Live Telemetry Security

vibe.Sınav incorporates multi-vector proctoring safeguards to maintain rigorous academic integrity:

| Event | Detection Technology | System Response |
| :--- | :--- | :--- |
| **Tab Switching** | HTML5 Visibility API (`visibilitychange`) | Logs `TAB_SWITCH` telemetry event; increments violation counter. |
| **Window Blur** | Window focus loss listener (`blur`) | Logs `WINDOW_BLUR` event with exact client timestamp. |
| **Fullscreen Exit** | Fullscreen API state monitor | Warns student and prompts immediate return to fullscreen. |
| **Copy / Cut / Paste** | DOM clipboard event interception | Blocks clipboard action via `preventDefault()`; logs attempt. |
| **Right-Click Context** | Context menu event interception | Suppresses context menu; prevents inspection or copy shortcuts. |
| **Threshold Disqualification**| Server-evaluated violation limit | Locks attempt immediately upon reaching max permitted violations. |
| **Heartbeat & Telemetry** | Background ping every 5 seconds | Tracks real-time connection status and active question index. |

---

## 10. Certificates, Reports & Analytics

### Automated Certificate Generator
- **Multi-Template Engine**: Professional landscape A4 certificate layouts with gold and navy decorative borders.
- **Dynamic Watermark & Logo**: Dynamic scaling and opacity adjustment for the institution's official crest.
- **Unique Verification Serial**: Generates sequential, tamper-evident certificate numbers (e.g., `CERT-2026-00042`).
- **QR Code Verification**: Instant verification link rendered directly on printed certificates.

### 14+ Analytical Reports
1. Full Assessment & Competition Results
2. Individual Candidate Performance Transcripts
3. Top Performers & Honor Roll
4. Daily Attendance & Workstation Check-ins
5. Absentee Register
6. Course-wise Breakdown
7. Shift / Batch Analysis
8. Branch / Campus Distribution
9. Score & Grade Distributions
10. Typing WPM & Accuracy Distributions
11. Question Error & Distractor Analysis
12. Qualification / Pass-Fail Audit
13. Security Violation & Disqualification Logs
14. Final Leaderboards with Medals (🥇, 🥈, 🥉)

### Export Capabilities
- **Excel-Compatible CSV**: High-speed data streaming with UTF-8 BOM encoding and formula injection protection.
- **Printable Candidate Admission Slips**: Formatted with student photo, barcode/roll number, and exam details.
- **80mm Thermal Receipt Slips**: Fast receipt printer output for on-the-spot candidate score slips.

---

## 11. Institutional Branding & Customization

vibe.Sınav is completely customizable to match your school, college, or university identity:
- **Dynamic Logo Sliding on Login**: Upload your institute logo in **Settings**; the login portal features an elegant CSS sliding animation showcasing both the institutional crest and "Powered by vibe.Sınav".
- **Dynamic Institute Name**: Replace generic titles with your official school or university name across dashboards, candidate admission slips, and reports.
- **Dynamic Favicons**: Automatically matches the browser tab favicon to your institution's uploaded logo.
- **Customizable Assessment Titles**: Configure custom exam titles, test guidelines, passing percentages, and negative marking rules per assessment.

---

## 12. Disaster Recovery & Backup

- **One-Click SQL Backup**: Navigate to **System & Maintenance &rarr; Database Backup** in the admin panel to download a full, consistent SQL snapshot.
- **Safe Restoration**: Backups can be imported directly via phpMyAdmin or the MySQL command line:
  ```bash
  mysql -u root -p ptm < backup_file.sql
  ```
- **Automated Directory Preservation**: Backups are securely archived in `storage/backups/`.

---

## 13. Troubleshooting & FAQs

### Q1: Candidate PCs cannot connect to Host Server
- Ensure both Server and Client computers are on the same local network / router subnet.
- Run `ipconfig` on the server to verify its current IPv4 address.
- Verify that port 80 (Apache) or 8000 (PHP server) is allowed in Windows Defender Firewall.

### Q2: Installer indicates "Already Installed"
- `install.php` is protected by `install.lock` to prevent accidental data overwrites.
- To re-run the initial installation, remove `install.lock` from the project root.

### Q3: Candidate refreshed browser or lost connection during an active test
- vibe.Sınav auto-saves candidate answers every 5 seconds.
- Upon reopening the test URL and logging in with their roll number, the candidate's active session, answers, and remaining time are seamlessly restored.

---

## Credits & License

- **Platform Architect**: **Ahsan Raza**
- **Brand & Engine**: **vibe.Sınav**
- **Copyright**: &copy; 2026 Ahsan Raza. All rights reserved.
