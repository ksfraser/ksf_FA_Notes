<?php
/**
 * ksf_FA_Notes Event Dispatching
 *
 * Dispatches CRUD lifecycle events via FA's hook_invoke_all with dual dispatch:
 * 1. Specific event: <event_name> (e.g. 'note_created')
 * 2. Broadcast event: 'ksf_crud_event' (all modules see all events)
 *
 * @package Ksfraser\Notes
 * @since 1.1.0
 */

/**
 * Dispatch a note lifecycle event.
 *
 * @param string $eventName  Specific event name (e.g. 'note_created')
 * @param int    $noteId     The affected note ID
 * @param array  $data       Additional payload data
 */
function notes_dispatch_event($eventName, $noteId, $data = [])
{
    $payload = array_merge([
        'event'       => $eventName,
        'module'      => 'notes',
        'record_type' => 'note',
        'record_id'   => $noteId,
        'timestamp'   => date('Y-m-d H:i:s'),
    ], $data);

    if (function_exists('hook_invoke_all')) {
        hook_invoke_all($eventName, $payload);
        hook_invoke_all('ksf_crud_event', $payload);
    }
}
