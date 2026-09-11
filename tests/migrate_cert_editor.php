<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Database.php';

$cols = Database::fetchAll('DESCRIBE certificates');
$colNames = array_column($cols, 'Field');

$newCols = [
    'display_name'       => 'VARCHAR(150) NULL AFTER position',
    'custom_statement'   => 'TEXT NULL AFTER display_name',
    'signatory_1_title'  => 'VARCHAR(100) NULL AFTER custom_statement',
    'signatory_1_name'   => 'VARCHAR(100) NULL AFTER signatory_1_title',
    'signatory_2_title'  => 'VARCHAR(100) NULL AFTER signatory_1_name',
    'signatory_2_name'   => 'VARCHAR(100) NULL AFTER signatory_2_title',
];

foreach ($newCols as $col => $def) {
    if (!in_array($col, $colNames)) {
        Database::getInstance()->exec("ALTER TABLE certificates ADD COLUMN `{$col}` {$def}");
        echo "Added column: {$col}\n";
    } else {
        echo "Column {$col} already exists.\n";
    }
}
echo "Schema check completed.\n";
