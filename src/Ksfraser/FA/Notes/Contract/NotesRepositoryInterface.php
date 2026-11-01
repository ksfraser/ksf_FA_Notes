<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Contract;

/**
 * Repository contract for note persistence.
 *
 * @since 1.0.0
 */
interface NotesRepositoryInterface
{
    /**
     * Insert a note.
     *
     * @param array $data Prepared column => value map
     * @return int New note id, or 0 on failure
     */
    public function write(array $data): int;

    /**
     * Update a note.
     *
     * @param int   $id   Note id
     * @param array $data Prepared column => value map
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Fetch one note.
     *
     * @param int $id Note id
     * @return array|null
     */
    public function get(int $id): ?array;

    /**
     * Fetch notes matching a criteria array.
     *
     * @param array $criteria See select()
     * @return array List of note rows
     */
    public function getAll(array $criteria = array()): array;

    /**
     * Count notes matching a criteria set.
     *
     * @param array $criteria See select()
     * @return int
     */
    public function count(array $criteria = array()): int;

    /**
     * Delete a note and its links.
     *
     * @param int $id Note id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Build a SELECT query for notes.
     *
     * @param array $criteria
     * @return string SQL
     */
    public function select(array $criteria = array()): string;

    /**
     * Build an INSERT query for a note.
     *
     * @param array $data Prepared column => value map
     * @return string SQL
     */
    public function sqlInsert(array $data): string;

    /**
     * Build an UPDATE query for a note.
     *
     * @param int   $id   Note id
     * @param array $data Prepared column => value map
     * @return string SQL (empty when nothing to update)
     */
    public function sqlUpdate(int $id, array $data): string;

    /**
     * Get distinct note owners for filter dropdown.
     *
     * @return string[]
     */
    public function sqlOwners(): string;

    /**
     * Get writable columns.
     *
     * @return string[]
     */
    public function writableColumns(): array;

    /**
     * Prepare input data for write/update.
     *
     * @param array $data Raw input
     * @return array Column => value
     */
    public function prepare(array $data): array;
}