<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\View;

use Ksfraser\HTML\Elements\HtmlDiv;
use Ksfraser\HTML\Elements\HtmlForm;
use Ksfraser\HTML\Elements\HtmlInput;
use Ksfraser\HTML\Elements\HtmlLabel;
use Ksfraser\HTML\Elements\HtmlSelect;
use Ksfraser\HTML\Elements\HtmlString;
use Ksfraser\FrontAccounting\Notes\DTO\NotesFilter;
use Ksfraser\FrontAccounting\Notes\Entity\EntityOptionsProvider;

/**
 * The filter bar above the notes summary table.
 *
 * Offers, in one bar:
 *   - whose notes to show, defaulting to the logged-in user and widenable to
 *     any single user or everyone;
 *   - free text over subject and body;
 *   - note category;
 *   - which kind of record the note must be attached to, and which one — both
 *     dropdowns, the second populated from the first by the owning module.
 *
 * Every dropdown is filled through EntityOptionsProvider, so the record lists
 * come from the modules that own those records.
 *
 * @BABOK Related: FR-NT-001-005
 * @since 1.0.0
 */
class NoteFilterBar
{
    /** @var EntityOptionsProvider */
    private $options;

    /**
     * @param EntityOptionsProvider $options Dropdown data, from responders
     * @since 1.0.0
     */
    public function __construct(EntityOptionsProvider $options)
    {
        $this->options = $options;
    }

    /**
     * Render the bar.
     *
     * @param NotesFilter    $filter     Current filter, supplies selected values
     * @param array          $noteTypes  Value => label for the category dropdown
     * @param array|null     $owners     Value => label for the owner dropdown
     * @return string HTML
     * @since 1.0.0
     */
    public function render(NotesFilter $filter, array $noteTypes, ?array $owners = null): string
    {
        $div = new HtmlDiv('div');
        $div->setAttribute('style', 'margin-bottom:10px;');

        $form = new HtmlForm(null);
        $form->setAttribute('method', 'get');
        $form->setAttribute('style', 'margin:0;');

        $form->addNested(new HtmlString($this->textInput('keyword', $filter->keyword(), 24)));

        $form->addNested(new HtmlString(' ' . _('Owner') . ': '));
        $form->addNested($this->ownerSelect($filter, $owners ?? array()));

        $form->addNested(new HtmlString(' ' . _('Type') . ': '));
        $form->addNested($this->noteTypeSelect($filter, $noteTypes));

        $form->addNested(new HtmlString(' ' . _('About') . ': '));
        $form->addNested($this->entityTypeSelect($filter));

        $form->addNested(new HtmlString(' '));
        $form->addNested($this->entityRecordSelect($filter));

        $form->addNested(new HtmlString(' '));
        $form->addNested($this->checkbox('inactive', $filter->includeInactive(), _('Include inactive')));

        $form->addNested(new HtmlString(' '));
        $search = new HtmlInput(null, 'submit');
        $search->setValue(_('Filter'));
        $search->setAttribute('style', 'padding:6px 12px;');
        $form->addNested($search);

        $div->addNested($form);

        return $div->getHtml();
    }

    /**
     * Owner scope: mine, everyone, or a named user.
     *
     * @param NotesFilter $filter Current filter
     * @param array       $owners Value => label
     * @return HtmlSelect
     * @since 1.0.0
     */
    private function ownerSelect(NotesFilter $filter, array $owners): HtmlSelect
    {
        $choices = array(
            NotesFilter::OWNER_SELF => _('My notes'),
            NotesFilter::OWNER_ANY => _('Everyone'),
        );

        foreach ($owners as $id => $name) {
            $choices[(string) $id] = $name;
        }

        $selected = $filter->ownerScope();
        if ($filter->ownerScope() === NotesFilter::OWNER_SPECIFIC) {
            $selected = $filter->owner();
        }

        $select = new HtmlSelect('owner_scope');

        foreach ($choices as $value => $label) {
            $select->addOption((string) $value, $label, (string) $value === (string) $selected);
        }

        return $select;
    }

    /**
     * @param NotesFilter $filter   Current filter
     * @param array       $noteTypes Value => label
     * @return HtmlSelect
     * @since 1.0.0
     */
    private function noteTypeSelect(NotesFilter $filter, array $noteTypes): HtmlSelect
    {
        $select = new HtmlSelect('note_type');
        $select->addOption('', _('-- any --'), $filter->noteType() === '');

        foreach ($noteTypes as $value => $label) {
            $select->addOption((string) $value, $label, (string) $value === $filter->noteType());
        }

        return $select;
    }

    /**
     * Which kind of record the note must be attached to.
     *
     * @param NotesFilter $filter Current filter
     * @return HtmlSelect
     * @since 1.0.0
     */
    private function entityTypeSelect(NotesFilter $filter): HtmlSelect
    {
        $select = new HtmlSelect('entity_type');
        $select->addOption('', _('-- any --'), $filter->entityType() === '');

        // pickableTypes(), not all(): the registry's 'picker' flag gates every
        // dropdown Notes shows. A type with no table behind it would only ever
        // filter to an empty list.
        foreach ($this->options->pickableTypes() as $type) {
            $select->addOption($type, $this->typeLabel($type), $type === $filter->entityType());
        }

        return $select;
    }

    /**
     * Which record, drawn from the options the owning module supplied.
     *
     * @param NotesFilter $filter Current filter
     * @return HtmlSelect
     * @since 1.0.0
     */
    private function entityRecordSelect(NotesFilter $filter): HtmlSelect
    {
        $select = new HtmlSelect('entity_id');
        $select->addOption('', _('-- any --'), $filter->entityId() === '');

        if ($filter->entityType() !== '') {
            foreach ($this->options->optionsFor($filter->entityType()) as $id => $label) {
                $select->addOption((string) $id, $label, (string) $id === $filter->entityId());
            }
        }

        return $select;
    }

    /**
     * Human label for an entity type, from the registry when it has one.
     *
     * @param string $type Registry key
     * @return string
     * @since 1.0.0
     */
    private function typeLabel(string $type): string
    {
        $labels = $this->options->typeLabels();

        return $labels[$type] ?? (function_exists('_') ? _($type) : $type);
    }

    /**
     * @param string $name    Field name
     * @param string $value   Current value
     * @param int    $size    Visible width
     * @return HtmlInput
     * @since 1.0.0
     */
    private function textInput(string $name, string $value, int $size): HtmlInput
    {
        $input = new HtmlInput(null, 'text');
        $input->setName($name);
        $input->setValue($value);
        $input->setAttribute('size', (string) $size);
        $input->setAttribute('placeholder', (string) _('Search notes'));

        return $input;
    }

    /**
     * A labelled checkbox, rendered as one HtmlString so it can be nested in
     * the form alongside the HtmlInput/HtmlSelect elements.
     *
     * @param string $name    Field name
     * @param bool   $checked Current state
     * @param string $label   Label text
     * @return HtmlString
     * @since 1.0.0
     */
    private function checkbox(string $name, bool $checked, string $label): HtmlString
    {
        $id = 'notes_' . $name;

        $input = new HtmlInput(null, 'checkbox');
        $input->setName($name);
        $input->setValue('1');
        $input->setAttribute('id', $id);

        if ($checked) {
            $input->setAttribute('checked', 'checked');
        }

        $labelElement = new HtmlLabel($id, $label);

        return new HtmlString($input->getHtml() . ' ' . $labelElement->getHtml());
    }
}