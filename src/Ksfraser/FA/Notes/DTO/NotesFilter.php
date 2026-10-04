<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\DTO;

/**
 * The filter the notes summary table is currently showing.
 *
 * Defaults to "my notes": notes owned by the logged-in user. The owner filter
 * can be widened to any user, which is why owner is a first-class field rather
 * than an afterthought of the query builder.
 *
 * Immutable value object: a filter is read from the request once, then passed
 * around unchanged.
 *
 * @BABOK Related: FR-NT-001-005
 * @since 1.0.0
 */
final class NotesFilter
{
    public const OWNER_SELF = 'self';
    public const OWNER_ANY = 'any';
    public const OWNER_SPECIFIC = 'specific';

    /** @var string self|any|specific */
    private $ownerScope;

    /** @var string Empty string means no specific owner */
    private $owner;

    /** @var string */
    private $keyword;

    /** @var string */
    private $noteType;

    /** @var string */
    private $entityType;

    /** @var string */
    private $entityId;

    /** @var int */
    private $limit;

    /** @var bool */
    private $includeInactive;

    /** @var string */
    private $currentUserId = '';

    /**
     * @param string $ownerScope      self|any|specific
     * @param string $owner           Owner id, ignored unless scope is a specific id
     * @param string $keyword         Free text over subject and body
     * @param string $noteType        Note category
     * @param string $entityType      Registry key of a linked record type
     * @param string $entityId        Linked record pk
     * @param int    $limit           Maximum rows
     * @param bool   $includeInactive Include notes flagged inactive
     * @since 1.0.0
     */
    public function __construct(
        string $ownerScope = self::OWNER_SELF,
        string $owner = '',
        string $keyword = '',
        string $noteType = '',
        string $entityType = '',
        string $entityId = '',
        int $limit = 100,
        bool $includeInactive = false
    ) {
        $valid = array(self::OWNER_SELF, self::OWNER_ANY, self::OWNER_SPECIFIC);
        $this->ownerScope = in_array($ownerScope, $valid, true) ? $ownerScope : self::OWNER_SELF;
        $this->owner = $owner;
        $this->keyword = trim($keyword);
        $this->noteType = $noteType;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->limit = $limit > 0 ? $limit : 100;
        $this->includeInactive = $includeInactive;
    }

    /**
     * Build from the request, defaulting to the logged-in user's own notes.
     *
     * Only $_GET is read, so the summary table is a safe link target and can
     * never change data.
     *
     * @param string $currentUserId Logged-in user id
     * @return self
     * @since 1.0.0
     */
    public static function fromRequest(string $currentUserId): self
    {
        $get = function (string $key, string $default = ''): string {
            return isset($_GET[$key]) && is_scalar($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
        };

        // "Mine" and "Everyone" are the two plain choices; naming a user, either
        // on its own or alongside the scope, means that user's notes.
        $scope = $get('owner_scope');
        $owner = $get('owner');

        if ($owner !== '') {
            $scope = self::OWNER_SPECIFIC;
        } elseif ($scope !== self::OWNER_ANY) {
            $scope = self::OWNER_SELF;
        }

        $limit = (int) $get('limit', '100');

        $filter = new self(
            $scope,
            $owner,
            $get('keyword'),
            $get('note_type'),
            $get('entity_type'),
            $get('entity_id'),
            $limit > 0 ? $limit : 100,
            $get('inactive') !== ''
        );

        $filter->setCurrentUserId($currentUserId);

        return $filter;
    }

    /**
     * @return string self|any
     * @since 1.0.0
     */
    public function ownerScope(): string
    {
        return $this->ownerScope;
    }

    /**
     * @return string
     * @since 1.0.0
     */
    public function owner(): string
    {
        return $this->owner;
    }

    /**
     * The owner to actually filter by, or '' for every owner.
     *
     * @return string
     * @since 1.0.0
     */
    public function effectiveOwner(): string
    {
        if ($this->ownerScope === self::OWNER_SELF) {
            return $this->currentUserId;
        }

        if ($this->ownerScope === self::OWNER_SPECIFIC) {
            return $this->owner;
        }

        return '';
    }

    /**
     * @param string $userId Logged-in user id
     * @return void
     * @since 1.0.0
     */
    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    /**
     * @return string
     * @since 1.0.0
     */
    public function currentUserId(): string
    {
        return $this->currentUserId;
    }

    /**
     * @return string
     * @since 1.0.0
     */
    public function keyword(): string
    {
        return $this->keyword;
    }

    /**
     * @return string
     * @since 1.0.0
     */
    public function noteType(): string
    {
        return $this->noteType;
    }

    /**
     * @return string
     * @since 1.0.0
     */
    public function entityType(): string
    {
        return $this->entityType;
    }

    /**
     * @return string
     * @since 1.0.0
     */
    public function entityId(): string
    {
        return $this->entityId;
    }

    /**
     * @return int
     * @since 1.0.0
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * @return bool
     * @since 1.0.0
     */
    public function includeInactive(): bool
    {
        return $this->includeInactive;
    }

    /**
     * Translate to the gateway's criteria array.
     *
     * @return array
     * @since 1.0.0
     */
    public function toCriteria(): array
    {
        $criteria = array(
            'keyword' => $this->keyword,
            'note_type' => $this->noteType,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'limit' => $this->limit,
        );

        $owner = $this->effectiveOwner();
        if ($owner !== '') {
            $criteria['owner'] = $owner;
        }

        if (!$this->includeInactive) {
            $criteria['inactive'] = 0;
        }

        return array_filter($criteria, function ($value) {
            return $value !== '' && $value !== null;
        });
    }
}