<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\View;

use Ksfraser\FrontAccounting\Notes\DTO\NotesFilter;
use Ksfraser\FrontAccounting\Notes\Entity\EntityOptionsProvider;

/**
 * The notes summary table.
 *
 * Shows the notes matching the current filter — by default the logged-in
 * user's own notes — each with the records it is attached to, and links to
 * open or edit.
 *
 * The table is rendered from data the gateway has already fetched; this class
 * performs no queries of its own, which is what keeps it testable against
 * plain arrays.
 *
 * @BABOK Related: FR-NT-001-005
 * @since 1.0.0
 */
class NoteSummaryTableView extends AbstractListView
{
    /** @var array[] */
    private $notes;

    /** @var NotesFilter */
    private $filter;

    /** @var array<string, string> */
    private $noteTypes;

    /** @var array<string, string> */
    private $owners;

    /**
     * @param EntityOptionsProvider $options   Dropdown data, from responders
     * @param array[]                $notes     Rows, each with a resolved 'links' key
     * @param NotesFilter            $filter    Current filter
     * @param array                  $noteTypes Value => label for note categories
     * @param string                 $userId    Logged-in user id
     * @param string                 $userName  Logged-in user's name
     * @param array                  $owners    Value => label for the owner dropdown
     * @since 1.0.0
     */
    public function __construct(
        EntityOptionsProvider $options,
        array $notes,
        NotesFilter $filter,
        array $noteTypes = array(),
        string $userId = '',
        string $userName = '',
        array $owners = array()
    ) {
        parent::__construct($options, $userId, $userName);

        $this->notes = $notes;
        $this->filter = $filter;
        $this->noteTypes = $noteTypes;
        $this->owners = $owners;
    }

    /**
     * @return void
     * @since 1.0.0
     */
    public function render(): void
    {
        echo $this->filterBar->render($this->filter, $this->noteTypes, $this->owners);

        echo '<p>' . $this->heading() . '</p>';

        $this->renderTableStart(array(
            _('Subject'),
            _('Note'),
            _('Type'),
            _('Owner'),
            _('Attached to'),
            _('Created'),
            '',
        ));

        foreach ($this->notes as $note) {
            $this->renderRow($this->cellsFor($note));
        }

        $this->renderTableEnd();
    }

    /**
     * One-line summary of what is being shown.
     *
     * @return string
     * @since 1.0.0
     */
    private function heading(): string
    {
        $count = count($this->notes);

        if ($this->filter->ownerScope() === NotesFilter::OWNER_SELF) {
            return sprintf(_('%d note(s) owned by you'), $count);
        }

        if ($this->filter->owner() !== '') {
            return sprintf(_('%d note(s) for %s'), $count, $this->safeStr($this->ownerLabel()));
        }

        return sprintf(_('%d note(s) for everyone'), $count);
    }

    /**
     * @return string
     * @since 1.0.0
     */
    private function ownerLabel(): string
    {
        return $this->owners[$this->filter->owner()] ?? $this->filter->owner();
    }

    /**
     * @param array $note Note row
     * @return string[]
     * @since 1.0.0
     */
    private function cellsFor(array $note): array
    {
        $id = (int) ($note['id'] ?? 0);

        $subject = ($note['subject'] ?? '') !== '' ? $note['subject'] : _('(no subject)');

        return array(
            $this->safeStr($subject),
            $this->safeStr($note['note'] ?? '', 200),
            $this->safeStr($this->noteTypes[$note['note_type'] ?? ''] ?? ($note['note_type'] ?? '')),
            $this->safeStr($note['owner'] ?? ''),
            $this->attachedTo($note),
            $this->safeStr($this->formatDateTime($note['created_at'] ?? null)),
            $this->actions($id),
        );
    }

    /**
     * The records this note is attached to, resolved to labels.
     *
     * A link whose target has gone missing is shown as such rather than
     * silently dropped, so a note is never quietly edited down.
     *
     * @param array $note Note row with a 'links' key
     * @return string
     * @since 1.0.0
     */
    private function attachedTo(array $note): string
    {
        $links = $note['links'] ?? array();

        if (!$links) {
            return $this->safeStr(_('(not attached)'));
        }

        $parts = array();

        foreach ($links as $link) {
            $label = !empty($link['known'])
                ? (string) $link['label']
                : sprintf(_('%s (not found)'), (string) $link['entity_id']);

            $role = !empty($link['link_role']) ? ' (' . $link['link_role'] . ')' : '';

            $parts[] = $this->safeStr($link['entity_type'] . ': ' . $label . $role);
        }

        return implode('<br>', $parts);
    }

    /**
     * Open / Edit actions for one note.
     *
     * Edit is only offered when the user may manage notes; the page enforces
     * that too, but hiding the link saves a pointless round trip.
     *
     * @param int $noteId Note id
     * @return string
     * @since 1.0.0
     */
    private function actions(int $noteId): string
    {
        if ($noteId <= 0) {
            return '';
        }

        $html = '<a href="' . $this->safeStr($this->url('notes.php', array(
            'view' => 'form',
            'edit_id' => $noteId,
        ))) . '">' . _('Open') . '</a>';

        return $html;
    }
}