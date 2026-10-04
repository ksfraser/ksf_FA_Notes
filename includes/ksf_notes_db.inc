<?php
/**
 * Table gateway for 0_ksf_Notes.
 *
 * Follows the module convention of one {table}_db.inc per table exposing
 * write_/get_/delete_. SQL construction is split from execution into pure
 * notes_sql_*() builders so the query text can be asserted in unit tests
 * without a database.
 *
 * @BABOK Related: FR-NT-001-001
 * @since 1.0.0
 */

require_once __DIR__ . '/common.inc';
require_once __DIR__ . '/events.inc';

/**
 * Columns a caller may write.
 *
 * @return string[]
 * @since 1.0.0
 */
function notes_writable_columns()
{
    return array(
        'subject',
        'note_type',
        'note',
        'created_by',
        'owner',
        'group_id',
        'inactive',
    );
}

/**
 * Filter an input array down to writable columns, applying defaults.
 *
 * @param array $data Raw input
 * @return array Column => value
 * @since 1.0.0
 */
function notes_prepare(array $data)
{
    $out = array();

    foreach (notes_writable_columns() as $column) {
        if (array_key_exists($column, $data)) {
            $out[$column] = $data[$column];
        }
    }

    if (!isset($out['note_type']) || $out['note_type'] === '') {
        $out['note_type'] = 'Comment';
    }

    if (!array_key_exists('inactive', $out)) {
        $out['inactive'] = 0;
    }

    if (array_key_exists('owner', $out) && ($out['owner'] === '' || $out['owner'] === null)) {
        $out['owner'] = null;
    }

    if (array_key_exists('group_id', $out) && ($out['group_id'] === '' || $out['group_id'] === null)) {
        $out['group_id'] = null;
    }

    if (array_key_exists('subject', $out) && $out['subject'] === '') {
        $out['subject'] = null;
    }

    return $out;
}

/**
 * Build the INSERT for a note.
 *
 * @param array $data Prepared column => value map
 * @return string
 * @since 1.0.0
 */
function notes_sql_insert(array $data)
{
    $columns = array();
    $values = array();

    foreach ($data as $column => $value) {
        $columns[] = '`' . $column . '`';
        $values[] = notes_db_escape($value);
    }

    return 'INSERT INTO ' . TB_PREF . 'ksf_Notes (' . implode(', ', $columns)
        . ') VALUES (' . implode(', ', $values) . ')';
}

/**
 * Build the UPDATE for a note.
 *
 * @param int   $id   Note id
 * @param array $data Prepared column => value map
 * @return string Empty string when there is nothing to update
 * @since 1.0.0
 */
function notes_sql_update($id, array $data)
{
    $set = notes_build_set($data, notes_writable_columns());

    if ($set === '') {
        return '';
    }

    $set .= ', `modified_at` = ' . notes_db_escape(notes_now());

    return 'UPDATE ' . TB_PREF . 'ksf_Notes ' . $set
        . ' WHERE `id` = ' . notes_db_escape((int) $id);
}

/**
 * Build the SELECT for the WHERE fragment of a note query.
 *
 * @param array $criteria keyword, note_type, created_by, owner, group_id, inactive
 * @return array List of SQL boolean fragments
 * @since 1.0.0
 */
function notes_where_fragments(array $criteria)
{
    $fragments = array();

    if (isset($criteria['keyword']) && $criteria['keyword'] !== '') {
        $escaped = notes_db_escape('%' . $criteria['keyword'] . '%');
        $fragments[] = "(`subject` LIKE $escaped OR `note` LIKE $escaped)";
    }

    foreach (array('note_type', 'created_by') as $column) {
        if (isset($criteria[$column]) && $criteria[$column] !== '') {
            $fragments[] = '`' . $column . '` = ' . notes_db_escape($criteria[$column]);
        }
    }

    foreach (array('owner', 'group_id', 'inactive') as $column) {
        if (isset($criteria[$column]) && $criteria[$column] !== '') {
            $fragments[] = '`' . $column . '` = ' . notes_db_escape($criteria[$column]);
        }
    }

    return $fragments;
}

/**
 * Assemble a note SELECT, optionally filtered to notes linked to a record.
 *
 * @param array $criteria See notes_where_fragments(); may also carry
 *        entity_type/entity_id to restrict the result to notes referencing
 *        that record, plus limit/offset.
 * @return string
 * @since 1.0.0
 */
