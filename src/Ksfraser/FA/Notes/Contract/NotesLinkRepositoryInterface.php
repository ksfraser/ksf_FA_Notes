<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Contract;

/**
 * Repository contract for note-to-record links.
 *
 * Delegates to RelatedTo hooks when available, falls back to direct table access.
 *
 * @since 1.0.0
 */
interface NotesLinkRepositoryInterface
{
    /**
     * Associate a note with a record.
     *
     * @param array $data note_id, entity_type, entity_id, link_role
     * @return int New link id (1 on success, 0 when already present or failed)
     */
    public function add(array $data): int;

    /**
     * Find one specific link row.
     *
     * @param int    $note_id     Note id
     * @param string $entity_type Registry key
     * @param string $entity_id   Referenced pk
     * @param string $link_role   Role
     * @return array|null
     */
    public function find(int $note_id, string $entity_type, string $entity_id, ?string $link_role = null): ?array;

    /**
     * Fetch the links of one note.
     *
     * @param int $note_id Note id
     * @return array List of link rows (legacy shape: entity_type, entity_id, link_role)
     */
    public function getByNote(int $note_id): array;

    /**
     * Fetch every link pointing at one referenced record.
     *
     * @param string $entity_type Registry key
     * @param string $entity_id   Referenced pk
     * @return array List of link rows
     */
    public function getByEntity(string $entity_type, string $entity_id): array;

    /**
     * Remove one link.
     *
     * @param int    $note_id     Note id
     * @param string $entity_type Registry key
     * @param string $entity_id   Referenced pk
     * @param string $link_role   Role; null matches any role for that reference
     * @return bool
     */
    public function delete(int $note_id, string $entity_type, string $entity_id, ?string $link_role = null): bool;

    /**
     * Resolve link rows into readable labels by looking each reference up in its
     * own table.
     *
     * @param array $links List of link rows or RelatedRecordDto objects
     * @return array Rows with label, label2 and known (bool) added
     */
    public function resolve(array $links): array;

    /**
     * Build the INSERT for a link row.
     *
     * @param array $link Normalised row
     * @return string SQL
     */
    public function sqlInsert(array $link): string;

    /**
     * Build a SELECT for links, filtered by note and/or referenced record.
     *
     * @param array $criteria note_id, entity_type, entity_id
     * @return string SQL
     */
    public function sqlSelect(array $criteria = array()): string;

    /**
     * Normalise and validate a link row.
     *
     * @param array $data note_id, entity_type, entity_id, link_role
     * @return array|null Normalised row, or null when invalid
     */
    public function prepare(array $data): ?array;
}