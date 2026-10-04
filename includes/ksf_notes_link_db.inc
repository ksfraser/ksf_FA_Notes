<?php
/**
 * Table gateway for 0_ksf_Notes_link.
 *
 * This is the x-link that lets one note reference many records across many
 * modules, and lets one record be referenced by many notes. A note carries no
 * foreign keys of its own; every association is a row here.
 *
 * SQL construction is split from execution so the query text can be asserted
 * in unit tests without a database.
 *
 * @BABOK Related: FR-NT-001-002
 * @since 1.0.0
 */

require_once __DIR__ . '/common.inc';
require_once __DIR__ . '/entity_types.inc';

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
 * Rejects unknown entity types and roles that are not valid for the type, so a
 * caller cannot smuggle an unlinkable reference into the table.
 *
 * @param array $data note_id, entity_type, entity_id, link_role
 * @return array|null Normalised row, or null when invalid
 * @since 1.0.0
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
 * Build the INSERT for a link row.
 *
 * @param array $link Normalised row; see notes_link_prepare()
 * @return string
 * @since 1.0.0
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
 * @param array $criteria note_id, entity_type, entity_id
 * @return string
 * @since 1.0.0
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

/**
 * Insert a link row.
 *
 * Relies on the uq_note_entity unique key to make repeated submissions of the
 * same reference a no-op rather than a duplicate-row error.
 *
 * @param array $data See notes_link_prepare()
 * @return int New link id, or 0 when the row was invalid or already present
 * @since 1.0.0
 */
function notes_link_add(array $data)
{
    $link = notes_link_prepare($data);

    if ($link === null) {
        return 0;
    }

    $existing = notes_link_find($link['note_id'], $link['entity_type'], $link['entity_id'], $link['link_role']);
    if ($existing !== null) {
        return 0;
    }

    db_query(notes_link_sql_insert($link), 'Could not link note to record');

    return (int) db_insert_id();
}

/**
 * Find one specific link row.
 *
 * @param int    $note_id     Note id
 * @param string $entity_type Registry key
 * @param string $entity_id   Referenced pk
 * @param string $link_role   Role
 * @return array|null
 * @since 1.0.0
 */
function notes_link_find($note_id, $entity_type, $entity_id, $link_role = null)
{
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
 * Fetch the links of one note.
 *
 * @param int $note_id Note id
 * @return array List of link rows
 * @since 1.0.0
 */
function notes_link_get_by_note($note_id)
{
    $result = db_query(
        notes_link_sql_select(array('note_id' => $note_id)),
        'Could not get note links'
    );

    $rows = array();
    while ($row = db_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Fetch every link pointing at one referenced record.
 *
 * @param string $entity_type Registry key
 * @param string $entity_id   Referenced pk
 * @return array List of link rows
 * @since 1.0.0
 */
function notes_link_get_by_entity($entity_type, $entity_id)
{
    $result = db_query(
        notes_link_sql_select(array('entity_type' => $entity_type, 'entity_id' => $entity_id)),
        'Could not get record notes'
    );

    $rows = array();
    while ($row = db_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Remove one link.
 *
 * @param int    $note_id     Note id
 * @param string $entity_type Registry key
 * @param string $entity_id   Referenced pk
 * @param string $link_role   Role; null matches any role for that reference
 * @return bool
 * @since 1.0.0
 */
function notes_link_delete($note_id, $entity_type, $entity_id, $link_role = null)
{
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
 * A note can reference tables owned by modules that are not installed, so an
 * unresolvable reference is returned with a null label rather than being
 * dropped or raising an error.
 *
 * @param array $links List of link rows
 * @return array Rows with label, label2 and known (bool) added
 * @since 1.0.0
 */
function notes_link_resolve(array $links)
{
    $resolved = array();

    foreach ($links as $link) {
        $def = notes_entity_type($link['entity_type']);
        $link['label'] = null;
        $link['label2'] = null;
        $link['known'] = false;

        if ($def !== null) {
            $sql = 'SELECT `' . $def['label'] . '` AS label FROM ' . TB_PREF . $def['table']
                . ' WHERE `' . $def['pk'] . '` = ' . notes_db_escape($link['entity_id']);

            $result = db_query($sql);
            if ($result !== false && db_num_rows($result) > 0) {
                $row = db_fetch_assoc($result);
                if ($row !== false) {
                    $link['label'] = $row['label'];
                    $link['known'] = true;
                }
            }
        }

        $resolved[] = $link;
    }

    return $resolved;
}