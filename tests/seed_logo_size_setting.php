<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Database.php';

$exists = Database::fetch("SELECT id FROM system_settings WHERE setting_key = 'cert_logo_size'");
if (!$exists) {
    Database::insert('system_settings', ['setting_key' => 'cert_logo_size', 'setting_value' => '52']);
    echo "Inserted cert_logo_size\n";
} else {
    echo "cert_logo_size exists\n";
}
