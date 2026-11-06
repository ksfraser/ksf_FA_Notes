<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\View;

use ksfraser\FrontAccounting\Common\ContactResolver\ContactResolver;
use ksfraser\FrontAccounting\Notes\Repository\EntityRegistry;
use ksfraser\RelatedTo\DTO\RelatedRecordDto;
use ksfraser\RelatedTo\UI\DefaultRelatedRecordsRenderer;

/**
 * Notes-specific related-records renderer.
 *
 * Extends the shared DefaultRelatedRecordsRenderer from related-to-core,
 * injecting FA-specific label, URL, and remove-link resolvers. Renders
 * related records grouped by rhs type under collapsible (twisty) section
 * headers, with resolved labels and clickable links to open records in
 * new windows/tabs.
 *
 * Border rendering is opt-in via the `table_border` render option; Notes
 * always passes `table_border => true`.
 *
 * Contact labels are resolved through the shared ContactResolver (ksf_FA_Common),
 * never through raw SQL.
 *
 * @since 1.0.0
 */
class RelatedRecordsRenderer extends DefaultRelatedRecordsRenderer
{
    /** @var EntityRegistry Entity registry for label resolution */
    private $registry;

    /** @var ContactResolver Resolves contact/person labels by id */
    private $contactResolver;

    /**
     * @param EntityRegistry $registry Entity registry for label resolution
     * @param ContactResolver|null $contactResolver Contact resolver (defaults
     *        to a new ContactResolver backed by FaContactResolver)
     * @since 1.0.0
     */
    public function __construct(EntityRegistry $registry = null, ContactResolver $contactResolver = null)
    {
        $this->registry = $registry ?? new EntityRegistry();
        $this->contactResolver = $contactResolver ?? new ContactResolver();

        $typeLabels = array(
            'customer' => _('Customers'),
            'contact' => _('Contacts'),
            'meeting' => _('Meetings'),
            'opportunity' => _('Opportunities'),
            'project' => _('Projects'),
            'task' => _('Tasks'),
            'calendar' => _('Calendar Entries'),
            'attachment' => _('Attachments'),
            'contract' => _('Contracts'),
            'note' => _('Notes'),
        );

        parent::__construct(array(
            'css_class' => 'related-records',
            'header_class' => 'related-header',
            'table_class' => 'related-table',
            'table_border' => true,
            'type_labels' => $typeLabels,
            'label_resolver' => array($this, 'resolveLabel'),
            'type_label_resolver' => array($this, 'resolveTypeLabel'),
            'url_resolver' => array($this, 'resolveUrl'),
            'remove_resolver' => array($this, 'resolveRemoveLink'),
            'escape' => array($this, 'esc'),
            'translate' => '_',
        ));
    }

    /**
     * {@inheritdoc}
     * @since 1.0.0
     */
    public function renderRelatedRecords(
        $lhsType,
        $lhsId,
        array $related,
        array $options = array()
    ) {
        return parent::renderRelatedRecords($lhsType, $lhsId, $related, $options);
    }

    /**
     * Resolve a record label from the entity registry / ContactResolver.
     *
     * For 'contact' type, delegates to ContactResolver::findById() so the
     * person's display name is shown instead of the raw id. All other types
     * fall back to the raw id (no lookup needed for the summary table).
     *
     * @param string $type
     * @param string|int $id
     * @return string
     * @since 1.0.0
     */
    public function resolveLabel($type, $id)
    {
        if ($type === 'contact') {
            $contact = $this->contactResolver->findById((int) $id);
            if ($contact !== null && $contact->getDisplayName() !== '') {
                return $contact->getDisplayName();
            }
        }

        return (string) $id;
    }

    /**
     * Resolve a type label.
     *
     * @param string $type
     * @return string
     * @since 1.0.0
     */
    public function resolveTypeLabel($type)
    {
        $labels = array(
            'customer' => _('Customers'),
            'contact' => _('Contacts'),
            'meeting' => _('Meetings'),
            'opportunity' => _('Opportunities'),
            'project' => _('Projects'),
            'task' => _('Tasks'),
            'calendar' => _('Calendar Entries'),
            'attachment' => _('Attachments'),
            'contract' => _('Contracts'),
            'note' => _('Notes'),
        );

        return isset($labels[$type]) ? $labels[$type] : ucfirst((string) $type);
    }

    /**
     * Get the URL to open a related record in a new window/tab.
     *
     * @param string $type
     * @param string|int $id
     * @return string URL
     * @since 1.0.0
     */
    public function resolveUrl($type, $id)
    {
        $urls = array(
            'customer' => "sales/customer_inquiries.php?customer_id={$id}",
            'contact' => "crm/crm_contacts.php?contact_id={$id}",
            'meeting' => "crm/meeting_view.php?id={$id}",
            'opportunity' => "crm/opportunity_view.php?id={$id}",
            'project' => "project/project_view.php?project_id={$id}",
            'task' => "project/task_view.php?task_id={$id}",
            'calendar' => "calendar/cal.php?id={$id}",
            'attachment' => "admin/attachments.php?vw={$id}",
            'invoice' => "sales/sales_inquiry.php?trans_no={$id}",
        );

        return isset($urls[$type]) ? $urls[$type] : "#";
    }

    /**
     * Build the confirming unlink remove link for a related record.
     *
     * @param RelatedRecordDto $record
     * @param string $lhsId
     * @return string HTML
     * @since 1.0.0
     */
    public function resolveRemoveLink($record, $lhsId)
    {
        return '<a href="notes.php?action=unlink&unlink_type=' . $this->esc($record->rhsType) . '&unlink_id=' . $this->esc($record->rhsId) . '&unlink_role=' . $this->esc($record->linkRole) . '&edit_id=' . $this->esc((string) $lhsId) . '" onclick="return confirm(\'' . $this->esc(_('Remove this association?')) . '\');">' . _('Remove') . '</a>';
    }

    /**
     * @param string $value
     * @return string
     * @since 1.0.0
     */
    public function esc($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
