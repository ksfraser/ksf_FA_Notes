<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\View;

use Ksfraser\HTML\Elements\HtmlForm;
use Ksfraser\HTML\Elements\HtmlInput;
use Ksfraser\HTML\Elements\HtmlLabel;
use Ksfraser\HTML\Elements\HtmlLink;
use Ksfraser\HTML\Elements\HtmlSelect;
use Ksfraser\HTML\Elements\HtmlString;
use Ksfraser\HTML\Form\FormBuilder;
use Ksfraser\FrontAccounting\Notes\Entity\EntityOptionsProvider;

/**
 * Data-entry form for one note and the records it is attached to.
 *
 * The note fields and the attachment pickers are separate concerns, so they are
 * separate methods: a caller rendering a create form, an edit form, or just the
 * link section uses only what it needs.
 *
 * Every record dropdown is filled through EntityOptionsProvider, so the options
 * come from whichever module owns those records.
 *
 * @BABOK Related: FR-NT-001-004
 * @since 1.0.0
 */
class NoteFormView
{
    /** @var EntityOptionsProvider */
    private $options;

    /** @var array<string, string> */
    private $noteTypes;

    /** @var array<string, array> */
    private $registry;

    /** @var string */
    private $formAction;

    /**
     * @param EntityOptionsProvider $options    Dropdown data, from responders
     * @param array                  $registry   Entity registry
     * @param array                  $noteTypes  Value => label for note categories
     * @param string                 $formAction Target script for the POST
     * @since 1.0.0
     */
    public function __construct(
        EntityOptionsProvider $options,
        array $registry = array(),
        array $noteTypes = array(),
        string $formAction = 'notes.php'
    ) {
        $this->options = $options;
        $this->registry = $registry;
        $this->noteTypes = $noteTypes;
        $this->formAction = $formAction;
    }

    /**
     * The whole form.
     *
     * @param array|null $note   Existing note, or null to create
     * @param array      $links  Current link rows when editing
     * @return string HTML
     * @since 1.0.0
     */
    public function render($note = null, array $links = array()): string
    {
        $note = is_array($note) ? $note : array();
        $noteId = (int) ($note['id'] ?? 0);

        $form = new HtmlForm(null);
        $form->setAttribute('method', 'post');
        $form->setAttribute('action', $this->formAction);

        $fb = (new FormBuilder())->withData($note);

        $hidden = new HtmlInput(null, 'hidden');
        $hidden->setName('edit_id');
        $hidden->setValue((string) $noteId);
        $form->addNested($hidden);

        $action = new HtmlInput(null, 'hidden');
        $action->setName('action');
        $action->setValue($noteId > 0 ? 'update' : 'create');
        $form->addNested($action);

        $form->addNested(new HtmlString($this->tableStart()));
        $form->addNested(new HtmlString($this->row(
            (string) _('Subject'),
            $fb->text('subject')->setAttribute('size', '60')->setAttribute('maxlength', '100')->getHtml()
        )));
        $form->addNested(new HtmlString($this->row(
            (string) _('Type'),
            $this->noteTypeSelect($note)
        )));
        $form->addNested(new HtmlString($this->row(
            (string) _('Note'),
            $this->textarea($note)
        )));
        $form->addNested(new HtmlString($this->row(
            (string) _('Owner'),
            $fb->text('owner')->setAttribute('size', '20')->getHtml()
        )));
        $form->addNested(new HtmlString($this->row(
            (string) _('Inactive'),
            $this->inactiveCheckbox($note)
        )));
        $form->addNested(new HtmlString($this->tableEnd()));

        $form->addNested(new HtmlString($this->linksSection($links, $noteId)));
        $form->addNested(new HtmlString($this->buttons($noteId)));

        return $form->getHtml();
    }

