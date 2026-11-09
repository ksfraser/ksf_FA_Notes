<?php
/**
 * Table gateway for 0_ksf_Notes_link (deprecated).
 *
 * This gateway now delegates all link operations to the ksf_FA_RelatedTo
 * module's hooks (RELATE_RECORDS / RELATED_TO) and master table
 * 0_ksf_RelatedTo. The old 0_ksf_Notes_link table is retained for backward
 * compatibility but is no longer written to or read from.
 *
 * A note carries no foreign keys of its own; every association is a row in
 * 0_ksf_RelatedTo as (lhs_type='note', lhs_id=note_id,
 * rhs_type=<entity>, rhs_id=<entity_id>, link_role=<role>).
 *
 * @BABOK Related: FR-NT-001-002
 * @since 1.0.0
 * @deprecated Use ksf_FA_RelatedTo hooks (RELATE_RECORDS / RELATED_TO) instead.
 */

require_once __DIR__ . '/common.inc';
require_once __DIR__ . '/entity_types.inc';

use ksfraser\RelatedTo\DTO\RelatedQueryDto;
use ksfraser\RelatedTo\DTO\RelatedRecordDto;

/**
 * Roles accepted when the entity type has no declared roles.
 *
 * @return array<string, string>
 * @since 1.0.0
 */
function notes_default_link_roles()
{
    return array('reference' => 'Reference');
}

/**
 * Normalise and validate a link row.
 *
 * @param array $data note_id, entity_type, entity_id, link_role
 * @return array|null Normalised row, or null when invalid
 * @since 1.0.0
 * @deprecated
 */
function notes_link_prepare(array $data)
{
    if (empty($data['note_id']) || empty($data['entity_type']) || !isset($data['entity_id'])) {
        return null;
    }

    $entity_id = trim((string) $data['entity_id']);
    if ($entity_id === '') {
        return null;
    }

    if (notes_entity_type($data['entity_type']) === null) {
        return null;
    }

    $roles = notes_link_roles_for($data['entity_type']);
    if (!$roles) {
        $roles = notes_default_link_roles();
    }

    $role = isset($data['link_role']) && $data['link_role'] !== ''
        ? $data['link_role']
        : key($roles);

    if (!isset($roles[$role])) {
        return null;
    }

    return array(
        'note_id' => (int) $data['note_id'],
        'entity_type' => $data['entity_type'],
        'entity_id' => $entity_id,
        'link_role' => $role,
    );
}

/**
 * Associate a note with a record via the RelatedTo RELATE_RECORDS hook.
 *
 * Falls back to direct table access if RelatedTo is not active.
 *
 * @param array $data note_id, entity_type, entity_id, link_role
 * @return int New link id (1 on success, 0 when already present or failed)
 * @since 1.0.0
 * @deprecated
 */
function notes_link_add(array $data)
{
    $link = notes_link_prepare($data);

    if ($link === null) {
        return 0;
    }

    // Try RelatedTo hook first (preferred path).
    if (function_exists('hook_invoke')) {
        $dto = new RelatedRecordDto(
            'note',
            $link['note_id'],
            $link['entity_type'],
            $link['entity_id'],
            $link['link_role']
        );
        $result = $dto;
        hook_invoke('ksf_FA_RelatedTo', 'RELATE_RECORDS', $result, null);
        if (is_array($result) && isset($result['ok']) && $result['ok'] === true) {
            return 1;
        }
    }

    // Fallback: direct table access (RelatedTo not active).
    $existing = notes_link_find($link['note_id'], $link['entity_type'], $link['entity_id'], $link['link_role']);
    if ($existing !== null) {
        return 0;
    }

    db_query(
        'INSERT INTO ' . TB_PREF . 'ksf_Notes_link'
        . ' (`note_id`, `entity_type`, `entity_id`, `link_role`) VALUES ('
        . notes_db_escape($link['note_id']) . ', '
        . notes_db_escape($link['entity_type']) . ', '
        . notes_db_escape($link['entity_id']) . ', '
        . notes_db_escape($link['link_role']) . ')',
        'Could not link note to record'
    );

    return (int) db_insert_id();
}

/**
 * Find one specific link row via the RelatedTo RELATED_TO hook.
 *
 * @param int    $note_id     Note id
 * @param string $entity_type Registry key
 * @param string $entity_id   Referenced pk
 * @param string $link_role   Role
 * @return array|null
 * @since 1.0.0
 * @deprecated
 */
