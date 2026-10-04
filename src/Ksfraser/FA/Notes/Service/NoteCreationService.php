<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Service;

/**
 * Note creation.
 *
 * The work behind CREATE_NOTE. Kept out of hooks.php so the responder stays a
 * two-line adapter, and so this can be exercised without a hook dispatcher.
 *
 * @BABOK Related: FR-NT-001-001
 * @since 1.0.0
 */
final class NoteCreationService
{
    /**
     * Create one note and attach the requested records.
     *
     * @param array      $data Payload; note/subject/note_type/owner/...
     * @param array|null $opts Caller arguments, which win over $data. May
     *                         carry 'links' in either the associative or the
     *                         compact array($entity_type, $entity_id, $role) form.
     * @return array{ok: bool, note_id?: int, links_added?: int, error?: string}
     * @since 1.0.0
     */
    public function create(array $data, $opts = null): array
    {
        $note_id = notes_write($this->payload($data, $opts));

        if ($note_id <= 0) {
            return array('ok' => false, 'error' => 'note_body_required');
        }

        $added = 0;

        foreach ($this->links($opts) as $link) {
            $link['note_id'] = $note_id;

            if (notes_link_add($link) > 0) {
                $added++;
            }
        }

        return array('ok' => true, 'note_id' => $note_id, 'links_added' => $added);
    }

    /**
     * Collect the note fields to write.
     *
     * @param array      $data Payload
     * @param array|null $opts Caller arguments
     * @return array
     * @since 1.0.0
     */
    private function payload(array $data, $opts): array
    {
        $payload = array();

        foreach (array('note', 'subject', 'note_type', 'created_by', 'owner', 'group_id') as $field) {
            $value = notes_opt($opts, $data, $field);

            if ($value !== null) {
                $payload[$field] = $value;
            }
        }

        if (!isset($payload['created_by'])) {
            $payload['created_by'] = isset($GLOBALS['user']['id']) ? $GLOBALS['user']['id'] : null;
        }

        return $payload;
    }

    /**
     * Normalise the caller's 'links' argument into link rows.
     *
     * Accepts both list-of-arrays and the compact array($entity_type, $entity_id,
     * $role) form, because the compact form is what a caller naturally writes
     * for "attach these five records".
     *
     * @param array|null $opts Caller arguments
     * @return array List of link rows without note_id
     * @since 1.0.0
     */
    private function links($opts): array
    {
        if (!is_array($opts) || empty($opts['links']) || !is_array($opts['links'])) {
            return array();
        }

        $rows = array();

        foreach ($opts['links'] as $link) {
            if (!is_array($link)) {
                continue;
            }

            if (isset($link[0]) && isset($link[1])) {
                $link = array(
                    'entity_type' => $link[0],
                    'entity_id' => $link[1],
                    'link_role' => isset($link[2]) ? $link[2] : null,
                );
            }

            $rows[] = $link;
        }

        return $rows;
    }
}