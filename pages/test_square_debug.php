<?php
$path_to_root = '../../../';
$page_security = 'SA_SALESINVOICE';
require_once($path_to_root . '/includes/session.inc');
require_once($path_to_root . '/includes/access_levels.inc');
add_access_extensions();

// Login
$user = new current_user();
$user->login(0, 'admin', 'admin');

// Test the full Square flow with debug
require_once('/var/www/html/modules/ksf_FA_Square/hooks.php');
$hooks = new hooks_ksf_FA_Square();

// Simulate cart with Square payment terms
$cart = new stdClass();
$cart->customer_id = 1;
$cart->Branch = '1';
$cart->payment_terms = ['terms_indicator' => 5]; // Square Invoice term
$cart->line_items = [
    ['stock_id' => 'TEST001', 'item_description' => 'Test Item', 'quantity' => 1, 'price' => 10.00, 'tax_type_id' => 0]
];
$cart->trans_no = 999; // Simulate invoice number

echo "Testing db_prewrite...\n";
$result = $hooks->db_prewrite($cart, ST_SALESINVOICE);
echo "db_prewrite result: " . ($result ? 'true' : 'false/null') . "\n";

// Check pending
$reflection = new ReflectionClass($hooks);
$prop = $reflection->getProperty('pendingSquareInvoice');
$prop->setAccessible(true);
$pending = $prop->getValue($hooks);
echo "Pending Square Invoice after prewrite: ";
print_r($pending);

echo "\nTesting db_postwrite with logging...\n";
// Add logging to db_postwrite by calling it directly
$hooks->db_postwrite($cart, ST_SALESINVOICE);
echo "db_postwrite completed\n";
