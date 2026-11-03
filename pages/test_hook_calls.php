<?php
$path_to_root = '../../../';
$page_security = 'SA_SALESINVOICE';
require_once($path_to_root . '/includes/session.inc');
require_once($path_to_root . '/includes/access_levels.inc');
add_access_extensions();

// Login
$user = new current_user();
$user->login(0, 'admin', 'admin');

// Check if hooks are registered
$hooks = $GLOBALS['Hooks']['ksf_FA_Square'] ?? null;
if ($hooks) {
    echo "Square hooks loaded\n";
    
    // Test db_prewrite directly
    $cart = new stdClass();
    $cart->customer_id = 1;
    $cart->Branch = '1';
    $cart->payment_terms = ['terms_indicator' => 5]; // Square Invoice term
    $cart->line_items = [
        ['stock_id' => 'TEST001', 'item_description' => 'Test Item', 'quantity' => 1, 'price' => 10.00, 'tax_type_id' => 0]
    ];
    
    $result = $hooks->db_prewrite($cart, ST_SALESINVOICE);
    echo "db_prewrite result: " . ($result ? 'true' : 'false/null') . "\n";
    
    // Check pending
    $reflection = new ReflectionClass($hooks);
    $prop = $reflection->getProperty('pendingSquareInvoice');
    $prop->setAccessible(true);
    $pending = $prop->getValue($hooks);
    echo "Pending Square Invoice: ";
    print_r($pending);
} else {
    echo "Square hooks NOT loaded\n";
}
