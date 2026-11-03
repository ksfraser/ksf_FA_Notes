<?php
require '../../includes/session.inc';
require '../../includes/access_levels.inc';
add_access_extensions();

// Login
$user = new current_user();
$user->login(0, 'admin', 'admin');

// Test CREATE_NOTE with links via hook_invoke
$noteData = array(
    'note' => 'Test note with links via hook',
    'subject' => 'Test Subject',
    'note_type' => 'square_invoice',
    'owner' => 'admin',
);
$noteOpts = array(
    'links' => array(
        array('customer', '1', 'square_invoice'),
        array('invoice', '1', 'source_invoice'),
    ),
);
$result = hook_invoke('ksf_FA_Notes', 'CREATE_NOTE', $noteData, $noteOpts);
echo 'Result: ' . ($result === false ? 'false' : 'true') . PHP_EOL;
if (is_array($noteData)) {
    echo 'Note ID: ' . ($noteData['note_id'] ?? 'none') . PHP_EOL;
    echo 'Links added: ' . ($noteData['links_added'] ?? 'none') . PHP_EOL;
    echo 'Error: ' . ($noteData['error'] ?? 'none') . PHP_EOL;
}
