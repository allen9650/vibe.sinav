<?php
/**
 * Official Landscape A4 Multi-Template Certificate Module
 * Marr Typing Competition System
 *
 * Professional Certificate Engine with:
 * - Dynamic Logo Management & Priority Fallback
 * - Watermark Controls & Source Configuration
 * - Signature Image Uploads (Transparent PNG)
 * - Interactive Live Certificate Editor & Real-Time Customizer
 * - A4 Landscape Geometry (297mm x 210mm)
 */

Middleware::requirePermission('certificates.print');

$certId = (int)($_GET['id'] ?? 0);
$cert = CertificateService::getCertificate($certId);

if (!$cert) {
    Session::flash('error', 'Certificate not found.');
    redirectTo('certificates');
}

$currentUserId = Session::get('user_id');

// Helper function to handle secure image upload
if (!function_exists('handleCertUpload')) {
    function handleCertUpload(string $fileKey, string $prefix, string $settingKey): bool {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $file = $_FILES[$fileKey];
        $allowedMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
        $allowedExts  = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowedMimes) || !in_array($ext, $allowedExts)) {
            Session::flash('error', 'Invalid image format. Allowed: PNG, JPG, WEBP, SVG.');
            return false;
        }

        if ($file['size'] > $maxSize) {
            Session::flash('error', 'File size exceeds 2MB limit.');
            return false;
        }

        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $uploadDir = UPLOADS_PATH . '/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            // Remove old file if it exists and belongs to this setting
            $oldFile = getSetting($settingKey, '');
            if ($oldFile && file_exists($uploadDir . $oldFile) && $oldFile !== getSetting('institute_logo', '')) {
                @unlink($uploadDir . $oldFile);
            }
            updateSetting($settingKey, $filename);
            return true;
        }

        return false;
    }
}

// Handle Editor POST Customization & Upload Actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save_customization') {
        CertificateService::updateCertificateCustomization($certId, $_POST, $currentUserId);

        // Update system-wide certificate branding & watermark if provided
        if (isset($_POST['cert_header_subtitle'])) {
            updateSetting('cert_header_subtitle', trim($_POST['cert_header_subtitle']));
        }
        if (isset($_POST['cert_watermark_enabled'])) {
            updateSetting('cert_watermark_enabled', $_POST['cert_watermark_enabled'] === '1' ? '1' : '0');
        }
        if (isset($_POST['cert_watermark_source'])) {
            updateSetting('cert_watermark_source', in_array($_POST['cert_watermark_source'], ['logo', 'text']) ? $_POST['cert_watermark_source'] : 'logo');
        }
        if (isset($_POST['cert_watermark_opacity'])) {
            $opVal = max(0.01, min(0.30, (float)$_POST['cert_watermark_opacity']));
            updateSetting('cert_watermark_opacity', (string)$opVal);
        }
        if (isset($_POST['cert_logo_size'])) {
            $sizeVal = max(25, min(120, (int)$_POST['cert_logo_size']));
            updateSetting('cert_logo_size', (string)$sizeVal);
        }

        Session::flash('success', 'Certificate customization saved successfully.');
        redirectTo('certificates-view&id=' . $certId . '&template=' . ($_POST['template_key'] ?? 'classic') . '&editor=1');
    } elseif ($postAction === 'upload_cert_logo') {
        if (handleCertUpload('cert_logo_file', 'cert_logo', 'cert_logo')) {
            AuditLog::log('cert_logo_uploaded', 'certificates', 'Certificate logo uploaded.', $currentUserId);
            Session::flash('success', 'Certificate logo uploaded successfully.');
        }
        redirectTo('certificates-view&id=' . $certId . '&editor=1&tab=branding');
    } elseif ($postAction === 'remove_cert_logo') {
        $oldLogo = getSetting('cert_logo', '');
        if ($oldLogo && file_exists(UPLOADS_PATH . '/' . $oldLogo) && $oldLogo !== getSetting('institute_logo', '')) {
            @unlink(UPLOADS_PATH . '/' . $oldLogo);
        }
        updateSetting('cert_logo', '');
        AuditLog::log('cert_logo_removed', 'certificates', 'Certificate logo removed; restored default institute logo.', $currentUserId);
        Session::flash('success', 'Certificate logo removed. Restored default institute logo.');
        redirectTo('certificates-view&id=' . $certId . '&editor=1&tab=branding');
    } elseif ($postAction === 'upload_sig1_image') {
        if (handleCertUpload('sig1_file', 'sig_coord', 'cert_signature_1_image')) {
            AuditLog::log('cert_sig1_uploaded', 'certificates', 'Coordinator signature image uploaded.', $currentUserId);
            Session::flash('success', 'Coordinator signature image uploaded.');
        }
        redirectTo('certificates-view&id=' . $certId . '&editor=1&tab=signatures');
    } elseif ($postAction === 'remove_sig1_image') {
        $oldSig = getSetting('cert_signature_1_image', '');
        if ($oldSig && file_exists(UPLOADS_PATH . '/' . $oldSig)) {
            @unlink(UPLOADS_PATH . '/' . $oldSig);
        }
        updateSetting('cert_signature_1_image', '');
        Session::flash('success', 'Coordinator signature image removed.');
        redirectTo('certificates-view&id=' . $certId . '&editor=1&tab=signatures');
    } elseif ($postAction === 'upload_sig2_image') {
        if (handleCertUpload('sig2_file', 'sig_dir', 'cert_signature_2_image')) {
            AuditLog::log('cert_sig2_uploaded', 'certificates', 'Director signature image uploaded.', $currentUserId);
            Session::flash('success', 'Director signature image uploaded.');
        }
        redirectTo('certificates-view&id=' . $certId . '&editor=1&tab=signatures');
    } elseif ($postAction === 'remove_sig2_image') {
        $oldSig = getSetting('cert_signature_2_image', '');
        if ($oldSig && file_exists(UPLOADS_PATH . '/' . $oldSig)) {
            @unlink(UPLOADS_PATH . '/' . $oldSig);
        }
        updateSetting('cert_signature_2_image', '');
        Session::flash('success', 'Director signature image removed.');
        redirectTo('certificates-view&id=' . $certId . '&editor=1&tab=signatures');
    } elseif ($postAction === 'reset_customization') {
        CertificateService::resetCertificateCustomization($certId, $currentUserId);
        Session::flash('success', 'Certificate reset to default test snapshots.');
        redirectTo('certificates-view&id=' . $certId . '&editor=1');
    }
}

$availableTemplates = CertificateService::getAvailableTemplates();

// Active template resolution
$savedTemplate = !empty($cert['template_key']) ? $cert['template_key'] : 'classic';
$activeTemplate = trim($_GET['template'] ?? $savedTemplate);
if (!isset($availableTemplates[$activeTemplate])) {
    $activeTemplate = 'classic';
}

// Update default template without creating duplicates
if (isset($_GET['save_template']) && $_GET['save_template'] === '1') {
    CSRF::validateOrFail();
    CertificateService::updateTemplate($certId, $activeTemplate);
    Session::flash('success', "Certificate design updated to {$availableTemplates[$activeTemplate]['name']}.");
    redirectTo('certificates-view&id=' . $certId . '&template=' . $activeTemplate);
}

// Institute & System Configuration
$instituteName = getSetting('institute_name', "vibe.Sınav");
$systemName    = getSetting('system_name', "vibe.Sınav");
$headerSub     = getSetting('cert_header_subtitle', 'Department of Information Technology & Typing Examination');

// Logo Priority Resolution:
// 1. Certificate-specific logo
// 2. Institute/System logo
// 3. Graceful vector text fallback
$certLogoSetting = getSetting('cert_logo', '');
$instLogoSetting = getSetting('institute_logo', '');
$effectiveLogoUrl = '';
$logoSourceType   = 'none';

if ($certLogoSetting && file_exists(UPLOADS_PATH . '/' . $certLogoSetting)) {
    $effectiveLogoUrl = upload($certLogoSetting);
    $logoSourceType = 'cert';
} elseif ($instLogoSetting && file_exists(UPLOADS_PATH . '/' . $instLogoSetting)) {
    $effectiveLogoUrl = upload($instLogoSetting);
    $logoSourceType = 'institute';
}