function notes_link_find($note_id, $entity_type, $entity_id, $link_role = null)
{
    if (function_exists('hook_invoke')) {
        $query = new RelatedQueryDto('note', $note_id);
        $result = $query;
        hook_invoke('ksf_FA_RelatedTo', 'RELATED_TO', $result, null);
        if (is_array($result) && isset($result['related']) && is_array($result['related'])) {
            foreach ($result['related'] as $rel) {
                if (!$rel instanceof RelatedRecordDto) {
                    continue;
                }
                if ($rel->rhsType !== $entity_type || (string) $rel->rhsId !== (string) $entity_id) {
                    continue;
                }
                if ($link_role !== null && $rel->linkRole !== $link_role) {
                    continue;
                }
                return array(
                    'id' => 0,
                    'note_id' => $rel->lhsId,
                    'entity_type' => $rel->rhsType,
                    'entity_id' => $rel->rhsId,
                    'link_role' => $rel->linkRole,
                );
            }
        }
    }

    // Fallback: direct table access.
    $criteria = array(
        'note_id' => $note_id,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
    );

    $sql = notes_link_sql_select($criteria);

    if ($link_role !== null) {
        $sql .= ' AND `link_role` = ' . notes_db_escape($link_role);
    }

    $result = db_query($sql, 'Could not get note link');
    $row = db_fetch_assoc($result);

    return $row === false ? null : $row;
}

/**
 * Fetch the links of one note via the RelatedTo RELATED_TO hook.
 *
 * @param int $note_id Note id
 * @return array List of link rows (legacy shape)
 * @since 1.0.0
 * @deprecated
 */
function notes_link_get_by_note($note_id)
{
    $links = array();

    if (function_exists('hook_invoke')) {
        $query = new RelatedQueryDto('note', $note_id);
        $result = $query;
        hook_invoke('ksf_FA_RelatedTo', 'RELATED_TO', $result, null);
        if (is_array($result) && isset($result['related']) && is_array($result['related'])) {
            foreach ($result['related'] as $rel) {
                if (!$rel instanceof RelatedRecordDto) {
                    continue;
                }
                if ($rel->lhsType !== 'note' || (string) $rel->lhsId !== (string) $note_id) {
                    continue;
                }
                $links[] = array(
                    'id' => 0,
                    'note_id' => $rel->lhsId,
                    'entity_type' => $rel->rhsType,
                    'entity_id' => $rel->rhsId,
                    'link_role' => $rel->linkRole,
                );
            }
        }
    }

    if (empty($links)) {
        // Fallback: direct table access.
        $result = db_query(
            notes_link_sql_select(array('note_id' => $note_id)),
            'Could not get note links'
        );

        while ($row = db_fetch_assoc($result)) {
            $links[] = $row;
        }
    }

    return $links;
}

/**
 * Fetch every link pointing at one referenced record via RelatedTo relatedBy().
 *
 * @param string $entity_type Registry key
 * @param string $entity_id   Referenced pk
 * @return array List of link rows
 * @since 1.0.0
 * @deprecated
 */
function notes_link_get_by_entity($entity_type, $entity_id)
{
    $links = array();

    // Direct table access (RelatedTo doesn't expose reverse lookup via hook yet).
    $result = db_query(
        notes_link_sql_select(array('entity_type' => $entity_type, 'entity_id' => $entity_id)),
        'Could not get record notes'
    );

    while ($row = db_fetch_assoc($result)) {
        $links[] = $row;
    }

    return $links;
}

/**
 * Remove one link via RelatedTo repository unrelate().
 *
 * @param int    $note_id     Note id
 * @param string $entity_type Registry key
 * @param string $entity_id   Referenced pk
 * @param string $link_role   Role; null matches any role for that reference
 * @return bool
 * @since 1.0.0
 * @deprecated
 */
