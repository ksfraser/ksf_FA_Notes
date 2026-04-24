<?php
/**
 * Notes Management Page
 */

$page_security = 'SA_CUSTOMER';
$path_to_root = "../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/FA_Notes/includes/notes_db.inc");

page(_("Notes Management"));

$selected_id = get_post('note_id', '');
$entity_filter = $_POST['entity_type'] ?? '';
$action = $_POST['action'] ?? '';

//--------------------------------------------------------------------------------

if ($action === 'delete' && $selected_id) {
    delete_note($selected_id);
    display_notification(_("Note deleted"));
}

if (isset($_POST['add_note']) || isset($_POST['save_note'])) {
    $entity_id = $_POST['entity_id'];
    $entity_type = $_POST['entity_type'];
    $note = $_POST['note'];
    $note_type = $_POST['note_type'] ?? 'Comment';
    
    if ($entity_id && $entity_type && $note) {
        add_note($entity_id, $entity_type, $note, $note_type);
        display_notification(_("Note added"));
    }
}

if (isset($_POST['update_note'])) {
    $note_id = $_POST['note_id'];
    $note = $_POST['note'];
    $note_type = $_POST['note_type'] ?? null;
    
    update_note($note_id, $note, $note_type);
    display_notification(_("Note updated"));
}

//--------------------------------------------------------------------------------

start_form();

start_table(TABLESTYLE, "width=50%");
table_section_title(_("Search Notes"));

text_row_ex(_("Keyword:"), 'keyword', 30);
select_row(_("Entity Type:"), 'entity_type', $entity_filter, [
    '' => _('All'),
    'debtor' => _('Customer'),
    'contact' => _('Contact'),
    'opportunity' => _('Opportunity'),
    'ticket' => _('Ticket'),
    'call_log' => _('Call Log'),
    'lead' => _('Lead'),
]);
submit_row('search', _("Search"), true);

end_table();

end_form();

//--------------------------------------------------------------------------------

if (isset($_POST['search'])) {
    $keyword = $_POST['keyword'];
    $entity_type = $_POST['entity_type'];
    
    echo '<h3>' . _('Search Results') . '</h3>';
    
    $notes = search_notes($keyword, $entity_type);
    
    if (empty($notes)) {
        echo '<p>' . _('No notes found') . '</p>';
    } else {
        start_table(TABLESTYLE, "width=90%");
        table_header([
            _("Entity"), _("Type"), _("Note"), _("Created By"), _("Date"), _("Actions")
        ]);
        
        foreach ($notes as $note) {
            $entity_label = $note['entity_type'] . ' #' . $note['entity_id'];
            
            label_cell($entity_label);
            label_cell($note['note_type']);
            label_cell(mb_substr($note['note'], 0, 100) . (mb_strlen($note['note']) > 100 ? '...' : ''));
            label_cell($note['created_by']);
            label_cell(sql2date($note['created_at']));
            
            $delete_url = "?note_id=" . $note['id'] . "&action=delete";
            delete_button_center($delete_url, _("Delete"));
            
            end_row();
        }
        
        end_table();
    }
}

end_page();