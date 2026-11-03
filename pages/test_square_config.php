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
$sql = "SELECT name, value FROM 0_square WHERE name IN ('access_token', 'sandbox_access_token', 'environment', 'location_id')";
$result = db_query($sql);
echo "Result: ";
var_dump($result);

$config = [];
if ($result) {
    while ($row = db_fetch($result)) {
        $config[$row['name']] = $row['value'];
    }
}
echo "Config: ";
print_r($config);
