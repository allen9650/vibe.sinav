<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Database.php';

$defaults = [
    'cert_logo'                => '',
    'cert_watermark_enabled'   => '1',
    'cert_watermark_source'    => 'logo',
    'cert_watermark_opacity'   => '0.05',
    'cert_signature_1_title'   => 'Competition Coordinator',
    'cert_signature_1_name'    => '',
    'cert_signature_1_image'   => '',
    'cert_signature_2_title'   => 'Director / Principal',
    'cert_signature_2_name'    => '',
    'cert_signature_2_image'   => '',
    'cert_signature_3_title'   => 'Examination Controller',
    'cert_signature_3_name'    => '',
    'cert_header_subtitle'     => 'Department of Information Technology & Typing Examination',
];

foreach ($defaults as $k => $v) {
    $exists = Database::fetch('SELECT id FROM system_settings WHERE setting_key = ?', [$k]);
    if (!$exists) {
        Database::insert('system_settings', ['setting_key' => $k, 'setting_value' => $v]);
        echo "Inserted setting: {$k}\n";
    } else {
        echo "Setting {$k} exists.\n";
    }
}
echo "Default cert settings ensured.\n";
