<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Database.php';

$defaults = [
    'cert_signature_1_title' => 'Competition Coordinator',
    'cert_signature_1_name'  => '',
    'cert_signature_2_title' => 'Director / Principal',
    'cert_signature_2_name'  => '',
    'cert_signature_3_title' => 'Examination Controller',
    'cert_signature_3_name'  => '',
];

foreach ($defaults as $k => $v) {
    $exists = Database::fetch('SELECT id FROM system_settings WHERE setting_key = ?', [$k]);
    if (!$exists) {
        Database::insert('system_settings', ['setting_key' => $k, 'setting_value' => $v]);
        echo "Inserted {$k}\n";
    }
}
echo "Done.\n";
