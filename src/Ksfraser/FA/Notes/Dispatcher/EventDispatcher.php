<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Dispatcher;

use ksfraser\FrontAccounting\Notes\Contract\EventDispatcherInterface;

/**
 * Note event dispatcher.
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
 * @since 1.0.0
 */
final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * {@inheritdoc}
     */
    public function before(string $hookName, array $data): array
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
     * {@inheritdoc}
     */
    public function after(string $hookName, int $noteId, array $data = []): void
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
}