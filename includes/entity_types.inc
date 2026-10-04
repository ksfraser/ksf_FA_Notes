<?php
/**
 * Registry of record types a note may be linked to.
 *
 * A note never stores foreign keys. Associations live in 0_ksf_Notes_link as
 * (entity_type, entity_id) pairs, and this registry is the single place that
 * says what an entity_type means: which table holds it, what its primary key
 * is called, and how to render a label for it.
 *
 * entity_id is stored as VARCHAR(64) because the referenced primary keys are
 * not uniform: crm_persons/meetings/opportunities/attachments use INT ids,
 * while debtors_master.debtor_no and fa_pm_projects.project_id are VARCHAR.
 *
 * Any module may register an additional type, or override a built-in one, by
 * listening for the NOTES_ENTITY_TYPES hook:
 *
 *   $data = ['types' => $types];
 *   hook_invoke_all('NOTES_ENTITY_TYPES', $data);
 *
 * This is what lets the ksf_FA_Calendar and ksf_FA_ProjectManagement table
 * renames (0_fa_* -> 0_ksf_*) happen in those modules without editing Notes.
 *
 * @BABOK Related: FR-NT-001-001
 * @since 1.0.0
 */

/**
 * Return the entity-type registry, honouring the NOTES_ENTITY_TYPES filter.
 *
 * @return array<string, array> Map of entity_type => definition. Each
 *         definition has: table (relative to TB_PREF), pk, label, module,
 *         picker (bool, can the Notes UI offer a chooser), multiple (bool,
 *         may one note reference several of these), and title.
 * @since 1.0.0
 */
function notes_entity_types()
{
    static $types = null;

    if ($types !== null) {
        return $types;
    }

    $types = array(
        // FA core. A "customer" is the debtor master record.
        'customer' => array(
            'title' => 'Customer',
            'table' => 'debtors_master',
            'pk' => 'debtor_no',
            'label' => 'name',
            'module' => '',
            'picker' => true,
            'multiple' => true,
        ),

        // FA core native person. This one type covers BOTH customer-side
        // contacts and HRM employees: ksf_FA_HRM's EmployeeRepository joins
        // crm_persons on person_id, so an employee is a crm_persons row with
        // HRM extension tables hanging off it. Deliberately NOT
        // 0_ksf_crm_contacts, which is the module's own flat duplicate.
        'contact' => array(
            'title' => 'Contact',
            'table' => 'crm_persons',
            'pk' => 'id',
            'label' => 'name',
            'module' => '',
            'picker' => true,
            'multiple' => true,
        ),

        'meeting' => array(
            'title' => 'Meeting',
            'table' => 'ksf_crm_meetings',
            'pk' => 'id',
            'label' => 'meeting_name',
            'module' => 'ksf_FA_CRM',
            'picker' => true,
            'multiple' => true,
        ),

        'opportunity' => array(
            'title' => 'Opportunity',
            'table' => 'ksf_crm_opportunities',
            'pk' => 'id',
            'label' => 'opportunity_name',
            'module' => 'ksf_FA_CRM',
            'picker' => true,
            'multiple' => true,
        ),

        // ksf_FA_Contract. Not built yet: the type is registered so links can
        // be created through the API today, but picker is false until that
        // module ships 0_ksf_contract.
        'contract' => array(
            'title' => 'Contract',
            'table' => 'ksf_contract',
            'pk' => 'id',
            'label' => 'name',
            'module' => 'ksf_FA_Contract',
            'picker' => false,
            'multiple' => true,
        ),

        'project' => array(
            'title' => 'Project',
            'table' => 'fa_pm_projects',
            'pk' => 'project_id',
            'label' => 'name',
            'module' => 'ksf_FA_ProjectManagement',
            'picker' => true,
            'multiple' => true,
        ),

        'task' => array(
            'title' => 'Task',
            'table' => 'fa_pm_tasks',
            'pk' => 'task_id',
            'label' => 'name',
            'module' => 'ksf_FA_ProjectManagement',
            'picker' => true,
            'multiple' => true,
        ),

        // Any calendar entry, not just tasks: meetings, reminders, phone calls
        // and recurring entries all live in one table.
        'calendar' => array(
            'title' => 'Calendar Entry',
            'table' => 'fa_cal_entries',
            'pk' => 'id',
            'label' => 'title',
            'module' => 'ksf_FA_Calendar',
            'picker' => true,
            'multiple' => true,
        ),

        // FA core attachment row. Notes writes the row itself (reserving a
        // type_no outside FA's ST_* enum) and stores the bytes under
        // company_path()/attachments/, then links the id here. The FA viewer
        // at admin/attachments.php?vw=<id> reads by id with no type gate, so
        // downloads keep working.
        'attachment' => array(
            'title' => 'Attachment',
            'table' => 'attachments',
            'pk' => 'id',
            'label' => 'description',
            'module' => '',
            'picker' => true,
            'multiple' => true,
        ),
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
 * Return one entity-type definition, or null when the type is unknown.
 *
 * @param string $entity_type Registry key
 * @return array|null
 * @since 1.0.0
 */
function notes_entity_type($entity_type)
{
    $types = notes_entity_types();

    return isset($types[$entity_type]) ? $types[$entity_type] : null;
}

/**
 * Return the entity types the Notes UI should offer a chooser for.
 *
 * @return array<string, array>
 * @since 1.0.0
 */
function notes_pickable_entity_types()
{
    return array_filter(notes_entity_types(), function ($def) {
        return !empty($def['picker']);
    });
}

/**
 * The link_role values a note may use, per entity type.
 *
 * Roles are what let one note express "meeting with a client regarding a
 * project due to a contract" rather than a flat undifferentiated bag of
 * references.
 *
 * @return array<string, array<string, string>> Map of entity_type => (role => label)
 * @since 1.0.0
 */
function notes_link_roles()
{
    return array(
        'customer' => array(
            'regarding' => 'Regarding',
            'with' => 'With',
        ),
        'contact' => array(
            'with' => 'With',
            'regarding' => 'Regarding',
        ),
        'meeting' => array(
            'regarding' => 'Regarding',
            'with' => 'Attended With',
        ),
        'opportunity' => array('regarding' => 'Regarding'),
        'contract' => array('regarding' => 'Regarding'),
        'project' => array('regarding' => 'Regarding'),
        'task' => array('regarding' => 'Regarding'),
        'calendar' => array('regarding' => 'Regarding'),
        'attachment' => array('document' => 'Document'),
    );
}

/**
 * Return the roles valid for one entity type.
 *
 * @param string $entity_type Registry key
 * @return array<string, string> Map of role => label; empty when unknown
 * @since 1.0.0
 */
function notes_link_roles_for($entity_type)
{
    $roles = notes_link_roles();

    return isset($roles[$entity_type]) ? $roles[$entity_type] : array();
}