    /**
     * The attachment pickers only.
     *
     * One multi-select per pickable type, pre-selected with what is already
     * attached, plus a role selector when a type offers more than one role.
     *
     * @param array $links  Current link rows
     * @param int   $noteId Note id
     * @return string HTML
     * @since 1.0.0
     */
    public function linksSection(array $links, int $noteId = 0): string
    {
        $attached = array();
        foreach ($links as $link) {
            $attached[$link['entity_type']][] = (string) $link['entity_id'];
        }

        $pickable = $this->options->pickableTypes();

        if (!$pickable) {
            return '';
        }

        $html = $this->tableStart();
        $html .= '<tr><th colspan="2" style="text-align:left;">' . _('Attach to') . '</th></tr>';

        foreach ($pickable as $type) {
            $items = $this->options->optionsFor($type);
            $selected = $attached[$type] ?? array();

            $html .= $this->row(
                (string) $this->typeTitle($type),
                $this->multiSelect('link_' . $type, $items, $selected)
            );

            $roles = $this->rolesFor($type);
            if (count($roles) > 1) {
                $html .= $this->row(
                    (string) _('Role'),
                    $this->roleSelect('role_' . $type, $roles, $roles[array_key_first($roles)] ?? null)
                );
            }
        }

        return $html . $this->tableEnd();
    }

    /**
     * The list of current links, each with a remove control.
     *
     * @param array $links  Current link rows
     * @param int   $noteId Note id
     * @return string HTML
     * @since 1.0.0
     */
    public function currentLinks(array $links, int $noteId): string
    {
        if (!$links) {
            return '<p>' . _('This note is not attached to anything.') . '</p>';
        }

        $html = $this->tableStart();
        $html .= '<tr><th>' . _('Type') . '</th><th>' . _('Record')
            . '</th><th>' . _('Role') . '</th><th></th></tr>';

        foreach ($links as $link) {
            $label = !empty($link['known'])
                ? (string) $link['label']
                : sprintf(_('%s (not found)'), (string) $link['entity_id']);

            $html .= '<tr>';
            $html .= '<td>' . $this->esc((string) $link['entity_type']) . '</td>';
            $html .= '<td>' . $this->esc($label) . '</td>';
            $html .= '<td>' . $this->esc((string) ($link['link_role'] ?? '')) . '</td>';
            $html .= '<td>' . $this->unlinkLink($noteId, $link) . '</td>';
            $html .= '</tr>';
        }

        return $html . $this->tableEnd();
    }

    /**
     * @param int   $noteId Note id
     * @param array $link   Link row
     * @return string
     * @since 1.0.0
     */
    private function unlinkLink(int $noteId, array $link): string
    {
        $href = 'notes.php?' . http_build_query(array(
            'view' => 'form',
            'edit_id' => $noteId,
            'action' => 'unlink',
            'unlink_type' => (string) $link['entity_type'],
            'unlink_id' => (string) $link['entity_id'],
            'unlink_role' => (string) ($link['link_role'] ?? ''),
        ));

        $link_element = new HtmlLink(null);
        $link_element->setHref($href);
        $link_element->setText((string) _('Remove'));

        return $link_element->getHtml();
    }

    /**
     * @param int $noteId Note id, 0 when creating
     * @return string
     * @since 1.0.0
     */
    private function buttons(int $noteId): string
    {
        $save = new HtmlInput(null, 'submit');
        $save->setName('save');
        $save->setValue((string) ($noteId > 0 ? _('Update Note') : _('Create Note')));
        $save->setAttribute('style', 'padding:6px 14px;');

        $cancel = new HtmlLink(null);
        $cancel->setHref('notes.php?view=summary');
        $cancel->setText((string) _('Cancel'));

        $html = '<p>' . $save->getHtml() . ' &nbsp; ' . $cancel->getHtml() . '</p>';

        if ($noteId > 0) {
            $delete = new HtmlLink(null);
            $delete->setHref('notes.php?' . http_build_query(array(
                'view' => 'summary',
                'action' => 'delete',
                'note_id' => $noteId,
            )));
            $delete->setText((string) _('Delete Note'));

            $html .= ' <p>' . $delete->getHtml() . '</p>';
        }

        return $html;
    }

