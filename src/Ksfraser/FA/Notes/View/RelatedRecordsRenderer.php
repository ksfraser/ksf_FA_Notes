<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\View;

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
 * @since 1.0.0
 */
class RelatedRecordsRenderer extends DefaultRelatedRecordsRenderer
{
    /** @var EntityRegistry Entity registry for label resolution */
    private $registry;

    /**
     * @param EntityRegistry $registry Entity registry for label resolution
     * @since 1.0.0
     */
    public function __construct(EntityRegistry $registry = null)
    {
        $this->registry = $registry ?? new EntityRegistry();

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

        $lhsId = null; // resolved per-call in renderRelatedRecords

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
     * Resolve a record label from the entity registry / FA db_query.
     *
     * @param string $type
     * @param string|int $id
     * @return string
     * @since 1.0.0
     */
    public function resolveLabel($type, $id)
    {
        $def = $this->registry->get($type);

        if ($def === null) {
            return (string) $id;
        }

        global $db;
        if (!isset($db) || !$db instanceof \mysqli) {
            return (string) $id;
        }

        $table = TB_PREF . $def['table'];
        $pk = $def['pk'];
        $labelCol = $def['label'];

        $sql = "SELECT `$labelCol` AS label FROM `$table` WHERE `$pk` = " . $db->real_escape_string((string) $id) . " LIMIT 1";
        $result = db_query($sql);

        if ($result !== false && db_num_rows($result) > 0) {
            $row = db_fetch_assoc($result);
            if ($row !== false && !empty($row['label'])) {
                return (string) $row['label'];
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