function notes_link_delete($note_id, $entity_type, $entity_id, $link_role = null)
{
    // Try RelatedTo hook first (preferred path).
    if (function_exists('hook_invoke')) {
        $dto = new \ksfraser\RelatedTo\DTO\RelatedRecordDto(
            'note',
            $note_id,
            $entity_type,
            $entity_id,
            $link_role
        );
        $result = $dto;
        hook_invoke('ksf_FA_RelatedTo', 'UNRELATE_RECORDS', $result, null);
        if (is_array($result) && isset($result['ok']) && $result['ok'] === true) {
            return true;
        }
    }

    // Try RelatedTo repository directly (RelatedTo active but no hook listener).
    $repoClass = '\\ksfraser\\FrontAccounting\\RelatedTo\\Repository\\RelatedRecordRepository';
    if (class_exists($repoClass)) {
        $repo = new $repoClass();
        $repo->unrelate('note', $note_id, $entity_type, $entity_id, $link_role);
        return true;
    }

    // Fallback: direct table access (RelatedTo not installed).
    $sql = 'DELETE FROM ' . TB_PREF . 'ksf_Notes_link WHERE `note_id` = '
        . notes_db_escape((int) $note_id)
        . ' AND `entity_type` = ' . notes_db_escape($entity_type)
        . ' AND `entity_id` = ' . notes_db_escape((string) $entity_id);

    if ($link_role !== null) {
        $sql .= ' AND `link_role` = ' . notes_db_escape($link_role);
    }

    db_query($sql, 'Could not unlink note from record');

    return true;
}

/**
 * Resolve link rows into readable labels by looking each reference up in its
 * own table.
 *
 * Accepts both legacy link rows (from direct table access) and
 * RelatedRecordDto objects (from RelatedTo hook responses).
 *
 * @param array $links List of link rows or RelatedRecordDto objects
 * @return array Rows with label, label2 and known (bool) added
 * @since 1.0.0
 * @deprecated
 */
function notes_link_resolve(array $links)
{
    $resolved = array();

    foreach ($links as $link) {
        $resolvedLink = array(
            'id' => 0,
            'label' => null,
            'label2' => null,
            'known' => false,
        );

        if ($link instanceof RelatedRecordDto) {
            $resolvedLink['entity_type'] = $link->rhsType;
            $resolvedLink['entity_id'] = $link->rhsId;
            $resolvedLink['link_role'] = $link->linkRole;
        } else {
            $resolvedLink['entity_type'] = $link['entity_type'] ?? '';
            $resolvedLink['entity_id'] = $link['entity_id'] ?? '';
            $resolvedLink['link_role'] = $link['link_role'] ?? '';
        }

        $def = notes_entity_type($resolvedLink['entity_type']);

        if ($def !== null) {
            $sql = 'SELECT `' . $def['label'] . '` AS label FROM ' . TB_PREF . $def['table']
                . ' WHERE `' . $def['pk'] . '` = ' . notes_db_escape($resolvedLink['entity_id']);

            $result = db_query($sql);
            if ($result !== false && db_num_rows($result) > 0) {
                $row = db_fetch_assoc($result);
                if ($row !== false) {
                    $resolvedLink['label'] = $row['label'];
                    $resolvedLink['known'] = true;
                }
            }
        }

        $resolved[] = $resolvedLink;
    }

    return $resolved;
}

/**
 * Build the INSERT for a link row.
 *
 * @param array $link Normalised row; see notes_link_prepare()
 * @return string
 * @since 1.0.0
 * @deprecated
 */
function notes_link_sql_insert(array $link)
{
    return 'INSERT INTO ' . TB_PREF . 'ksf_Notes_link'
        . ' (`note_id`, `entity_type`, `entity_id`, `link_role`) VALUES ('
        . notes_db_escape($link['note_id']) . ', '
        . notes_db_escape($link['entity_type']) . ', '
        . notes_db_escape($link['entity_id']) . ', '
        . notes_db_escape($link['link_role']) . ')';
}

/**
 * Build a SELECT for links, filtered by note and/or referenced record.
 *
 * Kept for backward compatibility; direct table access only.
 *
 * @param array $criteria note_id, entity_type, entity_id
 * @return string
 * @since 1.0.0
 * @deprecated
 */
function notes_link_sql_select(array $criteria = array())
{
    $where = array();

    if (isset($criteria['note_id'])) {
        $where[] = '`note_id` = ' . notes_db_escape((int) $criteria['note_id']);
    }

    if (isset($criteria['entity_type']) && $criteria['entity_type'] !== '') {
        $where[] = '`entity_type` = ' . notes_db_escape($criteria['entity_type']);
    }

    if (isset($criteria['entity_id']) && $criteria['entity_id'] !== '') {
        $where[] = '`entity_id` = ' . notes_db_escape((string) $criteria['entity_id']);
    }

    $sql = 'SELECT * FROM ' . TB_PREF . 'ksf_Notes_link';

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    return $sql . ' ORDER BY `entity_type`, `link_role`, `id`';
}
