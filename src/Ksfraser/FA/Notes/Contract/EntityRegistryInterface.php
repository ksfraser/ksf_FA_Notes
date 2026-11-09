<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Contract;

use ksfraser\FrontAccounting\Common\Contract\LinkRolesContract;

/**
 * Entity registry contract - source of truth for entity type definitions.
 *
 * Each entity type defines: table, pk, label, module, picker, multiple, title,
 * options_hook (optional). Modules can extend via RELATED_ENTITY_TYPES hook.
 *
 * @since 1.0.0
 */
interface EntityRegistryInterface extends LinkRolesContract
{
    /**
     * Get the full entity type registry.
     *
     * @return array<string, array> entity_type => definition
     */
    public function all(): array;

    /**
     * Get one entity type definition.
     *
     * @param string $entity_type Registry key
     * @return array|null Definition or null when unknown
     */
    public function get(string $entity_type): ?array;

    /**
     * Get entity types that can be offered in pickers (picker => true).
     *
     * @return array<string, array>
     */
    public function pickable(): array;

    /**
     * Human labels for registered types (type => title).
     *
     * @return array<string, string>
     */
    public function typeLabels(): array;

    /**
     * Note type categories (note_type => label).
     *
     * @return array<string, string>
     */
    public function noteTypes(): array;
}