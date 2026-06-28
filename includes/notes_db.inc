<?php
/**
 * Notes Database Functions
 */

/**
 * notes_dispatch_event() is now in includes/events.inc.
 * This file is kept for backward compatibility with code that loaded this file
 * before the events.inc refactor.
 */
if (!function_exists('notes_dispatch_event')) {
    require_once __DIR__ . '/events.inc';
}

function add_note($entity_id, $entity_type, $note, $note_type = 'Comment', $created_by = null, $owner = null, $group_id = null)
{
    $created_by = $created_by ?? $_SESSION['wa_current_user']->name ?? 'system';
    
    $columns = "entity_id, entity_type, note_type, note, created_by";
    $values = db_escape($entity_id) . ",
            " . db_escape($entity_type) . ",
            " . db_escape($note_type) . ",
            " . db_escape($note) . ",
            " . db_escape($created_by);

    if ($owner !== null) {
        $columns .= ", owner";
        $values .= ",\n            " . db_escape($owner);
    }
    if ($group_id !== null) {
        $columns .= ", group_id";
        $values .= ",\n            " . db_escape($group_id);
    }

    $sql = "INSERT INTO " . TB_PREF . "fa_crm_notes
        ($columns)
    VALUES ($values)";

    db_query($sql, "Could not add note");
    $note_id = db_insert_id();
    
    notes_dispatch_event('note_created', $note_id, ['entity_id' => $entity_id, 'entity_type' => $entity_type]);
    
    return $note_id;
}

function get_note($note_id)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_notes WHERE id = " . db_escape($note_id);
    $result = db_query($sql, "Could not get note");
    return db_fetch_assoc($result);
}

function get_notes($entity_id, $entity_type)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_notes
        WHERE entity_id = " . db_escape($entity_id) . "
        AND entity_type = " . db_escape($entity_type) . "
        ORDER BY created_at DESC";

    $result = db_query($sql, "Could not get notes");
    $notes = [];
    while ($row = db_fetch_assoc($result)) {
        $notes[] = $row;
    }
    return $notes;
}

function update_note($note_id, $note, $note_type = null)
{
    $sets = ["note = " . db_escape($note)];
    if ($note_type !== null) {
        $sets[] = "note_type = " . db_escape($note_type);
    }
    
    $sql = "UPDATE " . TB_PREF . "fa_crm_notes SET
        " . implode(', ', $sets) . "
        WHERE id = " . db_escape($note_id);

    db_query($sql, "Could not update note");
    
    notes_dispatch_event('note_updated', $note_id, []);
}

function delete_note($note_id)
{
    $old = get_note($note_id);
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_notes WHERE id = " . db_escape($note_id);
    db_query($sql, "Could not delete note");
    
    if ($old) {
        notes_dispatch_event('note_deleted', $note_id, [
            'entity_id' => $old['entity_id'],
            'entity_type' => $old['entity_type'],
        ]);
    }
}

function search_notes($keyword, $entity_type = null, $limit = 50)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_notes
        WHERE note LIKE " . db_escape('%' . $keyword . '%');

    if ($entity_type !== null) {
        $sql .= " AND entity_type = " . db_escape($entity_type);
    }

    $sql .= " ORDER BY created_at DESC LIMIT " . db_escape($limit);

    $result = db_query($sql, "Could not search notes");
    $notes = [];
    while ($row = db_fetch_assoc($result)) {
        $notes[] = $row;
    }
    return $notes;
}

function get_note_count($entity_id, $entity_type)
{
    $sql = "SELECT COUNT(*) as cnt FROM " . TB_PREF . "fa_crm_notes
        WHERE entity_id = " . db_escape($entity_id) . "
        AND entity_type = " . db_escape($entity_type);

    $result = db_query($sql, "Could not get note count");
    $row = db_fetch_assoc($result);
    return $row ? (int) $row['cnt'] : 0;
}

