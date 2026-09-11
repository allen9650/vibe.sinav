<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    echo "MySQL 127.0.0.1 connection: OK\n";
    $pdo->exec("SELECT 1");
    echo "MySQL query: OK\n";
} catch (Exception $e) {
    echo "FAIL 127.0.0.1: " . $e->getMessage() . "\n";
}

try {
    $pdo2 = new PDO('mysql:host=localhost;port=3306', 'root', '');
    echo "MySQL localhost connection: OK\n";
} catch (Exception $e) {
    echo "FAIL localhost: " . $e->getMessage() . "\n";
}