// Logo Size Configuration (25px to 120px)
$certLogoSize = max(25, min(120, (int)getSetting('cert_logo_size', '52')));

// Watermark Configuration
$watermarkEnabled = (getSetting('cert_watermark_enabled', '1') === '1');
$watermarkSource  = getSetting('cert_watermark_source', 'logo');
$watermarkOpacity = (float)getSetting('cert_watermark_opacity', '0.05');
if ($watermarkOpacity <= 0 || $watermarkOpacity > 0.4) {
    $watermarkOpacity = 0.05;
}

// Candidate Name (Supports custom display name override)
$displayName = !empty($cert['display_name']) ? $cert['display_name'] : $cert['candidate_name'];

// Signatures Configuration (Supports per-certificate custom overrides)
$sig1Title = !empty($cert['signatory_1_title']) ? $cert['signatory_1_title'] : getSetting('cert_signature_1_title', 'Competition Coordinator');
$sig1Name  = !empty($cert['signatory_1_name']) ? $cert['signatory_1_name'] : getSetting('cert_signature_1_name', '');
$sig1Image = getSetting('cert_signature_1_image', '');
$sig1ImageUrl = ($sig1Image && file_exists(UPLOADS_PATH . '/' . $sig1Image)) ? upload($sig1Image) : '';

$sig2Title = !empty($cert['signatory_2_title']) ? $cert['signatory_2_title'] : getSetting('cert_signature_2_title', 'Director / Principal');
$sig2Name  = !empty($cert['signatory_2_name']) ? $cert['signatory_2_name'] : getSetting('cert_signature_2_name', '');
$sig2Image = getSetting('cert_signature_2_image', '');
$sig2ImageUrl = ($sig2Image && file_exists(UPLOADS_PATH . '/' . $sig2Image)) ? upload($sig2Image) : '';

// Certificate Category
$certType = $cert['certificate_type']; // 'position', 'participation', 'merit'
$isPosition = ($certType === 'position');
$isMerit = ($certType === 'merit');
$positionText = $cert['position'] ?: ($isPosition ? '1st Position' : 'Participant');
$customStatement = $cert['custom_statement'] ?? '';

// Extract Rank Number for Winner Badge
$rankNum = 0;
if (preg_match('/(\d+)/', $positionText, $matches)) {
    $rankNum = (int)$matches[1];
} elseif (stripos($positionText, '1st') !== false || stripos($positionText, 'First') !== false) {
    $rankNum = 1;
} elseif (stripos($positionText, '2nd') !== false || stripos($positionText, 'Second') !== false) {
    $rankNum = 2;
} elseif (stripos($positionText, '3rd') !== false || stripos($positionText, 'Third') !== false) {
    $rankNum = 3;
}

// Authoritative Snapshots (Exact format without recalculation)
$netWpm = number_format((float)$cert['net_wpm_snapshot'], 2);
$accuracy = number_format((float)$cert['accuracy_snapshot'], 2);
$score = number_format((float)$cert['score_snapshot'], 2);
$compDateFormatted = formatDate($cert['competition_date'], 'd-m-Y');
$issueDateFormatted = formatDate($cert['issued_at'], 'd-m-Y');
$rollNumber = $cert['roll_number'] ?: $cert['registration_number'];

