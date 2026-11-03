<?php
$path_to_root = '../../../';
$page_security = 'SA_NOTES_VIEW';
require_once($path_to_root . '/includes/session.inc');
require_once($path_to_root . '/includes/access_levels.inc');
add_access_extensions();

// Login
$user = new current_user();
$user->login(0, 'admin', 'admin');

// Test sqLog directly
require_once('/var/www/html/modules/ksf_FA_Square/hooks.php');
$hooks = new hooks_ksf_FA_Square();
$reflection = new ReflectionClass($hooks);
$method = $reflection->getMethod('sqLog');
$method->setAccessible(true);
$method->invoke($hooks, 'info', 'Test sqLog call', ['test' => 'data']);
echo "sqLog called\n";