    /**
     * @param array $note Note row
     * @return string
     * @since 1.0.0
     */
    private function noteTypeSelect(array $note): string
    {
        $selected = (string) ($note['note_type'] ?? '');
        if ($selected === '' || !isset($this->noteTypes[$selected])) {
            $selected = (string) (array_key_first($this->noteTypes) ?? 'Comment');
        }

        $select = new HtmlSelect('note_type');

        foreach ($this->noteTypes as $value => $label) {
            $select->addOption((string) $value, (string) $label, (string) $value === $selected);
        }

        return $select->getHtml();
    }

    /**
     * @param array $note Note row
     * @return string
     * @since 1.0.0
     */
    private function textarea(array $note): string
    {
        $name = 'notes_note';

        $html = '<textarea name="' . $this->esc($name) . '" id="' . $this->esc($name)
            . '" cols="80" rows="6">'
            . $this->esc((string) ($note['note'] ?? ''))
            . '</textarea>';

        return $html;
    }

    /**
     * @param array $note Note row
     * @return string
     * @since 1.0.0
     */
    private function inactiveCheckbox(array $note): string
    {
        $checked = !empty($note['inactive']);

        return '<input type="hidden" name="inactive_present" value="1">'
            . '<input type="checkbox" name="inactive" id="notes_inactive" value="1"'
            . ($checked ? ' checked="checked"' : '')
            . '> <label for="notes_inactive">' . _('Inactive') . '</label>';
    }

    /**
     * @param string            $name     Field name
     * @param array<string,string> $items Options, value => label
     * @param string[]          $selected Currently selected values
     * @return string
     * @since 1.0.0
     */
    private function multiSelect(string $name, array $items, array $selected): string
    {
        if (!$items) {
            return '<span>' . _('No records available') . '</span>';
        }

        $select = new HtmlSelect($name);
        $select->setAttribute('multiple', 'multiple');
        $select->setAttribute('size', '5');

        $select->addOption('', (string) _('-- none --'), false);

        foreach ($items as $value => $label) {
            $select->addOption(
                (string) $value,
                (string) $label,
                in_array((string) $value, array_map('strval', $selected), true)
            );
        }

        return $select->getHtml();
    }

    /**
     * @param string                $name     Field name
     * @param array<string,string>  $roles    Role => label
     * @param string|null           $selected Currently selected role
     * @return string
     * @since 1.0.0
     */
    private function roleSelect(string $name, array $roles, ?string $selected): string
    {
        $select = new HtmlSelect($name);

        foreach ($roles as $value => $label) {
            $select->addOption((string) $value, (string) $label, (string) $value === (string) $selected);
        }

        return $select->getHtml();
    }

    /**
     * @param string $type Registry key
     * @return array<string, string>
     * @since 1.0.0
     */
    private function rolesFor(string $type): array
    {
        return isset($this->registry['link_roles'][$type]) && is_array($this->registry['link_roles'][$type])
            ? $this->registry['link_roles'][$type]
            : array('reference' => (string) _('Reference'));
    }

    /**
     * @param string $type Registry key
     * @return string
     * @since 1.0.0
     */
    private function typeTitle(string $type): string
    {
        $titles = $this->options->typeLabels();

        $title = $titles[$type] ?? $type;

        return function_exists('_') ? (string) _($title) : $title;
    }

    /**
     * @param string $label Row label
     * @param string $value Row content
     * @return string
     * @since 1.0.0
     */
    private function row(string $label, string $value): string
    {
        return '<tr><td class="label" style="width:30%;vertical-align:top;">'
            . $this->esc($label) . '</td><td>' . $value . "</td></tr>\n";
    }

    /**
     * @return string
     * @since 1.0.0
     */
    private function tableStart(): string
    {
        return '<table class="selection" style="width:100%;max-width:900px;">' . "\n";
    }

    /**
     * @return string
     * @since 1.0.0
     */
    private function tableEnd(): string
    {
        return "</table>\n";
    }

    /**
     * @param string $value Raw value
     * @return string
     * @since 1.0.0
     */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}