function get_entity_notes_summary($entity_type = null)
{
    $sql = "SELECT entity_type, entity_id, COUNT(*) as note_count,
            MAX(created_at) as last_note_date
        FROM " . TB_PREF . "fa_crm_notes";

    if ($entity_type !== null) {
        $sql .= " WHERE entity_type = " . db_escape($entity_type);
    }

    $sql .= " GROUP BY entity_type, entity_id ORDER BY last_note_date DESC";

    $result = db_query($sql, "Could not get notes summary");
    $summary = [];
    while ($row = db_fetch_assoc($result)) {
        $summary[] = $row;
    }
    return $summary;
}

/**
 * Link a note to an additional entity (multi-link support)
 *
 * @param int    $note_id
 * @param string $entity_type
 * @param int    $entity_id
 * @return int   The link ID
 * @throws RuntimeException If note not found
 */
function link_note(int $note_id, string $entity_type, int $entity_id): int
{
    $note = get_note($note_id);
    if (!$note) {
        throw new \RuntimeException("Note {$note_id} not found");
    }

    $sql = "INSERT IGNORE INTO " . TB_PREF . "fa_note_links
        (note_id, entity_type, entity_id)
        VALUES (" . db_escape($note_id) . ",
                " . db_escape($entity_type) . ",
                " . db_escape($entity_id) . ")";

    db_query($sql, "Could not link note to entity");
    $link_id = db_insert_id();

    notes_dispatch_event('note_linked', $note_id, [
        'entity_type' => $entity_type,
        'entity_id'   => $entity_id,
        'link_id'     => $link_id,
    ]);

    return $link_id;
}

/**
 * Unlink a note from an entity
 *
 * @param int    $note_id
 * @param string $entity_type
 * @param int    $entity_id
 */
function unlink_note(int $note_id, string $entity_type, int $entity_id): void
{
    $sql = "DELETE FROM " . TB_PREF . "fa_note_links
        WHERE note_id = " . db_escape($note_id) . "
        AND entity_type = " . db_escape($entity_type) . "
        AND entity_id = " . db_escape($entity_id);

    db_query($sql, "Could not unlink note from entity");

    notes_dispatch_event('note_unlinked', $note_id, [
        'entity_type' => $entity_type,
        'entity_id'   => $entity_id,
    ]);
}

/**
 * Get all entities linked to a note (including primary entity_id/entity_type)
 *
 * @param int $note_id
 * @return array  Array of ['entity_type', 'entity_id', ...]
 */
function get_linked_entities(int $note_id): array
{
    $note = get_note($note_id);
    if (!$note) {
        return [];
    }

    $entities = [];
    if (!empty($note['entity_type']) && !empty($note['entity_id'])) {
        $entities[] = [
            'entity_type' => $note['entity_type'],
            'entity_id'   => (int) $note['entity_id'],
            'is_primary'  => true,
        ];
    }

    $sql = "SELECT entity_type, entity_id FROM " . TB_PREF . "fa_note_links
        WHERE note_id = " . db_escape($note_id);

    $result = db_query($sql, "Could not get linked entities");
    while ($row = db_fetch_assoc($result)) {
        $entities[] = [
            'entity_type' => $row['entity_type'],
            'entity_id'   => (int) $row['entity_id'],
            'is_primary'  => false,
        ];
    }

    return $entities;
}

/**
 * ACL: Check if a user can view a note (owner or group access)
 *
 * @param int      $note_id
 * @param int|null $user_id  Defaults to current user
 * @return bool
 */
function can_view_note(int $note_id, ?int $user_id = null): bool
{
    if ($user_id === null) {
        $user_id = (int) ($_SESSION['wa_current_user']->user ?? 0);
    }

    $note = get_note($note_id);
    if (!$note) {
        return false;
    }

    if (!empty($note['owner']) && (int) $note['owner'] === $user_id) {
        return true;
    }

    if (empty($note['owner'])) {
        return true;
    }

    return false;
}

/**
 * ACL: Check if a user can edit a note
 *
 * @param int      $note_id
 * @param int|null $user_id  Defaults to current user
 * @return bool
 */
function can_edit_note(int $note_id, ?int $user_id = null): bool
{
    if ($user_id === null) {
        $user_id = (int) ($_SESSION['wa_current_user']->user ?? 0);
    }

    $note = get_note($note_id);
    if (!$note) {
        return false;
    }

    if (!empty($note['owner']) && (int) $note['owner'] === $user_id) {
        return true;
    }

    return false;
}