<?php
$path_to_root = '../../../';
$page_security = 'SA_OPEN';
require_once($path_to_root . '/includes/session.inc');
require_once($path_to_root . '/includes/access_levels.inc');
add_access_extensions();

$result = isset($GLOBALS['Hooks']['ksf_FA_Square']) ? 'yes' : 'no';
file_put_contents($path_to_root . '/tmp/hook_check_result.txt', $result);
echo "Result: $result";