function notes_sql_select(array $criteria = array())
{
    $where = notes_where_fragments($criteria);

    $sql = 'SELECT DISTINCT n.* FROM ' . TB_PREF . 'ksf_Notes n';

    if (!empty($criteria['entity_type']) && isset($criteria['entity_id']) && $criteria['entity_id'] !== '') {
        $sql .= ' INNER JOIN ' . TB_PREF . 'ksf_Notes_link l ON l.`note_id` = n.`id`'
            . ' AND l.`entity_type` = ' . notes_db_escape($criteria['entity_type'])
            . ' AND l.`entity_id` = ' . notes_db_escape((string) $criteria['entity_id']);
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY n.`created_at` DESC, n.`id` DESC';

    if (isset($criteria['limit']) && (int) $criteria['limit'] > 0) {
        $sql .= ' LIMIT ' . (int) $criteria['limit'];
        if (isset($criteria['offset']) && (int) $criteria['offset'] > 0) {
            $sql .= ' OFFSET ' . (int) $criteria['offset'];
        }
    }

    return $sql;
}

/**
 * Every user id that owns at least one note.
 *
 * Used to populate the summary table's owner filter. Deliberately a DISTINCT
 * projection on its own query rather than a criteria key on the full note
 * select, which would drag every note row through the network to read one
 * column off them.
 *
 * @return string[]
 * @since 1.0.0
 */
function notes_sql_owners()
{
    $sql = 'SELECT DISTINCT `owner` FROM ' . TB_PREF . 'ksf_Notes'
        . " WHERE `owner` IS NOT NULL AND `owner` <> '' ORDER BY `owner`";

    $result = db_query($sql);

    if (!$result) {
        return array();
    }

    $owners = array();

    while ($row = db_fetch_assoc($result)) {
        $owners[] = (string) $row['owner'];
    }

    return $owners;
}

/**
 * Insert a note.
 *
 * @param array $data Raw input; see notes_prepare()
 * @return int New note id, or 0 on failure
 * @since 1.0.0
 */
function notes_write(array $data)
{
    $data = notes_prepare($data);

    if (!isset($data['note']) || trim($data['note']) === '') {
        return 0;
    }

    if (!isset($data['created_at'])) {
        $data['created_at'] = notes_now();
    }

    // Author defaults to the logged-in user, but only on insert: notes_prepare()
    // is shared with the update path, where created_by is the original author
    // and must never be overwritten by whoever last edited the note.
    if (!isset($data['created_by']) && isset($GLOBALS['user']['id'])) {
        $data['created_by'] = $GLOBALS['user']['id'];
    }

    if (function_exists('notes_before_hook')) {
        $filtered = notes_before_hook('before_save', array_merge($data, array('action' => 'new')));
        if (is_array($filtered)) {
            $data = notes_prepare($filtered) + $data;
        }
    }

    db_query(notes_sql_insert($data), 'Could not add note');

    $note_id = (int) db_insert_id();

    if (function_exists('notes_after_hook')) {
        notes_after_hook('after_save', $note_id, array('action' => 'new'));
    }

    return $note_id;
}

/**
 * Update a note.
 *
 * @param int   $id   Note id
 * @param array $data Raw input; see notes_prepare()
 * @return bool
 * @since 1.0.0
 */
function notes_update($id, array $data)
{
    // An update that touches no writable column is a no-op. Tested against the
    // caller's input rather than notes_prepare()'s output, which always
    // carries defaults and would make every call look like a change.
    if (!array_intersect(array_keys($data), notes_writable_columns())) {
        return false;
    }

    $prepared = notes_prepare($data);

    if (function_exists('notes_before_hook')) {
        $filtered = notes_before_hook('before_save', array_merge($prepared, array(
            'id' => (int) $id,
            'action' => 'edited',
        )));
        if (is_array($filtered)) {
            $prepared = notes_prepare($filtered);
        }
    }

    $sql = notes_sql_update($id, $prepared);

    if ($sql === '') {
        return false;
    }

    db_query($sql, 'Could not update note');

    if (function_exists('notes_after_hook')) {
        notes_after_hook('after_save', (int) $id, array('action' => 'edited'));
    }

    return true;
}

/**
 * Fetch one note.
 *
 * @param int $id Note id
 * @return array|null
 * @since 1.0.0
 */
function notes_get($id)
{
    $sql = 'SELECT * FROM ' . TB_PREF . 'ksf_Notes WHERE `id` = ' . notes_db_escape((int) $id);
    $result = db_query($sql, 'Could not get note');
    $row = db_fetch_assoc($result);

    return $row === false ? null : $row;
}

/**
 * Fetch notes matching a criteria array.
 *
 * @param array $criteria See notes_sql_select()
 * @return array List of note rows
 * @since 1.0.0
 */
function notes_get_all(array $criteria = array())
{
    $result = db_query(notes_sql_select($criteria), 'Could not get notes');
    $rows = array();

    while ($row = db_fetch_assoc($result)) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Count notes matching a criteria array.
 *
 * @param array $criteria See notes_sql_select()
 * @return int
 * @since 1.0.0
 */
function notes_sql_count(array $criteria = array())
{
    unset($criteria['limit'], $criteria['offset']);

    // The DISTINCT + entity join means COUNT(*) must wrap the select, not
    // replace it, or a note linked to N records would be counted N times.
    $sql = notes_sql_select($criteria);

    return 'SELECT COUNT(*) AS cnt FROM (' . $sql . ') AS matching';
}

/**
 * Count notes matching a criteria set.
 *
 * @param array $criteria See notes_get_all()
 * @return int
 * @since 1.0.0
 */
function notes_count(array $criteria = array())
{
    $result = db_query(notes_sql_count($criteria), 'Could not count notes');
    $row = db_fetch_assoc($result);

    return $row === false ? 0 : (int) $row['cnt'];
}

/**
 * Delete a note and its links.
 *
 * Links go first so the table is never left with orphans even if the note
 * delete itself fails.
 *
 * @param int $id Note id
 * @return bool
 * @since 1.0.0
 */
function notes_delete($id)
{
    $id = (int) $id;

    if (function_exists('notes_before_hook')) {
        notes_before_hook('before_delete', array('id' => $id, 'action' => 'delete'));
    }

    db_query(
        'DELETE FROM ' . TB_PREF . 'ksf_Notes_link WHERE `note_id` = ' . notes_db_escape($id),
        'Could not delete note links'
    );

    $sql = 'DELETE FROM ' . TB_PREF . 'ksf_Notes WHERE `id` = ' . notes_db_escape($id);
    db_query($sql, 'Could not delete note');

    if (function_exists('notes_after_hook')) {
        notes_after_hook('after_delete', $id);
    }

    return true;
}