<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Repository;

use ksfraser\FrontAccounting\Notes\Contract\NotesLinkRepositoryInterface;
use ksfraser\RelatedTo\DTO\RelatedQueryDto;
use ksfraser\RelatedTo\DTO\RelatedRecordDto;

/**
 * Notes link repository - Table Gateway for 0_ksf_Notes_link (deprecated).
 *
 * Delegates all link operations to the ksf_FA_RelatedTo module's hooks
 * (RELATE_RECORDS / RELATED_TO) and master table 0_ksf_RelatedTo.
 * The old 0_ksf_Notes_link table is retained for backward compatibility
 * but is no longer written to or read from.
 *
 * A note carries no foreign keys of its own; every association is a row in
 * 0_ksf_RelatedTo as (lhs_type='note', lhs_id=note_id,
 * rhs_type=<entity>, rhs_id=<entity_id>, link_role=<role>).
 *
 * @BABOK Related: FR-NT-001-002
 * @since 1.0.0
 * @deprecated Use ksf_FA_RelatedTo hooks (RELATE_RECORDS / RELATED_TO) instead.
 */
final class NotesLinkRepository implements NotesLinkRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function prepare(array $data): ?array
    {
        if (empty($data['note_id']) || empty($data['entity_type']) || !isset($data['entity_id'])) {
            return null;
        }

        $entity_id = trim((string) $data['entity_id']);
        if ($entity_id === '') {
            return null;
        }

        // Validate entity type via registry
        if (!function_exists('notes_entity_type') || notes_entity_type($data['entity_type']) === null) {
            return null;
        }

        $roles = $this->linkRolesFor($data['entity_type']);
        if (!$roles) {
            $roles = $this->defaultLinkRoles();
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
     * {@inheritdoc}
     */
    public function add(array $data): int
    {
        $link = $this->prepare($data);

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
        $existing = $this->find($link['note_id'], $link['entity_type'], $link['entity_id'], $link['link_role']);
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
     * {@inheritdoc}
     */
    public function find(int $note_id, string $entity_type, string $entity_id, ?string $link_role = null): ?array
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

        $sql = $this->sqlSelect($criteria);

        if ($link_role !== null) {
            $sql .= ' AND `link_role` = ' . notes_db_escape($link_role);
        }

        $result = db_query($sql, 'Could not get note link');
        $row = db_fetch_assoc($result);

        return $row === false ? null : $row;
    }

    /**
     * {@inheritdoc}
     */
    public function getByNote(int $note_id): array
    {
        $links = array();

        if (function_exists('hook_invoke')) {
            // Use symmetric query via RelatedTo repository
            $repoClass = '\\ksfraser\\FrontAccounting\\RelatedTo\\Repository\\RelatedRecordRepository';
            if (class_exists($repoClass)) {
                $repo = new $repoClass();
                $related = $repo->relatedSymmetric('note', $note_id);
                foreach ($related as $rel) {
                    if (!$rel instanceof RelatedRecordDto) {
                        continue;
                    }
                    // relatedSymmetric normalizes to given record as lhs
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
            // Fallback: direct table access (legacy 0_ksf_Notes_link table).
            $result = db_query(
                $this->sqlSelect(array('note_id' => $note_id)),
                'Could not get note links'
            );

            while ($row = db_fetch_assoc($result)) {
                $links[] = $row;
            }
        }

        return $links;
    }

    /**
     * {@inheritdoc}
     */
    public function getByEntity(string $entity_type, string $entity_id): array
    {
        $links = array();

        if (function_exists('hook_invoke')) {
            // Use symmetric query via RelatedTo repository
            $repoClass = '\\ksfraser\\FrontAccounting\\RelatedTo\\Repository\\RelatedRecordRepository';
            if (class_exists($repoClass)) {
                $repo = new $repoClass();
                $related = $repo->relatedSymmetric($entity_type, $entity_id);
                foreach ($related as $rel) {
                    if (!$rel instanceof RelatedRecordDto) {
                        continue;
                    }
                    // relatedSymmetric normalizes to given record as lhs
                    // We want notes where the entity is on the rhs (as related)
                    if ($rel->rhsType === 'note') {
                        $links[] = array(
                            'id' => 0,
                            'note_id' => $rel->rhsId,
                            'entity_type' => $rel->lhsType,
                            'entity_id' => $rel->lhsId,
                            'link_role' => $rel->linkRole,
                        );
                    }
                }
            }
        }

        if (empty($links)) {
            // Fallback: direct table access (legacy 0_ksf_Notes_link table).
            $result = db_query(
                $this->sqlSelect(array('entity_type' => $entity_type, 'entity_id' => $entity_id)),
                'Could not get record notes'
            );

            while ($row = db_fetch_assoc($result)) {
                $links[] = $row;
            }
        }

        return $links;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $note_id, string $entity_type, string $entity_id, ?string $link_role = null): bool
    {
        // Try RelatedTo repository directly for unrelate.
        $repoClass = '\\ksfraser\\FrontAccounting\\RelatedTo\\Repository\\RelatedRecordRepository';
        if (class_exists($repoClass)) {
            $repo = new $repoClass();
            $repo->unrelate('note', $note_id, $entity_type, $entity_id, $link_role);
        } else {
            // Fallback: direct table access.
            $sql = 'DELETE FROM ' . TB_PREF . 'ksf_Notes_link WHERE `note_id` = '
                . notes_db_escape((int) $note_id)
                . ' AND `entity_type` = ' . notes_db_escape($entity_type)
                . ' AND `entity_id` = ' . notes_db_escape((string) $entity_id);

            if ($link_role !== null) {
                $sql .= ' AND `link_role` = ' . notes_db_escape($link_role);
            }

            db_query($sql, 'Could not unlink note from record');
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(array $links): array
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
            } elseif (is_array($link)) {
                $resolvedLink['entity_type'] = $link['entity_type'] ?? '';
                $resolvedLink['entity_id'] = $link['entity_id'] ?? '';
                $resolvedLink['link_role'] = $link['link_role'] ?? '';
            }

            $def = $this->getEntityDef($resolvedLink['entity_type']);

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
     * {@inheritdoc}
     */
    public function sqlInsert(array $link): string
    {
        return 'INSERT INTO ' . TB_PREF . 'ksf_Notes_link'
            . ' (`note_id`, `entity_type`, `entity_id`, `link_role`) VALUES ('
            . notes_db_escape($link['note_id']) . ', '
            . notes_db_escape($link['entity_type']) . ', '
            . notes_db_escape($link['entity_id']) . ', '
            . notes_db_escape($link['link_role']) . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function sqlSelect(array $criteria = array()): string
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
     * Get entity definition from registry.
     *
     * @param string $entity_type
     * @return array|null
     */
    private function getEntityDef(string $entity_type): ?array
    {
        if (!function_exists('notes_entity_type')) {
            return null;
        }

        return notes_entity_type($entity_type);
    }

    /**
     * Default link roles.
     *
     * @return array<string, string>
     */
    private function defaultLinkRoles(): array
    {
        return array('reference' => 'Reference');
    }

    /**
     * Link roles for entity type.
     *
     * @param string $entity_type
     * @return array<string, string>
     */
    private function linkRolesFor(string $entity_type): array
    {
        if (!function_exists('notes_link_roles_for')) {
            return array();
        }

        return notes_link_roles_for($entity_type);
    }
}