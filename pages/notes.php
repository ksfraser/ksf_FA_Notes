<?php
declare(strict_types=1);

use ksfraser\FrontAccounting\Notes\DTO\NotesFilter;
use ksfraser\FrontAccounting\Notes\Entity\EntityOptionsProvider;
use ksfraser\FrontAccounting\Notes\View\NoteFormView;
use ksfraser\FrontAccounting\Notes\View\NoteSummaryTableView;
use ksfraser\FrontAccounting\RelatedTo\UI\RelatedRecordsRenderer;

error_reporting(E_ALL);
ini_set("display_errors", "1");

/**
 * Notes page.
 *
 * A dispatcher and nothing else: it decides which view to show, enforces the
 * security areas, and hands the view classes plain data. All rendering lives in
 * src/Ksfraser/FA/Notes/View, all SQL in includes/ksf_notes_*.inc.
 *
 * Routes:
 *   (default)                    summary table
 *   view=summary                 summary table
 *   view=form                    data-entry form for a new note
 *   view=form&edit_id=<id>       data-entry form for an existing note
 *   action=create|update         POST from the form
 *   action=unlink                remove one attachment
 *   action=delete                delete a note
 */

$page_security = 'SA_NOTES_VIEW';
$path_to_root = '../../..';

try {
    include_once($path_to_root . '/includes/session.inc');
    add_access_extensions();

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        display_error(_('Notes dependencies are not installed'));
        exit;
    }
    require_once $autoload;

    require_once __DIR__ . '/../includes/entity_types.inc';
    require_once __DIR__ . '/../includes/common.inc';
    require_once __DIR__ . '/../includes/ksf_notes_db.inc';
    require_once __DIR__ . '/../includes/ksf_notes_link_db.inc';

    //--------------------------------------------------------------------------------
    // Wiring, shared by both views.

    $options = new EntityOptionsProvider(notes_entity_types());
    $currentUserId = (string) ($_SESSION['wa_current_user']->loginname ?? '');
    $currentUserName = notes_current_user_name();

    //--------------------------------------------------------------------------------
    // Ajax endpoint: entity options for the filter-bar cascading select.
    // Returns a JSON array of {id, name} for one entity type and exits, so no page
    // HTML is emitted. Used by notesFetchEntityOptions() in the filter bar.
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'entity_options') {
        header('Content-Type: application/json; charset=utf-8');
        $type = isset($_GET['type']) ? (string) $_GET['type'] : '';
        $optionsList = array();
        foreach ($options->optionsFor($type) as $id => $label) {
            $optionsList[] = array('id' => (string) $id, 'name' => (string) $label);
        }
        echo json_encode($optionsList);
        exit;
    }

    $view = notes_page_param('view', 'summary');
    $action = notes_page_param('action', '');

    //--------------------------------------------------------------------------------
    // Mutating routes. Each one checks SA_NOTES_MANAGE rather than trusting that
    // the summary table hid the button.

    if ($action !== '') {
        if (!$_SESSION['wa_current_user']->can_access('SA_NOTES_MANAGE')) {
            display_error(_('You do not have permission to change notes'), -1);
            exit;
        }

        $noteId = (int) notes_page_param('edit_id', notes_page_param('note_id', 0));

        if ($action === 'delete') {
            if ($noteId > 0 && notes_delete($noteId)) {
                display_notification(_('Note deleted'));
            }
            $view = 'summary';
        } elseif ($action === 'unlink') {
            $removed = notes_link_delete(
                $noteId,
                notes_page_param('unlink_type', ''),
                notes_page_param('unlink_id', ''),
                notes_page_param('unlink_role', '') ?: null
            );
            display_notification($removed ? _('Attachment removed') : _('Attachment was not found'));
            $view = 'form';
        } elseif (strpos($action, 'associate_') === 0) {
            $noteId = (int) notes_page_param('edit_id', notes_page_param('note_id', 0));

            if ($action !== 'associate_record') {
                display_error(_('Unknown association action.'));
                $view = 'form';
            } else {
                $type = notes_page_param('assoc_type', '');
                $entityId = notes_page_param('assoc_record', '');

                if ($noteId > 0 && $type !== '' && $entityId !== '') {
                    notes_link_add(array(
                        'note_id' => $noteId,
                        'entity_type' => $type,
                        'entity_id' => $entityId,
                        'link_role' => null,
                    ));
                    display_notification(_('Record associated'));
                } elseif ($noteId <= 0) {
                    display_error(_('Save the note before associating records.'));
                }

                $view = 'form';
            }
        } elseif ($action === 'create' || $action === 'update') {
            $owner = notes_page_param('owner', '');
            $data = array(
                'subject' => notes_page_param('subject', ''),
                'note' => notes_page_param('notes_note', ''),
                'note_type' => notes_page_param('note_type', ''),
                'owner' => $owner,
                'inactive' => notes_page_param('inactive', '') !== '' ? 1 : 0,
            );
            if ($owner !== '') {
                $data['owner'] = $owner;
            }

            if ($action === 'create') {
                // A new note belongs to whoever wrote it unless they named someone
                // else; the "my notes" owner filter keys off this value.
                if ($owner === '') {
                    $data['owner'] = $currentUserId;
                }
                // Record the author once, at creation; notes_update() never touches
                // created_by, so the original author survives later edits.
                $data['created_by'] = $currentUserId;

                $newId = notes_write($data);
                if ($newId > 0) {
                    display_notification(_('Note created'));
                    header('Location: notes.php?view=form&edit_id=' . $newId);
                    exit;
                } else {
                    display_error(_('Could not create the note'));
                }
            } else {
                $updated = notes_update($noteId, $data);
                if ($updated !== false) {
                    display_notification(_('Note updated'));
                }
            }

            $view = 'form';
        }
    }

    //--------------------------------------------------------------------------------
    // Summary table. Opens on the logged-in user's own notes.

    if ($view === 'summary') {
        page(_('Notes'));

        $filter = NotesFilter::fromRequest($currentUserId);
        $notes = notes_get_all($filter->toCriteria());

        foreach ($notes as $key => $note) {
            $notes[$key]['links'] = notes_link_resolve(
                notes_link_get_by_note((int) $note['id'])
            );
        }

        $owners = notes_page_owner_options($currentUserId);

        (new NoteSummaryTableView(
            $options,
            $notes,
            $filter,
            $currentUserId,
            $currentUserName,
            $owners
        ))->render();

        end_page();
        exit;
    }

    //--------------------------------------------------------------------------------
    // Data-entry form.

    page(_('Note'));

    $noteId = (int) notes_page_param('edit_id', notes_page_param('note_id', 0));
    $note = $noteId > 0 ? notes_get($noteId) : null;

    if ($noteId > 0 && !$note) {
        display_error(_('That note does not exist'));
        exit;
    }

    $formView = new NoteFormView($options, notes_entity_types(), 'notes.php?view=form', array(), $moduleTypeLabels);

    // Wire up the RelatedTo SummaryTableView-style renderer for the related-records table.
    $renderer = new RelatedRecordsRenderer();
    $formView->setRenderer($renderer);

    $links = $noteId > 0
        ? notes_link_get_by_note($noteId)
        : array();

    echo $formView->render($note, $links);

    end_page();
} catch (\Exception $e) {
    display_error(_('An error occurred: ') . $e->getMessage());
    exit;
} catch (\Throwable $e) {
    display_error(_('An error occurred: ') . $e->getMessage());
    exit;
}
