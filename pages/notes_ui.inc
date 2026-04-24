<?php
/**
 * Notes UI Component - Reusable for any entity
 */

function display_notes_panel($entity_id, $entity_type, $title = '', $readonly = false)
{
    global $path_to_root;
    
    include_once $path_to_root . "/modules/FA_Notes/includes/notes_db.inc";
    
    $notes = get_notes($entity_id, $entity_type);
    $types = ['Comment' => _('Comment'), 'Internal' => _('Internal'), 'Public' => _('Public')];
    
    if (!$readonly) {
        start_form();
        
        echo '<div class="notes-panel">';
        echo '<h4>' . ($title ?: _('Notes')) . '</h4>';
        
        start_table(TABLESTYLE, "width=100%");
        textarea_row(_("Add Note:"), 'new_note', '', 50, 4);
        
        $note_types = ['Comment' => _('Comment'), 'Internal' => _('Internal'), 'Public' => _('Public')];
        select_row(_("Type:"), 'new_note_type', 'Comment', $note_types);
        end_table();
        
        submit_center('add_note', _("Add Note"), true);
        end_form();
        
        echo '<hr>';
    }
    
    echo '<div class="notes-list">';
    echo '<h4>' . _('Existing Notes') . ' (' . count($notes) . ')</h4>';
    
    if (empty($notes)) {
        echo '<p class="no-notes">' . _('No notes yet') . '</p>';
    } else {
        start_table(TABLESTYLE, "width=100%");
        table_header([
            _("Date"), _("Type"), _("Note"), _("By"), _("Actions")
        ]);
        
        foreach ($notes as $note) {
            label_cell(sql2date($note['created_at']));
            label_cell($note['note_type']);
            label_cell($note['note']);
            label_cell($note['created_by']);
            
            if (!$readonly) {
                echo '<td>';
                echo '<button onclick="Notes.edit(' . $note['id'] . ')">' . _('Edit') . '</button> ';
                echo '<button onclick="Notes.delete(' . $note['id'] . ')">' . _('Delete') . '</button>';
                echo '</td>';
            }
            end_row();
        }
        
        end_table();
    }
    
    echo '</div>';
}

function display_notes_inline($entity_id, $entity_type)
{
    global $path_to_root;
    
    include_once $path_to_root . "/modules/FA_Notes/includes/notes_db.inc";
    
    $notes = get_notes($entity_id, $entity_type);
    
    if (empty($notes)) {
        return '<span class="no-notes">' . _('No notes') . '</span>';
    }
    
    $html = '<div class="notes-inline">';
    $html .= '<span class="note-count">' . count($notes) . ' ' . _('notes') . '</span>';
    $html .= '</div>';
    
    return $html;
}

function display_add_note_form($entity_id, $entity_type, $return_to = '')
{
    $note_types = ['Comment' => _('Comment'), 'Internal' => _('Internal'), 'Public' => _('Public')];
    
    start_form();
    
    hidden('entity_id', $entity_id);
    hidden('entity_type', $entity_type);
    hidden('return_to', $return_to);
    
    start_table(TABLESTYLE, "width=100%");
    textarea_row(_("Note:"), 'note', '', 50, 5);
    select_row(_("Type:"), 'note_type', 'Comment', $note_types);
    end_table();
    
    submit_center('save_note', _("Save Note"), true);
    
    end_form();
}