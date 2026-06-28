<?php
/**
 * ksf_FA_Notes Event Dispatching
 *
 * Dispatches CRUD lifecycle events via FA's hook_invoke with dual dispatch:
 * - before_* hooks use hook_invoke_first (filter: can modify/validate/abort)
 * - after_* hooks use hook_invoke_all (notification only)
 * - ksf_crud_event broadcast for Workflow integration
 *
 * Uses generic SuiteCRM-style hook names (before_save, after_save, etc.)
 * with entity_type and action in the payload.
 *
 * @package Ksfraser\Notes
 * @since 1.1.0
 */

/**
 * Dispatch a before_* hook (WordPress filter pattern via hook_invoke_first).
 *
 * @param string $hookName  e.g. 'before_save'
 * @param array  $data      Data to be modified/validated (includes entity_type, action)
 * @return array            Modified data
 */
function notes_before_hook($hookName, $data)
{
    if (function_exists('hook_invoke_first')) {
        $result = hook_invoke_first($hookName, $data);
        if (is_array($result)) {
            return $result;
        }
    }
    return $data;
}

/**
 * Dispatch an after_* hook (notification via hook_invoke_all).
 *
 * @param string $hookName  e.g. 'after_save'
 * @param int    $noteId
 * @param array  $data      Additional payload (includes entity_type, action)
 */
function notes_after_hook($hookName, $noteId, $data = [])
{
    $payload = array_merge([
        'event'       => $hookName,
        'module'      => 'notes',
        'entity_type' => 'note',
        'entity_id'   => $noteId,
        'timestamp'   => date('Y-m-d H:i:s'),
    ], $data);

    if (function_exists('hook_invoke_all')) {
        hook_invoke_all($hookName, $payload);
        hook_invoke_all('ksf_crud_event', $payload);
    }
}

/**
 * Legacy wrapper — delegates to notes_after_hook().
 * Kept for backward compatibility with code calling notes_dispatch_event() directly.
 */
if (!function_exists('notes_dispatch_event')) {
    function notes_dispatch_event($eventName, $noteId, $data = []) {
        notes_after_hook($eventName, $noteId, $data);
    }
}
