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
            'table'    => 'sales_invoice',
            'pk'       => 'trans_no',
            'label'    => 'reference',
            'module'   => '',
            'picker'   => false,
            'multiple' => true,
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
            'reference' => 'Reference',
        );

        return $roles;
    }
}
