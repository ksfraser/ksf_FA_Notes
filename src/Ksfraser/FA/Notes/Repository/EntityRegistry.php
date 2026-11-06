<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\Notes\Repository;

use ksfraser\FrontAccounting\Common\Repository\EntityRegistry as CommonEntityRegistry;
use ksfraser\FrontAccounting\Notes\Contract\EntityRegistryInterface;

/**
 * Notes-specific entity registry extending the common one.
 *
 * Adds Notes-specific entity types (invoice), note types, and link roles.
 *
 * @BABOK Related: FR-NT-001-002
 * @since 1.0.0
 */
final class EntityRegistry extends CommonEntityRegistry implements EntityRegistryInterface
{
    /**
     * {@inheritdoc}
     */
    protected function buildRegistry(): array
    {
        // Get base registry from parent (Common)
        $types = parent::buildRegistry();

        // Add Notes-specific types
        $types['invoice'] = array(
            'title'    => 'Invoice',
            'table'    => 'debtor_trans',
            'pk'       => 'trans_no',
            'label'    => 'reference',
            'module'   => '',
            'picker'   => false,
            'multiple' => true,
            'where'    => "type = " . (defined('ST_SALESINVOICE') ? ST_SALESINVOICE : 10),
        );

        $types['contact'] = array(
            'title'    => 'Contact',
            'table'    => 'crm_persons',
            'pk'       => 'id',
            'label'    => 'name',
            'module'   => '',
            'picker'   => true,
            'multiple' => true,
        );

        $types['meeting'] = array(
            'title'    => 'Meeting',
            'table'    => 'crm_meetings',
            'pk'       => 'id',
            'label'    => 'name',
            'module'   => '',
            'picker'   => true,
            'multiple' => true,
        );

        $types['opportunity'] = array(
            'title'    => 'Opportunity',
            'table'    => 'crm_opportunities',
            'pk'       => 'id',
            'label'    => 'name',
            'module'   => '',
            'picker'   => true,
            'multiple' => true,
        );

        $types['contract'] = array(
            'title'    => 'Contract',
            'table'    => 'ksf_contract',
            'pk'       => 'id',
            'label'    => 'name',
            'module'   => '',
            'picker'   => false,
            'multiple' => true,
        );

        $types['project'] = array(
            'title'    => 'Project',
            'table'    => 'projects',
            'pk'       => 'project_id',
            'label'    => 'name',
            'module'   => '',
            'picker'   => true,
            'multiple' => true,
        );

        $types['task'] = array(
            'title'    => 'Task',
            'table'    => 'project_tasks',
            'pk'       => 'id',
            'label'    => 'name',
            'module'   => '',
            'picker'   => true,
            'multiple' => true,
        );

        $types['calendar'] = array(
            'title'    => 'Calendar Entry',
            'table'    => 'crm_calendar',
            'pk'       => 'id',
            'label'    => 'title',
            'module'   => '',
            'picker'   => true,
            'multiple' => true,
        );

        $types['attachment'] = array(
            'title'    => 'Attachment',
            'table'    => 'attachments',
            'pk'       => 'id',
            'label'    => 'name',
            'module'   => '',
            'picker'   => true,
            'multiple' => true,
        );

        if (function_exists('hook_invoke_all')) {
            $data = array('types' => $types);
            hook_invoke_all('NOTES_ENTITY_TYPES', $data);
            if (isset($data['types']) && is_array($data['types'])) {
                $types = $data['types'];
            }
        }

        return $types;
    }

    /**
     * {@inheritdoc}
     */
    public function noteTypes(): array
    {
        $types = array(
            'Comment' => _('Comment'),
            'Call' => _('Call'),
            'Email' => _('Email'),
            'Meeting' => _('Meeting'),
            'Task' => _('Task'),
            'Document' => _('Document'),
            'Issue' => _('Issue'),
            'Reminder' => _('Reminder'),
        );

        if (function_exists('hook_invoke_all')) {
            $payload = array('types' => $types);
            hook_invoke_all('NOTES_NOTE_TYPES', $payload);
            if (isset($payload['types']) && is_array($payload['types'])) {
                $types = $payload['types'];
            }
        }

        return $types;
    }

    /**
     * {@inheritdoc}
     */
    protected function linkRoles(): array
    {
        // Get base roles from parent
        $roles = parent::linkRoles();

        // Add Notes-specific roles
        $roles['invoice'] = array(
            'source_invoice' => 'Source Invoice',
            'square_invoice' => 'Square Invoice',
            'reference' => 'Reference',
        );

        $roles['contact'] = array(
            'invoice_contact' => 'Invoice Contact',
            'with' => 'With',
            'reference' => 'Reference',
        );

        $roles['attachment'] = array(
            'source_attachment' => 'Source Attachment',
            'document' => 'Document',
            'reference' => 'Reference',
        );

        $roles['meeting'] = array(
            'source_meeting' => 'Source Meeting',
            'reference' => 'Reference',
        );

        $roles['opportunity'] = array(
            'source_opportunity' => 'Source Opportunity',
            'reference' => 'Reference',
        );

        $roles['project'] = array(
            'source_project' => 'Source Project',
            'reference' => 'Reference',
        );

        $roles['task'] = array(
            'source_task' => 'Source Task',
            'reference' => 'Reference',
        );

        $roles['calendar'] = array(
            'source_calendar' => 'Source Calendar Entry',
            'reference' => 'Reference',
        );

        $roles['attachment'] = array(
            'source_attachment' => 'Source Attachment',
            'reference' => 'Reference',
        );

        return $roles;
    }
}
