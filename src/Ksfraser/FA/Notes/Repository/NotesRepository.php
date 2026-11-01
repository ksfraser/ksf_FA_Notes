<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Repository;

use ksfraser\FrontAccounting\Notes\Contract\NotesRepositoryInterface;

/**
 * Notes repository - Table Gateway for 0_ksf_Notes.
 *
 * Follows the module convention of one repository per table exposing
 * write/get/delete. SQL construction is split from execution into pure
 * sql*() builders so the query text can be asserted in unit tests
 * without a database.
 *
 * @BABOK Related: FR-NT-001-001
 * @since 1.0.0
 */
final class NotesRepository implements NotesRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function writableColumns(): array
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
     * {@inheritdoc}
     */
    public function prepare(array $data): array
    {
        $out = array();

        foreach ($this->writableColumns() as $column) {
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
     * {@inheritdoc}
     */
    public function sqlInsert(array $data): string
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
     * {@inheritdoc}
     */
    public function sqlUpdate(int $id, array $data): string
    {
        $set = $this->buildSet($data, $this->writableColumns());

        if ($set === '') {
            return '';
        }

        $set .= ', `modified_at` = ' . notes_db_escape(notes_now());

        return 'UPDATE ' . TB_PREF . 'ksf_Notes ' . $set
            . ' WHERE `id` = ' . notes_db_escape((int) $id);
    }

    /**
     * Build the SET clause of an UPDATE from a whitelisted field map.
     *
     * @param array $data Field => value, already filtered to known columns
     * @param array $columns Allowed column names
     * @return string 'SET a = ..., b = ...' or '' when there is nothing to set
     * @since 1.0.0
     */
    private function buildSet(array $data, array $columns): string
    {
        $parts = array();

        foreach ($columns as $column) {
            if (array_key_exists($column, $data)) {
                $parts[] = '`' . $column . '` = ' . notes_db_escape($data[$column]);
            }
        }

        return $parts ? 'SET ' . implode(', ', $parts) : '';
    }

    /**
     * {@inheritdoc}
     */
    public function select(array $criteria = array()): string
    {
        $where = $this->whereFragments($criteria);

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
     * Build the WHERE fragments for a note query.
     *
     * @param array $criteria keyword, note_type, created_by, owner, group_id, inactive
     * @return array List of SQL boolean fragments
     * @since 1.0.0
     */
    private function whereFragments(array $criteria): array
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
     * {@inheritdoc}
     */
    public function sqlOwners(): string
    {
        return 'SELECT DISTINCT `owner` FROM ' . TB_PREF . 'ksf_Notes'
            . " WHERE `owner` IS NOT NULL AND `owner` <> '' ORDER BY `owner`";
    }

    /**
     * Get distinct note owners as an array.
     *
     * @return string[]
     * @since 1.0.0
     */
    public function getOwners(): array
    {
        $result = db_query($this->sqlOwners());

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
     * {@inheritdoc}
     */
    public function write(array $data): int
    {
        $data = $this->prepare($data);

        if (!isset($data['note']) || trim($data['note']) === '') {
            return 0;
        }

        if (!isset($data['created_at'])) {
            $data['created_at'] = notes_now();
        }

        // Author defaults to the logged-in user, but only on insert
        if (!isset($data['created_by'])) {
            $author = notes_current_user_loginname();
            if ($author !== '') {
                $data['created_by'] = $author;
            }
        }

        // Dispatch before_save hook
        if (function_exists('notes_before_hook')) {
            $filtered = notes_before_hook('before_save', array_merge($data, array('action' => 'new')));
            if (is_array($filtered)) {
                $data = $this->prepare($filtered) + $data;
            }
        }

        db_query($this->sqlInsert($data), 'Could not add note');

        $note_id = (int) db_insert_id();

        // Dispatch after_save hook
        if (function_exists('notes_after_hook')) {
            notes_after_hook('after_save', $note_id, array('action' => 'new'));
        }

        return $note_id;
    }

    /**
     * {@inheritdoc}
     */
    public function update(int $id, array $data): bool
    {
        // An update that touches no writable column is a no-op
        if (!array_intersect(array_keys($data), $this->writableColumns())) {
            return false;
        }

        $prepared = $this->prepare($data);

        // Dispatch before_save hook
        if (function_exists('notes_before_hook')) {
            $filtered = notes_before_hook('before_save', array_merge($prepared, array(
                'id' => (int) $id,
                'action' => 'edited',
            )));
            if (is_array($filtered)) {
                $prepared = $this->prepare($filtered);
            }
        }

        $sql = $this->sqlUpdate($id, $prepared);

        if ($sql === '') {
            return false;
        }

        db_query($sql, 'Could not update note');

        // Dispatch after_save hook
        if (function_exists('notes_after_hook')) {
            notes_after_hook('after_save', (int) $id, array('action' => 'edited'));
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get(int $id): ?array
    {
        $sql = 'SELECT * FROM ' . TB_PREF . 'ksf_Notes WHERE `id` = ' . notes_db_escape((int) $id);
        $result = db_query($sql, 'Could not get note');
        $row = db_fetch_assoc($result);

        return $row === false ? null : $row;
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(array $criteria = array()): array
    {
        $result = db_query($this->select($criteria), 'Could not get notes');
        $rows = array();

        while ($row = db_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $criteria = array()): int
    {
        unset($criteria['limit'], $criteria['offset']);

        // The DISTINCT + entity join means COUNT(*) must wrap the select
        $sql = $this->select($criteria);

        $result = db_query('SELECT COUNT(*) AS cnt FROM (' . $sql . ') AS matching', 'Could not count notes');
        $row = db_fetch_assoc($result);

        return $row === false ? 0 : (int) $row['cnt'];
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
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
}