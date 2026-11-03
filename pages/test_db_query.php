<?php
$path_to_root = '../../../';
$page_security = 'SA_SALESINVOICE';
require_once($path_to_root . '/includes/session.inc');
require_once($path_to_root . '/includes/access_levels.inc');
add_access_extensions();

// Login
$user = new current_user();
$user->login(0, 'admin', 'admin');

// Test the query directly
require_once('/var/www/html/modules/ksf_FA_Square/hooks.php');

$tablePrefix = defined('TB_PREF') ? TB_PREF : '0_';
$sql = "SELECT name, value FROM " . $tablePrefix . "0_square WHERE name IN ('access_token', 'sandbox_access_token', 'environment', 'location_id')";
echo "SQL: $sql\n";

$result = db_query($sql);
echo "Result: ";
var_dump($result);

if ($result) {
    while ($row = db_fetch($result)) {
        echo "Row: ";
        print_r($row);
    }
}
