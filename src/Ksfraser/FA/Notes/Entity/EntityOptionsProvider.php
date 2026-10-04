<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Entity;

/**
 * Resolves the option list for one entity type.
 *
 * Notes deliberately does not query another module's tables to build a
 * dropdown. The module that owns a record knows how to list its own records —
 * including its own soft-delete rules, access scoping and joins — so each
 * module answers a GET_<TYPE>_OPTIONS responder and this class collects the
 * answers.
 *
 * Resolution order:
 *   1. Ask every active extension: hook_invoke_all('GET_<TYPE>_OPTIONS').
 *   2. Fall back to a direct read of the type's registered table, which covers
 *      native FrontAccounting tables (debtors_master, crm_persons,
 *      attachments) that belong to no module and so have no responder.
 *
 * The fallback is a safety net, not the design: it keeps a picker usable when
 * the owning module is not installed or has not published a responder.
 *
 * @BABOK Related: FR-NT-001-002
 * @since 1.0.0
 */
class EntityOptionsProvider
{
    /**
     * @var array<string, array> Entity registry, entity_type => definition
     */
    private $registry;

    /**
     * @var array<string, array> Per-request memo, entity_type => options
     */
    private $cache = array();

    /**
     * @var array<string, array<string, string>> Pre-seeded options, entity_type => pk => label
     */
    private $overrides = array();

    /**
     * @param array<string, array>            $registry Entity registry
     * @param array<string, array<string, string>> $options Pre-seeded options per entity type.
     *        These win over responders and tables, which is what lets a caller
     *        that already has the rows pass them in, and lets the views be tested
     *        without a database.
     */
    public function __construct(array $registry, array $options = array())
    {
        $this->registry = $registry;
        $this->overrides = $options;
    }

    /**
     * The hook name that answers options for a type.
     *
     * A registry entry may name the hook explicitly with 'options_hook',
     * which is how a module and Notes agree on the seam. Otherwise the name is
     * derived in the camelCase form the existing KSF services already use
     * (getCustomerTypes, getTerritories, ...), so a module exposing that
     * pattern is answerable without being modified.
     *
     * @param string $entity_type Registry key
     * @return string Empty string when the type is not registered
     * @since 1.0.0
     */
    public function hookNameFor(string $entity_type): string
    {
        if (isset($this->registry[$entity_type]['options_hook'])) {
            return (string) $this->registry[$entity_type]['options_hook'];
        }

        if (!isset($this->registry[$entity_type])) {
            return '';
        }

        return 'get' . ucfirst($entity_type) . 'Options';
    }

    /**
     * Options for one entity type, as pk => label.
     *
     * @param string $entity_type Registry key
     * @return array<string, string>
     * @since 1.0.0
     */
    public function optionsFor(string $entity_type): array
    {
        if (isset($this->cache[$entity_type])) {
            return $this->cache[$entity_type];
        }

        if (!isset($this->registry[$entity_type])) {
            return $this->cache[$entity_type] = array();
        }

        if (isset($this->overrides[$entity_type]) && $this->overrides[$entity_type]) {
            return $this->cache[$entity_type] = $this->overrides[$entity_type];
        }

        $options = $this->fromResponders($entity_type);

        if (!$options) {
            $options = $this->fromTable($entity_type);
        }

        return $this->cache[$entity_type] = $options;
    }

    /**
     * Options for every type, keyed by type.
     *
     * @return array<string, array<string, string>>
     * @since 1.0.0
     */
    public function all(): array
    {
        $all = array();

        foreach (array_keys($this->registry) as $entity_type) {
            $all[$entity_type] = $this->optionsFor($entity_type);
        }

        return $all;
    }

    /**
     * Types that can actually be offered a picker right now.
     *
     * A type is excluded when its registry entry opts out, or when neither a
     * responder nor its table can supply a single row.
     *
     * @return string[]
     * @since 1.0.0
     */
    public function pickableTypes(): array
    {
        $types = array();

        foreach ($this->registry as $entity_type => $def) {
            if (empty($def['picker'])) {
                continue;
            }
            if ($this->optionsFor($entity_type)) {
                $types[] = $entity_type;
            }
        }

        return $types;
    }

    /**
     * Human labels for the registered types, from each entry's 'label' key.
     *
     * @return array<string, string>
     * @since 1.0.0
     */
    public function typeLabels(): array
    {
        $labels = array();

        foreach ($this->registry as $entity_type => $def) {
            $labels[$entity_type] = (string) ($def['title'] ?? $def['label'] ?? $entity_type);
        }

        return $labels;
    }

    /**
     * Resolve a pk to its label, for displaying an already-saved link.
     *
     * @param string $entity_type Registry key
     * @param string $entity_id   Primary key
     * @return string Empty string when the record cannot be resolved
     * @since 1.0.0
     */
    public function labelFor(string $entity_type, string $entity_id)
    {
        $options = $this->optionsFor($entity_type);

        // An empty string, not null: callers render this straight into HTML and
        // a null here is a silent type error at the far end.
        return (string) ($options[$entity_id] ?? '');
    }

    /**
     * Ask every active extension to supply the options.
     *
     * @param string $entity_type Registry key
     * @return array<string, string>
     * @since 1.0.0
     */
    private function fromResponders(string $entity_type): array
    {
        if (!function_exists('hook_invoke_all')) {
            return array();
        }

        $payload = array(
            'entity_type' => $entity_type,
            'options' => array(),
        );

        hook_invoke_all($this->hookNameFor($entity_type), $payload);

        if (!isset($payload['options']) || !is_array($payload['options'])) {
            return array();
        }

        $options = array();

        foreach ($payload['options'] as $value => $label) {
            $options[(string) $value] = (string) $label;
        }

        return $options;
    }

    /**
     * Read the type's registered table directly.
     *
     * Only reached for native FrontAccounting tables, or when the owning module
     * is absent. Skips cleanly when the table is not installed.
     *
     * @param string $entity_type Registry key
     * @return array<string, string>
     * @since 1.0.0
     */
    private function fromTable(string $entity_type): array
    {
        $def = $this->registry[$entity_type];

        if (!function_exists('db_query') || !defined('TB_PREF')) {
            return array();
        }

        if (!$this->tableExists($def['table'])) {
            return array();
        }

        $sql = 'SELECT `' . $def['pk'] . '` AS pk, `' . $def['label'] . '` AS label FROM '
            . TB_PREF . $def['table'] . ' ORDER BY `label`';

        $result = db_query($sql);

        if ($result === false || !function_exists('db_fetch_assoc')) {
            return array();
        }

        $options = array();

        while ($row = db_fetch_assoc($result)) {
            if ($row['label'] === null || $row['label'] === '') {
                continue;
            }
            $options[(string) $row['pk']] = (string) $row['label'];
        }

        return $options;
    }

    /**
     * @param string $table Table name without the company prefix
     * @return bool
     * @since 1.0.0
     */
    private function tableExists(string $table): bool
    {
        if (function_exists('check_table')) {
            return (bool) check_table($table, TB_PREF);
        }

        $result = db_query("SHOW TABLES LIKE '" . TB_PREF . $table . "'");

        return $result !== false && db_num_rows($result) > 0;
    }
}