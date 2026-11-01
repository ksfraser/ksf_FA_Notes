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
use ksfraser\RelatedTo\UI\RelatedRecordsRendererInterface;

/**
 * Data-entry form for one note and the records it is attached to.
 *
 * Note fields (subject, body, owner, inactive) are edited in the main form.
 * Record associations are managed through:
 *   - A Type DDL -> Record DDL (Ajax cascade) + "Associate Record" button
 *   - A single cross-reference table listing every linked record, each with a
 *     Remove control (SuiteCRM-style twisty sections grouped by type)
 *
 * Every record dropdown is filled through EntityOptionsProvider, so the
 * options come from whichever module owns those records.
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

    /** @var array<string, string> */
    private $typeLabels;

    /** @var RelatedRecordsRendererInterface|null */
    private $renderer;

    /**
     * @param EntityOptionsProvider $options    Dropdown data, from responders
     * @param array                  $registry   Entity registry
     * @param string                 $formAction Target script for the POST
     * @param array                  $noteTypes  Value => label for note categories
     * @param array                  $typeLabels Module type => label from RELATED_MODULES
     * @since 1.0.0
     */
    public function __construct(
        EntityOptionsProvider $options,
        array $registry = array(),
        string $formAction = 'notes.php',
        array $noteTypes = array(),
        array $typeLabels = array()
    ) {
        $this->options = $options;
        $this->registry = $registry;
        $this->noteTypes = $noteTypes;
        $this->formAction = $formAction;
        $this->typeLabels = $typeLabels;
        $this->renderer = null;
    }

    /**
     * Set the related-records renderer (SuiteCRM-style twisty tables).
     *
     * @param RelatedRecordsRendererInterface $renderer
     * @return $this
     * @since 1.0.0
     */
    public function setRenderer(RelatedRecordsRendererInterface $renderer)
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * The whole form: main note fields, association controls, then the
     * cross-reference table of all linked records.
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
            (string) _('Note'),
            $this->textarea($note)
        )));
        $form->addNested(new HtmlString($this->row(
            (string) _('Assigned To'),
            $this->ownerSelect($note)
        )));
        $form->addNested(new HtmlString($this->row(
            (string) _('Inactive'),
            $this->inactiveCheckbox($note)
        )));
        $form->addNested(new HtmlString($this->tableEnd()));

        $form->addNested(new HtmlString($this->buttons($noteId)));

        // Association controls: Type DDL -> Record DDL + Associate Record button
        $html = $this->associationControls($noteId);

        // Related records table (SuiteCRM-style twisty sections grouped by type)
        if ($this->renderer !== null) {
            $html .= $this->renderer->renderRelatedRecords(
                'note',
                $noteId > 0 ? (string) $noteId : '',
                $this->resolveLinks($links),
                array(
                    'css_class' => 'related-records',
                    'header_class' => 'related-header',
                    'table_class' => 'related-table',
                    'type_labels' => !empty($this->typeLabels) ? $this->typeLabels : array(),
                )
            );
        } else {
            $html .= $this->xrefTableFallback($links, $noteId);
        }

        return $form->getHtml() . $html;
    }

    /**
     * Association controls: Type DDL cascading to Record DDL + Associate button.
     *
     * Each control lives in its own small POST form.
     *
     * @param int $noteId Note id, 0 when creating
     * @return string HTML
     * @since 1.0.0
     */
    private function associationControls(int $noteId): string
    {
        $html = '<fieldset style="margin-top:16px;padding:10px;border:1px solid #ccc;">';
        $html .= '<legend>' . _('Associate with records') . '</legend>';

        $html .= $this->associateForm(
            $noteId,
            'associate_record',
            _('Associate Record'),
            '<label for="assoc_type">' . _('Type') . ':</label> '
            . $this->associationTypeSelect() . ' '
            . $this->associationRecordSelect()
        );

        $html .= $this->associationCascadeJs();
        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Build one small associate POST form.
     *
     * @param int    $noteId Note id
     * @param string $action Form action value (associate_record)
     * @param string $button Button label
     * @param string $fields HTML for the label + select field(s)
     * @return string HTML
     * @since 1.0.0
     */
    private function associateForm(int $noteId, string $action, string $button, string $fields): string
    {
        return '<form method="post" action="' . $this->esc($this->formAction) . '"
                style="margin:0;padding:0;display:inline-block;">'
            . '<input type="hidden" name="edit_id" value="' . $noteId . '">'
            . '<input type="hidden" name="action" value="' . $this->esc($action) . '">'
            . $fields
            . ' <button type="submit">' . $this->esc($button) . '</button>'
            . '</form>';
    }

    /**
     * Association type dropdown. Changing this cascades the record dropdown
     * through Ajax (see associationCascadeJs()).
     *
     * @return string HTML
     * @since 1.0.0
     */
    private function associationTypeSelect(): string
    {
        $select = new HtmlSelect('assoc_type');
        $select->setAttribute('id', 'assoc_type');
        $select->addOption('', _('-- none --'), true);

        $types = !empty($this->typeLabels) ? $this->typeLabels : $this->options->typeLabels();

        foreach ($types as $type => $label) {
            $select->addOption((string) $type, (string) $label, false);
        }

        return $select->getHtml();
    }

    /**
     * Association record dropdown. Populated by Ajax from the selected type;
     * empty until a type is chosen.
     *
     * @return string HTML
     * @since 1.0.0
     */
    private function associationRecordSelect(): string
    {
        $select = new HtmlSelect('assoc_record');
        $select->setAttribute('id', 'assoc_record');
        $select->setAttribute('disabled', 'disabled');
        $select->addOption('', _('-- none --'), true);

        return $select->getHtml();
    }

    /**
     * Ajax cascade: choosing an association type fetches its record options
     * and populates the record dropdown.
     *
     * @return string HTML (inline script)
     * @since 1.0.0
     */
    private function associationCascadeJs(): string
    {
        $js = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    var typeSelect = document.getElementById('assoc_type');
    var recordSelect = document.getElementById('assoc_record');
    if (!typeSelect || !recordSelect) {
        return;
    }
    function refreshRecords() {
        var type = typeSelect.value;
        recordSelect.disabled = true;
        recordSelect.innerHTML = '<option value="">-- none --</option>';
        if (!type) {
            return;
        }
        var url = 'notes.php?ajax=entity_options&type=' + encodeURIComponent(type);
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                recordSelect.disabled = false;
                recordSelect.innerHTML = '<option value="">-- none --</option>';
                if (Array.isArray(data)) {
                    data.forEach(function(item) {
                        var option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name;
                        recordSelect.appendChild(option);
                    });
                }
            })
            .catch(function() {
                recordSelect.disabled = false;
            });
    }
    typeSelect.addEventListener('change', refreshRecords);
});
</script>
JS;

        return $js;
    }

    /**
     * Resolve legacy link rows into RelatedRecordDto[] for the renderer.
     *
     * @param array $links Legacy link rows (entity_type, entity_id, link_role)
     * @return array RelatedRecordDto[]
     * @since 1.0.0
     */
    private function resolveLinks(array $links): array
    {
        $dtos = array();

        foreach ($links as $link) {
            if ($link instanceof \ksfraser\RelatedTo\DTO\RelatedRecordDto) {
                $dtos[] = $link;
            } elseif (is_array($link)) {
                $dtos[] = new \ksfraser\RelatedTo\DTO\RelatedRecordDto(
                    $link['entity_type'] ?? '',
                    $link['entity_id'] ?? '',
                    'note',
                    $link['note_id'] ?? 0,
                    $link['link_role'] ?? null
                );
            }
        }

        return $dtos;
    }

    /**
     * Fallback cross-reference table when no renderer is set.
     *
     * @param array $links  Current link rows
     * @param int   $noteId Note id
     * @return string HTML
     * @since 1.0.0
     */
    private function xrefTableFallback(array $links, int $noteId): string
    {
        if (!$links) {
            return '<p>' . _('This note is not attached to anything.') . '</p>';
        }

        $html = '<fieldset style="margin-top:16px;padding:10px;border:1px solid #ccc;">';
        $html .= '<legend>' . _('Attached records') . '</legend>';
        $html .= $this->tableStart();
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

        $html .= $this->tableEnd();
        $html .= '</fieldset>';

        return $html;
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

        return '<input type="checkbox" name="inactive" id="notes_inactive" value="1"'
            . ($checked ? ' checked="checked"' : '')
            . '> <label for="notes_inactive">' . _('Inactive') . '</label>';
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

    /**
     * Owner dropdown - populated from 0_users table via FA native get_users().
     *
     * @param array $note Note row
     * @return string
     * @since 1.0.0
     */
    private function ownerSelect(array $note): string
    {
        $selected = (string) ($note['owner'] ?? '');
        $select = new HtmlSelect('owner');
        $select->addOption('', _('-- any --'), $selected === '');

        $result = get_users(true);

        if (is_array($result)) {
            foreach ($result as $row) {
                $userId = (string) ($row['user_id'] ?? '');
                if ($userId !== '') {
                    $select->addOption($userId, $userId, $userId === $selected);
                }
            }
        } elseif ($result !== false) {
            while ($row = db_fetch_assoc($result)) {
                if ($row !== false && isset($row['user_id']) && $row['user_id'] !== '') {
                    $select->addOption((string) $row['user_id'], (string) $row['user_id'], (string) $row['user_id'] === $selected);
                }
            }
        }

        return $select->getHtml();
    }

    /**
     * Human label for an entity type.
     *
     * @param string $type Registry key
     * @return string
     * @since 1.0.0
     */
    private function typeTitle(string $type): string
    {
        $titles = $this->options->typeLabels();

        $title = $titles[$type] ?? $type;

        return function_exists('_') ? (string) _($title) : $type;
    }
}
