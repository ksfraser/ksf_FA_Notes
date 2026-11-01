<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Contract;

/**
 * Event dispatcher contract for note lifecycle hooks.
 *
 * Uses generic SuiteCRM-style hook names (before_save, after_save, etc.)
 * with entity_type and action in the payload.
 *
 * @since 1.0.0
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch a before_* hook (filter pattern via hook_invoke_first).
     *
     * @param string $hookName e.g. 'before_save'
     * @param array  $data     Data to be modified/validated (includes entity_type, action)
     * @return array Modified data
     */
    public function before(string $hookName, array $data): array;

    /**
     * Dispatch an after_* hook (notification via hook_invoke_all).
     *
     * @param string $hookName e.g. 'after_save'
     * @param int    $noteId
     * @param array  $data     Additional payload (includes entity_type, action)
     */
    public function after(string $hookName, int $noteId, array $data = []): void;
}