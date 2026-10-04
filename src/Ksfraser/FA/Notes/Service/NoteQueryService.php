<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Service;

/**
 * Note search.
 *
 * The work behind SEARCH_NOTES and GET_NOTES_FOR_ENTITY, so those responders
 * stay two-line adapters.
 *
 * @BABOK Related: FR-NT-001-003
 * @since 1.0.0
 */
final class NoteQueryService
{
    /**
     * Fetch one note with its links resolved to labels.
     *
     * @param int|string $note_id Note id
     * @return array{ok: bool, note?: array, error?: string}
     * @since 1.0.0
     */
    public function find($note_id): array
    {
        $note_id = notes_opt(null, array('note_id' => $note_id), 'note_id');

        if ($note_id === null || $note_id === '') {
            return array('ok' => false, 'error' => 'note_id_required');
        }

        $note = notes_get($note_id);

        if ($note === null) {
            return array('ok' => false, 'error' => 'note_not_found');
        }

        $note['links'] = notes_link_resolve(notes_link_get_by_note($note_id));

        return array('ok' => true, 'note' => $note);
    }

    /**
     * Search notes.
     *
     * Empty criteria are dropped rather than sent as '', so the gateway's
     * fragment builder sees only what the caller actually asked to filter on.
     *
     * @param array      $data Payload
     * @param array|null $opts Caller arguments, which win over $data
     * @return array{ok: bool, notes: array, count: int}
     * @since 1.0.0
     */
    public function search(array $data, $opts = null): array
    {
        $criteria = array();

        foreach (array('keyword', 'note_type', 'created_by', 'entity_type', 'entity_id', 'limit') as $field) {
            $value = notes_opt($opts, $data, $field);

            if ($value !== null && $value !== '') {
                $criteria[$field] = $value;
            }
        }

        $notes = notes_get_all($criteria);

        return array('ok' => true, 'notes' => $notes, 'count' => count($notes));
    }

    /**
     * Every note attached to one record.
     *
     * This is the read path CRM/HRM/ProjectManagement use to show the notes
     * belonging to one of their records.
     *
     * @param array      $data Payload
     * @param array|null $opts Caller arguments, which win over $data
     * @return array{ok: bool, notes?: array, count?: int, error?: string}
     * @since 1.0.0
     */
    public function forEntity(array $data, $opts = null): array
    {
        $entity_type = notes_opt($opts, $data, 'entity_type');
        $entity_id = notes_opt($opts, $data, 'entity_id');

        if ($entity_type === null || $entity_type === '' || $entity_id === null || $entity_id === '') {
            return array('ok' => false, 'error' => 'entity_required');
        }

        $notes = notes_get_all(array(
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'limit' => notes_opt($opts, $data, 'limit'),
        ));

        return array('ok' => true, 'notes' => $notes, 'count' => count($notes));
    }
}