// Active Editor Tab
$showEditor = isset($_GET['editor']) && $_GET['editor'] === '1';
$activeTab = trim($_GET['tab'] ?? 'content');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate — <?= e($displayName) ?> (<?= e($cert['certificate_number']) ?>)</title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <style>
        /* ============================================================
           GLOBAL PRINT & A4 LANDSCAPE GEOMETRY
           Exact A4 Landscape: 297mm × 210mm
           ============================================================ */
        @page {
            size: A4 landscape;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html, body {
            margin: 0;
            padding: 0;
            background-color: #0f172a;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #0f172a;
        }

        .cert-screen-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 10px;
            min-height: 100vh;
        }

        /* Top Interactive Toolbar */
        .cert-toolbar {
            width: 100%;
            max-width: 297mm;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.35);
        }

        .template-btn {
            border: 2px solid transparent;
            transition: all 0.2s ease-in-out;
            font-weight: 600;
            font-size: 13px;
        }
        .template-btn.active {
            border-color: #f59e0b;
            background-color: #0f172a;
            color: #f59e0b;
            box-shadow: 0 0 12px rgba(245, 158, 11, 0.4);
        }

        /* Certificate Editor Panel */
        .cert-editor-card {
            width: 100%;
            max-width: 297mm;
            background: #1e293b;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.4);
            color: #f8fafc;
            display: <?= $showEditor ? 'block' : 'none' ?>;
        }

        .editor-tab-btn {
            background: #0f172a;
            color: #94a3b8;
            border: 1px solid #334155;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .editor-tab-btn.active, .editor-tab-btn:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #3b82f6;
        }

        /* Base A4 Canvas */
        .cert-canvas {
            width: 297mm;
            height: 210mm;
            max-width: 297mm;
            max-height: 210mm;
            background: #ffffff;
            position: relative;
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
            overflow: hidden;
            page-break-after: avoid;
            page-break-inside: avoid;
        }

        /* Watermark Layer */
        .cert-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-12deg);
            width: 85%;
            height: 85%;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 0;
        }
        .cert-watermark-logo-img {
            max-width: 380px;
            max-height: 380px;
            object-fit: contain;
            filter: grayscale(100%);
        }
        .cert-watermark svg {
            width: 320px;
            height: 320px;
            fill: #0f172a;
        }
        .cert-watermark-text {
            font-size: 50px;
            font-weight: 900;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 10px;
            text-align: center;
            line-height: 1.1;
        }

        .cert-content-layer {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Logo Styling */
        .cert-logo-img {
            max-height: <?= $certLogoSize ?>px;
            max-width: <?= round($certLogoSize * 2.8) ?>px;
            object-fit: contain;
        }
        .cert-logo-placeholder {
            width: <?= $certLogoSize ?>px;
            height: <?= $certLogoSize ?>px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a, #0f172a);
            color: #fbbf24;
            font-size: <?= round($certLogoSize * 0.45) ?>px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        /* Standard Typography */
        .cert-certify-label {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 2px;
        }
        .cert-candidate-name {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.5px;
            line-height: 1.15;
            margin: 4px 0 6px 0;
            word-wrap: break-word;
            max-width: 90%;
        }
        .cert-body-statement {
            font-size: 14.5px;
            line-height: 1.5;
            color: #334155;
            max-width: 820px;
            margin: 0 auto;
        }
        .cert-highlight-speed {
            font-weight: 800;
            color: #1e3a8a;
        }
        .cert-highlight-accuracy {
            font-weight: 800;
            color: #047857;
        }

        /* Signatures & Seal Block */
        .cert-signature-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding: 0 35px;
            margin-top: 4px;
        }
        .cert-sig-item {
            width: 210px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .cert-sig-img-wrap {
            height: 38px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            margin-bottom: 2px;
        }
        .cert-sig-img {
            max-height: 36px;
            max-width: 160px;
            object-fit: contain;
        }
        .cert-sig-line {
            width: 100%;
            border-top: 1.5px solid #334155;
            margin-bottom: 4px;
            padding-top: 2px;
        }
        .cert-sig-title {
            font-size: 11.5px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cert-sig-name {
            font-size: 11px;
            color: #64748b;
        }
        .cert-seal-center {
            text-align: center;
        }
        .cert-seal-badge {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            border: 2px dashed #b8860b;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #b8860b;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
        }

        /* Bottom Identification Footer */
        .cert-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            padding: 4px 20px 0 20px;
            border-top: 1px solid #e2e8f0;
            margin-top: 6px;
        }

        /* ============================================================
           TEMPLATE 1: CLASSIC PROFESSIONAL
           ============================================================ */
        .tpl-classic {
            background: #fffdf9 radial-gradient(circle, #ffffff 0%, #f9f6ee 100%);
            padding: 10mm;
            height: 100%;
            font-family: 'Georgia', 'Times New Roman', serif;
        }
        .tpl-classic .outer-border {
            border: 3.5px double #b8860b;
            height: 100%;
            padding: 3.5mm;
            position: relative;
        }
        .tpl-classic .inner-border {
            border: 1.5px solid #1e3a8a;
            height: 100%;
            padding: 6mm 8mm;
            text-align: center;
            position: relative;
            background: rgba(255, 255, 255, 0.65);
        }
        .tpl-classic .corner-flourish {
            position: absolute;
            width: 28px;
            height: 28px;
            border-color: #b8860b;
            border-style: solid;
        }
        .tpl-classic .corner-tl { top: 5px; left: 5px; border-width: 3px 0 0 3px; }
        .tpl-classic .corner-tr { top: 5px; right: 5px; border-width: 3px 3px 0 0; }
        .tpl-classic .corner-bl { bottom: 5px; left: 5px; border-width: 0 0 3px 3px; }
        .tpl-classic .corner-br { bottom: 5px; right: 5px; border-width: 0 3px 3px 0; }
        .tpl-classic .inst-heading {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #0f2b5c;
            text-transform: uppercase;
        }
        .tpl-classic .title-banner {
            font-size: 27px;
            font-weight: bold;
            color: #b8860b;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 2px 0 4px 0;
        }
        .tpl-classic .candidate-name-classic {
            font-size: 30px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 2px solid #b8860b;
            display: inline-block;
            padding: 0 30px 2px 30px;
            margin: 2px 0 6px 0;
        }

        /* ============================================================
           TEMPLATE 2: MODERN PREMIUM
           ============================================================ */
        .tpl-modern {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 8mm;
            height: 100%;
            font-family: 'Segoe UI', -apple-system, Roboto, sans-serif;
        }
        .tpl-modern .frame-wrapper {
            border: 3px solid #0f172a;
            height: 100%;
            padding: 5mm 8mm;
            position: relative;
            background: #ffffff;
            box-shadow: inset 0 0 0 2px #3b82f6, inset 0 0 0 6px #ffffff, inset 0 0 0 8px #e2e8f0;
            text-align: center;
        }
        .tpl-modern .inst-heading-modern {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 1.5px;
            color: #0f172a;
            text-transform: uppercase;
        }
        .tpl-modern .title-banner-modern {
            font-size: 25px;
            font-weight: 800;
            background: linear-gradient(90deg, #1d4ed8, #0ea5e9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            margin: 2px 0 4px 0;
        }
        .tpl-modern .stat-pills-row {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin: 6px 0;
        }
        .tpl-modern .stat-pill {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 4px 14px;
            min-width: 120px;
        }
        .tpl-modern .stat-pill-val {
            font-size: 16px;
            font-weight: 800;
            color: #1e40af;
        }
        .tpl-modern .stat-pill-lbl {
            font-size: 9.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        /* ============================================================
           TEMPLATE 3: ACADEMIC EXCELLENCE
           ============================================================ */
        .tpl-academic {
            background: #fcfbf9;
            padding: 10mm;
            height: 100%;
            font-family: 'Times New Roman', Times, serif;
        }
        .tpl-academic .academic-border {
            border: 3px solid #701a24;
            height: 100%;
            padding: 3.5mm;
            position: relative;
            box-shadow: inset 0 0 0 2px #d4af37;
        }
        .tpl-academic .academic-inner {
            border: 1px solid #701a24;
            height: 100%;
            padding: 5mm 8mm;
            text-align: center;
            background: #ffffff;
            position: relative;
        }
        .tpl-academic .inst-heading-acad {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 21px;
            font-weight: 900;
            color: #701a24;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .tpl-academic .title-banner-acad {
            font-size: 25px;
            font-weight: bold;
            color: #701a24;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            border-top: 1px solid #d4af37;
            border-bottom: 1px solid #d4af37;
            display: inline-block;
            padding: 2px 25px;
            margin: 2px auto 4px auto;
        }
        .tpl-academic .perf-table {
            width: 70%;
            margin: 4px auto;
            border-collapse: collapse;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
        }
        .tpl-academic .perf-table th {
            background: #701a24;
            color: #ffffff;
            padding: 3px 6px;
            border: 1px solid #701a24;
            text-transform: uppercase;
            font-size: 9.5px;
            letter-spacing: 0.5px;
        }
        .tpl-academic .perf-table td {
            border: 1px solid #e2e8f0;
            padding: 3px 6px;
            font-weight: 600;
            background: #fdfdfd;
        }

        /* ============================================================
           TEMPLATE 4: TYPING CHAMPION (WINNER EDITION)
           ============================================================ */
        .tpl-champion {
            background: #0f172a;
            padding: 8mm;
            height: 100%;
            color: #f8fafc;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .tpl-champion .champion-frame {
            border: 4px solid #f59e0b;
            height: 100%;
            padding: 5mm 8mm;
            position: relative;
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
            box-shadow: inset 0 0 25px rgba(245, 158, 11, 0.25);
            text-align: center;
        }
        .tpl-champion .rank-1-accent { border-color: #fbbf24 !important; }
        .tpl-champion .rank-2-accent { border-color: #94a3b8 !important; }
        .tpl-champion .rank-3-accent { border-color: #d97706 !important; }

        .tpl-champion .inst-heading-champ {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #f8fafc;
            text-transform: uppercase;
        }
        .tpl-champion .title-banner-champ {
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #fbbf24;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            margin: 2px 0;
        }
        .tpl-champion .position-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 3px 20px;
            border-radius: 50px;
            font-size: 13.5px;
            font-weight: 900;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin: 2px auto 4px auto;
        }
        .tpl-champion .badge-gold { background: linear-gradient(90deg, #f59e0b, #d97706); color: #0f172a; box-shadow: 0 3px 12px rgba(245, 158, 11, 0.4); }
        .tpl-champion .badge-silver { background: linear-gradient(90deg, #94a3b8, #64748b); color: #0f172a; box-shadow: 0 3px 12px rgba(148, 163, 184, 0.4); }
        .tpl-champion .badge-bronze { background: linear-gradient(90deg, #ea580c, #c2410c); color: #ffffff; box-shadow: 0 3px 12px rgba(234, 88, 12, 0.4); }
        .tpl-champion .badge-generic { background: linear-gradient(90deg, #3b82f6, #1d4ed8); color: #ffffff; }

        .tpl-champion .champ-stats-row {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin: 6px 0;
        }
        .tpl-champion .champ-stat-card {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 6px;
            padding: 4px 14px;
            min-width: 115px;
        }
        .tpl-champion .champ-stat-num {
            font-size: 16px;
            font-weight: 900;
            color: #fbbf24;
        }
        .tpl-champion .champ-stat-lbl {
            font-size: 9.5px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
        }

        /* ============================================================
           PRINT MEDIA SPECIFICATION
           ============================================================ */
        @media print {
            body {
                background: transparent !important;
                padding: 0 !important;
            }
            .cert-screen-wrapper {
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
            }
            .no-print, .cert-toolbar, .cert-editor-card {
                display: none !important;
            }
            .cert-canvas {
                box-shadow: none !important;
                width: 297mm !important;
                height: 210mm !important;
                margin: 0 !important;
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
                overflow: hidden !important;
            }
        }
    </style>
</head>
<body>

<div class="cert-screen-wrapper">

    <!-- Interactive Design Selector Toolbar (Hidden during Print) -->
    <div class="cert-toolbar no-print">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-light small fw-bold mb-1">
                    <i class="fas fa-palette text-warning me-1"></i> SELECT CERTIFICATE DESIGN
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($availableTemplates as $tmplKey => $tmplInfo): ?>
                        <a href="<?= url('certificates-view') ?>&id=<?= $certId ?>&template=<?= $tmplKey ?><?= $showEditor ? '&editor=1&tab=' . $activeTab : '' ?>" 
                           class="btn btn-sm btn-dark template-btn <?= $activeTemplate === $tmplKey ? 'active' : '' ?>">
                            <i class="fas <?= $tmplInfo['icon'] ?> me-1"></i> <?= e($tmplInfo['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Toggle Editor Button -->
                <button type="button" onclick="toggleCertEditor()" class="btn btn-primary btn-sm fw-bold shadow-sm">
                    <i class="fas fa-edit me-1"></i> Certificate Editor & Branding
                </button>

                <a href="<?= url('certificates-view') ?>&id=<?= $certId ?>&template=<?= $activeTemplate ?>&save_template=1&csrf_token=<?= CSRF::token() ?>" 
                   class="btn btn-outline-info btn-sm fw-bold shadow-sm" title="Save this template as default for this certificate">
                    <i class="fas fa-save me-1"></i> Set as Default
                </a>

                <button onclick="window.print()" class="btn btn-warning btn-sm px-4 fw-bold shadow">
                    <i class="fas fa-print me-1"></i> Print (A4)
                </button>

                <a href="<?= url('certificates') ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
                    <i class="fas fa-times me-1"></i> Close
                </a>
            </div>
        </div>
    </div>

    <!-- Live Interactive Certificate Editor Panel -->
    <div class="cert-editor-card no-print" id="certEditorPanel">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary pb-2">
            <div class="d-flex align-items-center gap-3">
                <h5 class="fw-bold mb-0 text-warning">
                    <i class="fas fa-sliders-h me-2"></i> Certificate Editor & Brand Manager
                </h5>
                <div class="d-flex gap-1">
                    <button type="button" class="editor-tab-btn <?= $activeTab === 'content' ? 'active' : '' ?>" onclick="switchEditorTab('content')">
                        <i class="fas fa-file-alt"></i> Candidate & Wording
                    </button>
                    <button type="button" class="editor-tab-btn <?= $activeTab === 'branding' ? 'active' : '' ?>" onclick="switchEditorTab('branding')">
                        <i class="fas fa-image"></i> Logo & Header
                    </button>
                    <button type="button" class="editor-tab-btn <?= $activeTab === 'watermark' ? 'active' : '' ?>" onclick="switchEditorTab('watermark')">
                        <i class="fas fa-tint"></i> Watermark
                    </button>
                    <button type="button" class="editor-tab-btn <?= $activeTab === 'signatures' ? 'active' : '' ?>" onclick="switchEditorTab('signatures')">
                        <i class="fas fa-signature"></i> Signatures & Authority
                    </button>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" onclick="toggleCertEditor()"></button>
        </div>

        <!-- TAB 1: Content & Candidate -->
        <div id="tab-content" class="editor-tab-content" style="display: <?= $activeTab === 'content' ? 'block' : 'none' ?>;">
            <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>&template=<?= $activeTemplate ?>" id="customizationForm">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" id="editorActionField" value="save_customization">
                <input type="hidden" name="template_key" value="<?= e($activeTemplate) ?>">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-light">Candidate Display Name</label>
                        <input type="text" name="display_name" id="inputDisplayName" class="form-control form-control-sm bg-dark text-light border-secondary"
                               value="<?= e($displayName) ?>" placeholder="Candidate Full Name">
                        <div class="form-text text-muted small">Update spelling or title on certificate.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-light">Certificate Type / Category</label>
                        <select name="certificate_type" id="inputCertType" class="form-select form-select-sm bg-dark text-light border-secondary">
                            <option value="position" <?= $isPosition ? 'selected' : '' ?>>Achievement / Position</option>
                            <option value="participation" <?= (!$isPosition && !$isMerit) ? 'selected' : '' ?>>Participation</option>
                            <option value="merit" <?= $isMerit ? 'selected' : '' ?>>Merit / Qualified</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-light">Honor / Position Title</label>
                        <input type="text" name="position" id="inputPosition" class="form-control form-control-sm bg-dark text-light border-secondary"
                               value="<?= e($positionText) ?>" placeholder="e.g. 1st Position, Winner, Participant">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-light">Custom Body Statement / Wording (Optional)</label>
                        <textarea name="custom_statement" id="inputCustomStatement" rows="2" class="form-control form-control-sm bg-dark text-light border-secondary"
                                  placeholder="Leave blank to use the standard institutional wording automatically..."><?= e($customStatement) ?></textarea>
                        <div class="form-text text-muted small">Leave empty to use the standard institutional certificate wording.</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetCertificateDefaults()">
                        <i class="fas fa-undo me-1"></i> Reset to Defaults
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleCertEditor()">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm px-4 fw-bold shadow">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- TAB 2: Branding & Logo Management -->
        <div id="tab-branding" class="editor-tab-content" style="display: <?= $activeTab === 'branding' ? 'block' : 'none' ?>;">
            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <div class="p-3 bg-dark rounded border border-secondary">
                        <h6 class="fw-bold text-warning mb-2"><i class="fas fa-image me-1"></i> Certificate Logo</h6>
                        <p class="small text-muted mb-3">
                            Current Active Logo: 
                            <strong class="text-light">
                                <?= $logoSourceType === 'cert' ? 'Custom Certificate Logo' : ($logoSourceType === 'institute' ? 'Default Institute Logo' : 'Vector Fallback Emblem') ?>
                            </strong>
                        </p>

                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div style="width: 100px; height: 60px; background: #ffffff; border-radius: 6px; display: flex; align-items: center; justify-content: center; padding: 4px;">
                                <?php if ($effectiveLogoUrl): ?>
                                    <img src="<?= $effectiveLogoUrl ?>" alt="Logo Preview" style="max-height: 50px; max-width: 90px; object-fit: contain;">
                                <?php else: ?>
                                    <div class="cert-logo-placeholder" style="width: 38px; height: 38px; font-size: 16px;"><i class="fas fa-graduation-cap"></i></div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if ($logoSourceType === 'cert'): ?>
                                    <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>" class="d-inline">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="remove_cert_logo">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash-alt me-1"></i> Remove & Restore Default
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Using Institute Default</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>" enctype="multipart/form-data">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="upload_cert_logo">
                            <label class="form-label small fw-bold text-light">Upload New Certificate Logo</label>
                            <div class="input-group input-group-sm mb-1">
                                <input type="file" name="cert_logo_file" class="form-control bg-dark text-light border-secondary" accept="image/*" required>
                                <button type="submit" class="btn btn-primary fw-bold">
                                    <i class="fas fa-upload me-1"></i> Upload
                                </button>
                            </div>
                            <small class="text-muted">Accepted: PNG (transparent preferred), JPG, WEBP, SVG. Max 2MB.</small>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>&template=<?= $activeTemplate ?>">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="save_customization">
                        <div class="p-3 bg-dark rounded border border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-heading me-1"></i> Institute & Subtitle</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-light">Institute Name (From Global Settings)</label>
                                <input type="text" class="form-control form-control-sm bg-secondary text-light border-secondary" value="<?= e($instituteName) ?>" disabled>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-light">Certificate Department Subtitle</label>
                                <input type="text" name="cert_header_subtitle" class="form-control form-control-sm bg-dark text-light border-secondary" 
                                       value="<?= e($headerSub) ?>" placeholder="e.g. Department of Information Technology & Typing Examination">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-light d-flex justify-content-between">
                                    <span><i class="fas fa-expand-arrows-alt me-1"></i> Logo Size Adjustment:</span>
                                    <span id="logoSizeValLabel" class="text-warning fw-bold"><?= $certLogoSize ?>px</span>
                                </label>
                                <input type="range" name="cert_logo_size" id="logoSizeRange" class="form-range" min="25" max="110" step="1" value="<?= $certLogoSize ?>">
                                <div class="d-flex justify-content-between text-muted" style="font-size: 10px;">
                                    <span>Compact (25px)</span>
                                    <span>Standard (52px)</span>
                                    <span>Large (110px)</span>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success btn-sm fw-bold">
                                <i class="fas fa-save me-1"></i> Save Branding & Logo Size
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 3: Watermark Controls -->
        <div id="tab-watermark" class="editor-tab-content" style="display: <?= $activeTab === 'watermark' ? 'block' : 'none' ?>;">
            <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>&template=<?= $activeTemplate ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="save_customization">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-light">Watermark Status</label>
                        <select name="cert_watermark_enabled" id="watermarkEnabledSelect" class="form-select form-select-sm bg-dark text-light border-secondary">
                            <option value="1" <?= $watermarkEnabled ? 'selected' : '' ?>>Enabled (Show on Certificate)</option>
                            <option value="0" <?= !$watermarkEnabled ? 'selected' : '' ?>>Disabled (Hide Watermark)</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-light">Watermark Source</label>
                        <select name="cert_watermark_source" class="form-select form-select-sm bg-dark text-light border-secondary">
                            <option value="logo" <?= $watermarkSource === 'logo' ? 'selected' : '' ?>>Use Certificate/Institute Logo</option>
                            <option value="text" <?= $watermarkSource === 'text' ? 'selected' : '' ?>>Use Institute Name & Shield Crest</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-light">Watermark Opacity: <span id="opacityValLabel" class="text-warning"><?= $watermarkOpacity ?></span></label>
                        <input type="range" name="cert_watermark_opacity" id="watermarkOpacityRange" class="form-range" min="0.01" max="0.25" step="0.01" value="<?= $watermarkOpacity ?>">
                        <div class="form-text text-muted small">Recommended: 0.04 to 0.08 for print safety.</div>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top border-secondary text-end">
                    <button type="submit" class="btn btn-success btn-sm px-4 fw-bold shadow">
                        <i class="fas fa-save me-1"></i> Save Watermark Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 4: Signatures & Authority -->
        <div id="tab-signatures" class="editor-tab-content" style="display: <?= $activeTab === 'signatures' ? 'block' : 'none' ?>;">
            <div class="row g-4">
                <!-- Left Signatory: Coordinator -->
                <div class="col-md-6">
                    <div class="p-3 bg-dark rounded border border-secondary">
                        <h6 class="fw-bold text-warning mb-3"><i class="fas fa-pen-nib me-1"></i> Left Signatory (Coordinator)</h6>
                        <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>&template=<?= $activeTemplate ?>" class="mb-3">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="save_customization">

                            <div class="mb-2">
                                <label class="form-label small fw-bold text-light">Title / Designation</label>
                                <input type="text" name="signatory_1_title" id="inputSig1Title" class="form-control form-control-sm bg-dark text-light border-secondary"
                                       value="<?= e($sig1Title) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-light">Signatory Full Name</label>
                                <input type="text" name="signatory_1_name" id="inputSig1Name" class="form-control form-control-sm bg-dark text-light border-secondary"
                                       value="<?= e($sig1Name) ?>" placeholder="e.g. Prof. Ahmed Ali">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm fw-bold">
                                <i class="fas fa-save me-1"></i> Update Coordinator Text
                            </button>
                        </form>

                        <hr class="border-secondary my-3">

                        <!-- Coordinator Signature Image Upload -->
                        <div>
                            <label class="form-label small fw-bold text-light">Coordinator Signature Image</label>
                            <?php if ($sig1ImageUrl): ?>
                                <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-secondary rounded">
                                    <img src="<?= $sig1ImageUrl ?>" alt="Sig1" style="max-height: 35px; max-width: 120px; object-fit: contain;">
                                    <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="remove_sig1_image">
                                        <button type="submit" class="btn btn-danger btn-sm py-0 px-2" title="Remove signature image">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>" enctype="multipart/form-data">
                                <?= CSRF::field() ?>
                                <input type="hidden" name="action" value="upload_sig1_image">
                                <div class="input-group input-group-sm">
                                    <input type="file" name="sig1_file" class="form-control bg-dark text-light border-secondary" accept="image/*" required>
                                    <button type="submit" class="btn btn-outline-warning fw-bold">Upload</button>
                                </div>
                                <small class="text-muted">Transparent PNG recommended (Max 2MB).</small>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Signatory: Director / Principal -->
                <div class="col-md-6">
                    <div class="p-3 bg-dark rounded border border-secondary">
                        <h6 class="fw-bold text-warning mb-3"><i class="fas fa-stamp me-1"></i> Right Signatory (Director / Principal)</h6>
                        <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>&template=<?= $activeTemplate ?>" class="mb-3">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="save_customization">

                            <div class="mb-2">
                                <label class="form-label small fw-bold text-light">Title / Designation</label>
                                <input type="text" name="signatory_2_title" id="inputSig2Title" class="form-control form-control-sm bg-dark text-light border-secondary"
                                       value="<?= e($sig2Title) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-light">Signatory Full Name</label>
                                <input type="text" name="signatory_2_name" id="inputSig2Name" class="form-control form-control-sm bg-dark text-light border-secondary"
                                       value="<?= e($sig2Name) ?>" placeholder="e.g. Dr. Muhammad Tariq">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm fw-bold">
                                <i class="fas fa-save me-1"></i> Update Director Text
                            </button>
                        </form>

                        <hr class="border-secondary my-3">

                        <!-- Director Signature Image Upload -->
                        <div>
                            <label class="form-label small fw-bold text-light">Director Signature Image</label>
                            <?php if ($sig2ImageUrl): ?>
                                <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-secondary rounded">
                                    <img src="<?= $sig2ImageUrl ?>" alt="Sig2" style="max-height: 35px; max-width: 120px; object-fit: contain;">
                                    <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="action" value="remove_sig2_image">
                                        <button type="submit" class="btn btn-danger btn-sm py-0 px-2" title="Remove signature image">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?= url('certificates-view') ?>&id=<?= $certId ?>" enctype="multipart/form-data">
                                <?= CSRF::field() ?>
                                <input type="hidden" name="action" value="upload_sig2_image">
                                <div class="input-group input-group-sm">
                                    <input type="file" name="sig2_file" class="form-control bg-dark text-light border-secondary" accept="image/*" required>
                                    <button type="submit" class="btn btn-outline-warning fw-bold">Upload</button>
                                </div>
                                <small class="text-muted">Transparent PNG recommended (Max 2MB).</small>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Certificate Canvas (A4 Landscape: 297mm x 210mm) -->
    <div class="cert-canvas">

        <!-- Background Watermark Layer -->
        <?php if ($watermarkEnabled): ?>
            <div class="cert-watermark" id="certWatermarkLayer" style="opacity: <?= $watermarkOpacity ?>;">
                <?php if ($watermarkSource === 'logo' && $effectiveLogoUrl): ?>
                    <img src="<?= $effectiveLogoUrl ?>" alt="Watermark" class="cert-watermark-logo-img">
                <?php else: ?>
                    <svg viewBox="0 0 24 24">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12v4.7c0 4.54-3.1 8.79-7 9.88-3.9-1.09-7-5.34-7-9.88V6.3l7-3.12zM12 6a4 4 0 100 8 4 4 0 000-8zm0 2a2 2 0 110 4 2 2 0 010-4z"/>
                    </svg>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($activeTemplate === 'classic'): ?>
            <!-- ============================================================
                 TEMPLATE 1: CLASSIC PROFESSIONAL
                 ============================================================ -->
            <div class="tpl-classic">
                <div class="outer-border">
                    <div class="corner-flourish corner-tl"></div>
                    <div class="corner-flourish corner-tr"></div>
                    <div class="corner-flourish corner-bl"></div>
                    <div class="corner-flourish corner-br"></div>

                    <div class="inner-border">
                        <div class="cert-content-layer">
                            <!-- Header & Branding -->
                            <div>
                                <div class="d-flex justify-content-center align-items-center gap-3 mb-1">
                                    <?php if ($effectiveLogoUrl): ?>
                                        <img src="<?= $effectiveLogoUrl ?>" alt="Logo" class="cert-logo-img">
                                    <?php else: ?>
                                        <div class="cert-logo-placeholder"><i class="fas fa-graduation-cap"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="inst-heading"><?= e($instituteName) ?></div>
                                        <div style="font-size: 11px; letter-spacing: 1.5px; color: #64748b; text-transform: uppercase; font-family: 'Segoe UI', Arial, sans-serif;">
                                            <?= e($headerSub) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="title-banner cert-title-banner">
                                    <?= $isPosition ? 'Certificate of Achievement' : ($isMerit ? 'Certificate of Merit' : 'Certificate of Participation') ?>
                                </div>
                            </div>

                            <!-- Standardized Candidate Wording -->
                            <div>
                                <div class="cert-certify-label">This is to certify that</div>
                                <div class="candidate-name-classic cert-candidate-name-el"><?= e($displayName) ?></div>

                                <div class="cert-body-statement cert-body-statement-el">
                                    <?php if ($customStatement): ?>
                                        <?= nl2br(e($customStatement)) ?>
                                    <?php elseif ($isPosition): ?>
                                        has successfully participated in the <strong><?= e($cert['competition_name']) ?></strong> and has secured <strong class="cert-pos-highlight" style="color: #b8860b; font-size: 16px;"><?= e($positionText) ?></strong> with a Net Typing Speed of <span class="cert-highlight-speed"><?= $netWpm ?> WPM</span> and Accuracy of <span class="cert-highlight-accuracy"><?= $accuracy ?>%</span>.<br>
                                        <span class="text-muted" style="font-size: 12.5px;">This certificate is awarded in recognition of outstanding typing performance, accuracy, speed, dedication, and achievement in the competition.</span>
                                    <?php elseif ($isMerit): ?>
                                        has successfully completed the <strong><?= e($cert['competition_name']) ?></strong> and achieved the required performance standard with a Net Typing Speed of <span class="cert-highlight-speed"><?= $netWpm ?> WPM</span> and Accuracy of <span class="cert-highlight-accuracy"><?= $accuracy ?>%</span>.<br>
                                        <span class="text-muted" style="font-size: 12.5px;">This certificate is presented in recognition of typing proficiency, accuracy, and successful performance.</span>
                                    <?php else: ?>
                                        has successfully participated in the <strong><?= e($cert['competition_name']) ?></strong> organized by <strong><?= e($instituteName) ?></strong>.<br>
                                        <span class="text-muted" style="font-size: 12.5px;">This certificate is awarded in recognition of participation, commitment, and demonstrated typing effort in the competition.</span><br>
                                        <span style="font-size: 13px; font-weight: 700; color: #1e3a8a;">Net Typing Speed: <?= $netWpm ?> WPM &bull; Accuracy: <?= $accuracy ?>% &bull; Final Score: <?= $score ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Dual Signatures & Seal -->
                            <div>
                                <div class="cert-signature-row">
                                    <div class="cert-sig-item">
                                        <div class="cert-sig-img-wrap">
                                            <?php if ($sig1ImageUrl): ?>
                                                <img src="<?= $sig1ImageUrl ?>" alt="Signature" class="cert-sig-img">
                                            <?php endif; ?>
                                        </div>
                                        <div class="cert-sig-line"></div>
                                        <div class="cert-sig-title cert-sig1-title-el"><?= e($sig1Title) ?></div>
                                        <div class="cert-sig-name cert-sig1-name-el"><?= e($sig1Name) ?></div>
                                    </div>

                                    <div class="cert-seal-center">
                                        <div class="cert-seal-badge">
                                            <i class="fas fa-award fa-lg mb-1"></i>
                                            SEAL
                                        </div>
                                    </div>

                                    <div class="cert-sig-item">
                                        <div class="cert-sig-img-wrap">
                                            <?php if ($sig2ImageUrl): ?>
                                                <img src="<?= $sig2ImageUrl ?>" alt="Signature" class="cert-sig-img">
                                            <?php endif; ?>
                                        </div>
                                        <div class="cert-sig-line"></div>
                                        <div class="cert-sig-title cert-sig2-title-el"><?= e($sig2Title) ?></div>
                                        <div class="cert-sig-name cert-sig2-name-el"><?= e($sig2Name) ?></div>
                                    </div>
                                </div>

                                <!-- Standardized Footer Dates & Number -->
                                <div class="cert-footer-row">
                                    <div><strong>Date of Issue:</strong> <?= $issueDateFormatted ?></div>
                                    <div><strong>Certificate No:</strong> <span class="font-monospace text-dark"><?= e($cert['certificate_number']) ?></span></div>
                                    <div><strong>Competition Date:</strong> <?= $compDateFormatted ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTemplate === 'modern'): ?>
            <!-- ============================================================
                 TEMPLATE 2: MODERN PREMIUM
                 ============================================================ -->
            <div class="tpl-modern">
                <div class="frame-wrapper">
                    <div class="cert-content-layer">
                        <!-- Top Header -->
                        <div>
                            <div class="d-flex justify-content-between align-items-center px-3 mb-1">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($effectiveLogoUrl): ?>
                                        <img src="<?= $effectiveLogoUrl ?>" alt="Logo" class="cert-logo-img">
                                    <?php else: ?>
                                        <div class="cert-logo-placeholder"><i class="fas fa-certificate"></i></div>
                                    <?php endif; ?>
                                    <div class="text-start">
                                        <div class="inst-heading-modern"><?= e($instituteName) ?></div>
                                        <div class="small text-muted fw-bold text-uppercase" style="letter-spacing: 1px;">Official Certification of Typing Competence</div>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge bg-dark font-monospace text-warning px-3 py-2 border border-secondary">
                                        <?= e($cert['certificate_number']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="title-banner-modern cert-title-banner">
                                <?= $isPosition ? 'Certificate of Speed & Achievement' : ($isMerit ? 'Certificate of Proficiency & Merit' : 'Certificate of Participation') ?>
                            </div>
                        </div>

                        <!-- Candidate Body -->
                        <div>
                            <div class="cert-certify-label">This Credential Is Proudly Conferred Upon</div>
                            <div class="cert-candidate-name cert-candidate-name-el mx-auto"><?= e($displayName) ?></div>

                            <div class="cert-body-statement cert-body-statement-el">
                                <?php if ($customStatement): ?>
                                    <?= nl2br(e($customStatement)) ?>
                                <?php elseif ($isPosition): ?>
                                    for standout competitive performance in the <strong><?= e($cert['competition_name']) ?></strong> securing <strong class="text-primary cert-pos-highlight"><?= e($positionText) ?></strong> with verified Net Speed of <span class="cert-highlight-speed"><?= $netWpm ?> WPM</span> and <span class="cert-highlight-accuracy"><?= $accuracy ?>% Accuracy</span>.
                                <?php elseif ($isMerit): ?>
                                    for successfully meeting institutional proficiency benchmarks in the <strong><?= e($cert['competition_name']) ?></strong> achieving <span class="cert-highlight-speed"><?= $netWpm ?> WPM Net Speed</span> with <span class="cert-highlight-accuracy"><?= $accuracy ?>% Accuracy</span>.
                                <?php else: ?>
                                    for active participation and dedication in the <strong><?= e($cert['competition_name']) ?></strong> organized by <strong><?= e($instituteName) ?></strong>.
                                <?php endif; ?>
                            </div>

                            <!-- Modern Stat Pills -->
                            <div class="stat-pills-row">
                                <div class="stat-pill">
                                    <div class="stat-pill-val"><?= $netWpm ?></div>
                                    <div class="stat-pill-lbl">Net Speed (WPM)</div>
                                </div>
                                <div class="stat-pill">
                                    <div class="stat-pill-val"><?= $accuracy ?>%</div>
                                    <div class="stat-pill-lbl">Accuracy Rating</div>
                                </div>
                                <div class="stat-pill">
                                    <div class="stat-pill-val"><?= $score ?></div>
                                    <div class="stat-pill-lbl">Certified Score</div>
                                </div>
                                <?php if ($isPosition): ?>
                                    <div class="stat-pill" style="border-color: #3b82f6; background: #eff6ff;">
                                        <div class="stat-pill-val text-primary cert-pos-pill-el"><?= e($positionText) ?></div>
                                        <div class="stat-pill-lbl">Award Standing</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Signatures & Footer -->
                        <div>
                            <div class="cert-signature-row">
                                <div class="cert-sig-item">
                                    <div class="cert-sig-img-wrap">
                                        <?php if ($sig1ImageUrl): ?>
                                            <img src="<?= $sig1ImageUrl ?>" alt="Signature" class="cert-sig-img">
                                        <?php endif; ?>
                                    </div>
                                    <div class="cert-sig-line"></div>
                                    <div class="cert-sig-title cert-sig1-title-el"><?= e($sig1Title) ?></div>
                                    <div class="cert-sig-name cert-sig1-name-el"><?= e($sig1Name) ?></div>
                                </div>

                                <div class="cert-seal-center">
                                    <div style="font-size: 11px; font-weight: 700; color: #3b82f6; border: 1px dashed #3b82f6; border-radius: 4px; padding: 4px 12px;">
                                        <i class="fas fa-shield-alt me-1"></i> DIGITAL VERIFIED
                                    </div>
                                </div>

                                <div class="cert-sig-item">
                                    <div class="cert-sig-img-wrap">
                                        <?php if ($sig2ImageUrl): ?>
                                            <img src="<?= $sig2ImageUrl ?>" alt="Signature" class="cert-sig-img">
                                        <?php endif; ?>
                                    </div>
                                    <div class="cert-sig-line"></div>
                                    <div class="cert-sig-title cert-sig2-title-el"><?= e($sig2Title) ?></div>
                                    <div class="cert-sig-name cert-sig2-name-el"><?= e($sig2Name) ?></div>
                                </div>
                            </div>

                            <div class="cert-footer-row">
                                <div><strong>Date of Issue:</strong> <?= $issueDateFormatted ?></div>
                                <div><strong>Roll #:</strong> <?= e($rollNumber) ?></div>
                                <div><strong>Competition Date:</strong> <?= $compDateFormatted ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTemplate === 'academic'): ?>
            <!-- ============================================================
                 TEMPLATE 3: ACADEMIC EXCELLENCE
                 ============================================================ -->
            <div class="tpl-academic">
                <div class="academic-border">
                    <div class="academic-inner">
                        <div class="cert-content-layer">
                            <!-- Crest & Heading -->
                            <div>
                                <div class="d-flex justify-content-center align-items-center gap-3 mb-1">
                                    <?php if ($effectiveLogoUrl): ?>
                                        <img src="<?= $effectiveLogoUrl ?>" alt="Logo" class="cert-logo-img">
                                    <?php else: ?>
                                        <div class="cert-logo-placeholder" style="background: #701a24; color: #d4af37;"><i class="fas fa-book-open"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="inst-heading-acad"><?= e($instituteName) ?></div>
                                        <div style="font-size: 11px; letter-spacing: 1.5px; color: #701a24; font-weight: bold; text-transform: uppercase;">
                                            <?= e($headerSub) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="title-banner-acad cert-title-banner">Academic Typing Excellence</div>
                            </div>

                            <!-- Body Text -->
                            <div>
                                <div class="cert-certify-label" style="color: #701a24;">This Is To Solemnly Certify That</div>
                                <div class="cert-candidate-name cert-candidate-name-el mx-auto" style="font-family: 'Times New Roman', serif;"><?= e($displayName) ?></div>

                                <div class="cert-body-statement cert-body-statement-el">
                                    <?php if ($customStatement): ?>
                                        <?= nl2br(e($customStatement)) ?>
                                    <?php else: ?>
                                        has satisfactorily satisfied all institutional typing standards in the <strong><?= e($cert['competition_name']) ?></strong>
                                        <?= $isPosition ? "and was awarded <strong style='color: #701a24;' class='cert-pos-highlight'>" . e($positionText) . "</strong>" : "" ?>.
                                    <?php endif; ?>
                                </div>

                                <!-- Academic Evaluation Table -->
                                <table class="perf-table">
                                    <thead>
                                        <tr>
                                            <th>Evaluation Parameter</th>
                                            <th>Certified Metric</th>
                                            <th>Institutional Benchmark</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Net Typing Speed</td>
                                            <td><strong><?= $netWpm ?> WPM</strong></td>
                                            <td>>= 30.00 WPM</td>
                                            <td><span class="text-success fw-bold"><i class="fas fa-check-circle"></i> Certified</span></td>
                                        </tr>
                                        <tr>
                                            <td>Accuracy Percentage</td>
                                            <td><strong><?= $accuracy ?>%</strong></td>
                                            <td>>= 90.00%</td>
                                            <td><span class="text-success fw-bold"><i class="fas fa-check-circle"></i> Qualified</span></td>
                                        </tr>
                                        <tr>
                                            <td>Overall Score</td>
                                            <td><strong><?= $score ?> PTS</strong></td>
                                            <td>Standard Model</td>
                                            <td><span class="text-primary fw-bold cert-pos-table-el"><?= e($positionText) ?></span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Signatures & Footer -->
                            <div>
                                <div class="cert-signature-row">
                                    <div class="cert-sig-item">
                                        <div class="cert-sig-img-wrap">
                                            <?php if ($sig1ImageUrl): ?>
                                                <img src="<?= $sig1ImageUrl ?>" alt="Signature" class="cert-sig-img">
                                            <?php endif; ?>
                                        </div>
                                        <div class="cert-sig-line" style="border-top-color: #701a24;"></div>
                                        <div class="cert-sig-title cert-sig1-title-el" style="color: #701a24;"><?= e($sig1Title) ?></div>
                                        <div class="cert-sig-name cert-sig1-name-el"><?= e($sig1Name) ?></div>
                                    </div>

                                    <div class="cert-seal-center">
                                        <div class="cert-seal-badge" style="border-color: #701a24; color: #701a24;">
                                            <i class="fas fa-stamp fa-lg mb-1"></i>
                                            ACADEMIC
                                        </div>
                                    </div>

                                    <div class="cert-sig-item">
                                        <div class="cert-sig-img-wrap">
                                            <?php if ($sig2ImageUrl): ?>
                                                <img src="<?= $sig2ImageUrl ?>" alt="Signature" class="cert-sig-img">
                                            <?php endif; ?>
                                        </div>
                                        <div class="cert-sig-line" style="border-top-color: #701a24;"></div>
                                        <div class="cert-sig-title cert-sig2-title-el" style="color: #701a24;"><?= e($sig2Title) ?></div>
                                        <div class="cert-sig-name cert-sig2-name-el"><?= e($sig2Name) ?></div>
                                    </div>
                                </div>

                                <div class="cert-footer-row">
                                    <div><strong>Date of Issue:</strong> <?= $issueDateFormatted ?></div>
                                    <div><strong>Certificate ID:</strong> <span class="font-monospace text-dark"><?= e($cert['certificate_number']) ?></span></div>
                                    <div><strong>Conferment Date:</strong> <?= $compDateFormatted ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTemplate === 'champion'): ?>
            <!-- ============================================================
                 TEMPLATE 4: TYPING CHAMPION (WINNER EDITION)
                 ============================================================ -->
            <?php
            $accentClass = 'rank-1-accent';
            $badgeClass = 'badge-gold';
            $trophyClass = 'text-warning';
            $trophyIcon = 'fa-trophy';

            if ($rankNum === 2) {
                $accentClass = 'rank-2-accent';
                $badgeClass = 'badge-silver';
                $trophyClass = 'text-secondary';
                $trophyIcon = 'fa-medal';
            } elseif ($rankNum === 3) {
                $accentClass = 'rank-3-accent';
                $badgeClass = 'badge-bronze';
                $trophyClass = 'text-danger';
                $trophyIcon = 'fa-award';
            } elseif (!$isPosition) {
                $accentClass = '';
                $badgeClass = 'badge-generic';
                $trophyClass = 'text-warning';
                $trophyIcon = 'fa-crown';
            }
            ?>
            <div class="tpl-champion">
                <div class="champion-frame <?= $accentClass ?>">
                    <div class="cert-content-layer">
                        <!-- Top Banner -->
                        <div>
                            <div class="d-flex justify-content-center align-items-center gap-3 mb-1">
                                <?php if ($effectiveLogoUrl): ?>
                                    <img src="<?= $effectiveLogoUrl ?>" alt="Logo" class="cert-logo-img">
                                <?php else: ?>
                                    <div class="cert-logo-placeholder" style="background: #f59e0b; color: #0f172a;"><i class="fas <?= $trophyIcon ?>"></i></div>
                                <?php endif; ?>
                                <div>
                                    <div class="inst-heading-champ"><?= e($instituteName) ?></div>
                                    <div style="font-size: 10.5px; letter-spacing: 2px; color: #94a3b8; text-transform: uppercase;">
                                        Annual Speed Typing Grand Championship
                                    </div>
                                </div>
                            </div>

                            <div class="title-banner-champ cert-title-banner">Championship Award</div>

                            <div class="position-badge <?= $badgeClass ?> cert-pos-badge-el">
                                <i class="fas fa-star"></i> <span class="cert-pos-text-inner"><?= e($positionText) ?></span> <i class="fas fa-star"></i>
                            </div>
                        </div>

                        <!-- Candidate Presentation -->
                        <div>
                            <div class="cert-certify-label" style="color: #fbbf24;">Conferred With Highest Distinction Upon</div>
                            <div class="cert-candidate-name cert-candidate-name-el mx-auto text-light"><?= e($displayName) ?></div>

                            <div class="cert-body-statement cert-body-statement-el" style="color: #cbd5e1;">
                                <?php if ($customStatement): ?>
                                    <?= nl2br(e($customStatement)) ?>
                                <?php else: ?>
                                    In recognition of outstanding typing speed, exceptional accuracy, and exemplary victory standing in the
                                    <strong><?= e($cert['competition_name']) ?></strong> held on <?= $compDateFormatted ?>.
                                <?php endif; ?>
                            </div>

                            <!-- 4 Winner Metric Cards -->
                            <div class="champ-stats-row">
                                <div class="champ-stat-card">
                                    <div class="champ-stat-num"><?= $netWpm ?></div>
                                    <div class="champ-stat-lbl">Net Speed WPM</div>
                                </div>
                                <div class="champ-stat-card">
                                    <div class="champ-stat-num"><?= $accuracy ?>%</div>
                                    <div class="champ-stat-lbl">Typing Accuracy</div>
                                </div>
                                <div class="champ-stat-card">
                                    <div class="champ-stat-num"><?= $score ?></div>
                                    <div class="champ-stat-lbl">Final Score</div>
                                </div>
                                <div class="champ-stat-card" style="border-color: #fbbf24;">
                                    <div class="champ-stat-num text-warning cert-pos-highlight"><?= e($positionText) ?></div>
                                    <div class="champ-stat-lbl">Official Standing</div>
                                </div>
                            </div>
                        </div>

                        <!-- Signatures & Footer -->
                        <div>
                            <div class="cert-signature-row">
                                <div class="cert-sig-item">
                                    <div class="cert-sig-img-wrap">
                                        <?php if ($sig1ImageUrl): ?>
                                            <img src="<?= $sig1ImageUrl ?>" alt="Signature" class="cert-sig-img">
                                        <?php endif; ?>
                                    </div>
                                    <div class="cert-sig-line" style="border-top-color: #94a3b8;"></div>
                                    <div class="cert-sig-title cert-sig1-title-el" style="color: #cbd5e1;"><?= e($sig1Title) ?></div>
                                    <div class="cert-sig-name cert-sig1-name-el" style="color: #94a3b8;"><?= e($sig1Name) ?></div>
                                </div>

                                <div class="cert-seal-center">
                                    <div style="color: #fbbf24; font-size: 16px; letter-spacing: 4px;">
                                        ★ ★ ★ ★ ★
                                    </div>
                                </div>

                                <div class="cert-sig-item">
                                    <div class="cert-sig-img-wrap">
                                        <?php if ($sig2ImageUrl): ?>
                                            <img src="<?= $sig2ImageUrl ?>" alt="Signature" class="cert-sig-img">
                                        <?php endif; ?>
                                    </div>
                                    <div class="cert-sig-line" style="border-top-color: #94a3b8;"></div>
                                    <div class="cert-sig-title cert-sig2-title-el" style="color: #cbd5e1;"><?= e($sig2Title) ?></div>
                                    <div class="cert-sig-name cert-sig2-name-el" style="color: #94a3b8;"><?= e($sig2Name) ?></div>
                                </div>
                            </div>

                            <div class="cert-footer-row" style="border-top-color: #334155; color: #94a3b8;">
                                <div><strong>Date of Issue:</strong> <?= $issueDateFormatted ?></div>
                                <div><strong>Certificate No:</strong> <span class="font-monospace text-warning"><?= e($cert['certificate_number']) ?></span></div>
                                <div><strong>Competition Date:</strong> <?= $compDateFormatted ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
/**
 * Interactive Certificate Live Editor Script
 */
function toggleCertEditor() {
    const panel = document.getElementById('certEditorPanel');
    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'block';
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        panel.style.display = 'none';
    }
}

function switchEditorTab(tabName) {
    document.querySelectorAll('.editor-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.editor-tab-content').forEach(content => content.style.display = 'none');

    const targetTab = document.getElementById('tab-' + tabName);
    if (targetTab) {
        targetTab.style.display = 'block';
    }

    event.currentTarget.classList.add('active');
}

function resetCertificateDefaults() {
    if (confirm('Are you sure you want to reset all customizations back to standard test snapshots?')) {
        const form = document.getElementById('customizationForm');
        document.getElementById('editorActionField').value = 'reset_customization';
        form.submit();
    }
}

// Client-Side Real-Time DOM Binding
document.addEventListener('DOMContentLoaded', function() {
    const inputName = document.getElementById('inputDisplayName');
    const inputPos = document.getElementById('inputPosition');
    const inputStatement = document.getElementById('inputCustomStatement');
    const inputSig1Title = document.getElementById('inputSig1Title');
    const inputSig1Name = document.getElementById('inputSig1Name');
    const inputSig2Title = document.getElementById('inputSig2Title');
    const inputSig2Name = document.getElementById('inputSig2Name');
    const rangeOpacity = document.getElementById('watermarkOpacityRange');
    const labelOpacity = document.getElementById('opacityValLabel');
    const watermarkLayer = document.getElementById('certWatermarkLayer');

    if (inputName) {
        inputName.addEventListener('input', function() {
            document.querySelectorAll('.cert-candidate-name-el').forEach(el => el.textContent = this.value);
        });
    }

    if (inputPos) {
        inputPos.addEventListener('input', function() {
            document.querySelectorAll('.cert-pos-highlight, .cert-pos-pill-el, .cert-pos-table-el, .cert-pos-text-inner').forEach(el => el.textContent = this.value);
        });
    }

    if (inputStatement) {
        inputStatement.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                document.querySelectorAll('.cert-body-statement-el').forEach(el => {
                    el.innerHTML = this.value.replace(/\n/g, '<br>');
                });
            }
        });
    }

    if (inputSig1Title) {
        inputSig1Title.addEventListener('input', function() {
            document.querySelectorAll('.cert-sig1-title-el').forEach(el => el.textContent = this.value);
        });
    }
    if (inputSig1Name) {
        inputSig1Name.addEventListener('input', function() {
            document.querySelectorAll('.cert-sig1-name-el').forEach(el => el.textContent = this.value);
        });
    }
    if (inputSig2Title) {
        inputSig2Title.addEventListener('input', function() {
            document.querySelectorAll('.cert-sig2-title-el').forEach(el => el.textContent = this.value);
        });
    }
    if (inputSig2Name) {
        inputSig2Name.addEventListener('input', function() {
            document.querySelectorAll('.cert-sig2-name-el').forEach(el => el.textContent = this.value);
        });
    }

    if (rangeOpacity && watermarkLayer) {
        rangeOpacity.addEventListener('input', function() {
            if (labelOpacity) labelOpacity.textContent = this.value;
            watermarkLayer.style.opacity = this.value;
        });
    }

    const rangeLogoSize = document.getElementById('logoSizeRange');
    const labelLogoSize = document.getElementById('logoSizeValLabel');
    if (rangeLogoSize) {
        rangeLogoSize.addEventListener('input', function() {
            const val = parseInt(this.value, 10);
            if (labelLogoSize) labelLogoSize.textContent = val + 'px';
            document.querySelectorAll('.cert-logo-img').forEach(el => {
                el.style.maxHeight = val + 'px';
                el.style.maxWidth = Math.round(val * 2.8) + 'px';
            });
            document.querySelectorAll('.cert-logo-placeholder').forEach(el => {
                el.style.width = val + 'px';
                el.style.height = val + 'px';
                el.style.fontSize = Math.round(val * 0.45) + 'px';
            });
        });
    }
});
</script>

</body>
</html>
