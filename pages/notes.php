<?php
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

use ksfraser\FrontAccounting\Notes\DTO\NotesFilter;
use ksfraser\FrontAccounting\Notes\Entity\EntityOptionsProvider;
use ksfraser\FrontAccounting\Notes\View\NoteFormView;
use ksfraser\FrontAccounting\Notes\View\NoteSummaryTableView;

//--------------------------------------------------------------------------------
// Wiring, shared by both views.

$options = new EntityOptionsProvider(notes_entity_types());
$noteTypes = notes_note_types();
$currentUserId = (string) ($_SESSION['wa_current_user']->loginname ?? '');
$currentUserName = notes_current_user_name();

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
    } elseif ($action === 'create' || $action === 'update') {
        $owner = notes_page_param('owner', '');
        $data = array(
            'subject' => notes_page_param('subject', ''),
            'note' => notes_page_param('notes_note', ''),
            'note_type' => notes_page_param('note_type', ''),
            'owner' => $owner,
            'inactive' => notes_page_param('inactive_present', '') !== '' ? 1 : 0,
        );

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
                notes_page_apply_links($newId, $options);
                display_notification(_('Note created'));
            } else {
                display_error(_('Could not create the note'));
            }
        } else {
            $updated = notes_update($noteId, $data);
            if ($updated !== false) {
                notes_page_apply_links($noteId, $options);
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
        $noteTypes,
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

$noteId = (int) notes_page_param('edit_id', 0);
$note = $noteId > 0 ? notes_get($noteId) : null;

if ($noteId > 0 && !$note) {
    display_error(_('That note does not exist'));
    exit;
}

$formView = new NoteFormView($options, notes_entity_types(), $noteTypes, 'notes.php?view=form');

echo $formView->currentLinks(
    $noteId > 0 ? notes_link_resolve(notes_link_get_by_note($noteId)) : array(),
    $noteId
);

echo $formView->render($note, $noteId > 0 ? notes_link_get_by_note($noteId) : array());

end_page();

/**
 * Read a scalar request parameter from POST, falling back to GET.
 *
 * @param string $name    Parameter name
 * @param mixed  $default Returned when absent
 * @return string
 */
function notes_page_param(string $name, $default = '')
{
    if (isset($_POST[$name]) && is_scalar($_POST[$name])) {
        return trim((string) $_POST[$name]);
    }

    if (isset($_GET[$name]) && is_scalar($_GET[$name])) {
        return trim((string) $_GET[$name]);
    }

    return (string) $default;
}

/**
 * The logged-in user's display name: real_name when set, otherwise the login
 * name. FA exposes these on $_SESSION['wa_current_user']; there is no
 * global user_name() helper in this FA build.
 *
 * @return string
 */
function notes_current_user_name()
{
    $user = $_SESSION['wa_current_user'];
    $name = trim((string) (@$user->name));

    return $name !== '' ? $name : (string) (@$user->loginname);
}

/**
 * Save the attachments chosen in the form, adding new ones and dropping the
 * ones the user cleared.
 *
 * @param int                    $noteId Note id
 * @param EntityOptionsProvider  $options Options provider, for the pickable types
 * @return void
 */
function notes_page_apply_links(int $noteId, EntityOptionsProvider $options)
{
    foreach ($options->pickableTypes() as $type) {
        $field = 'link_' . $type;
        $chosen = isset($_POST[$field]) && is_array($_POST[$field])
            ? array_map('strval', $_POST[$field])
            : array();

        $chosen = array_values(array_filter($chosen, function ($id) {
            return $id !== '';
        }));

        $role = notes_page_param('role_' . $type, '') ?: null;

        foreach ($chosen as $entityId) {
            if (!notes_link_find($noteId, $type, $entityId, $role)) {
                notes_link_add(array(
                    'note_id' => $noteId,
                    'entity_type' => $type,
                    'entity_id' => $entityId,
                    'link_role' => $role,
                ));
            }
        }

        $existing = notes_link_get_by_note($noteId);
        foreach ($existing as $link) {
            // Null role, not the form's role: a link may have been saved under a
            // different role, and the user cleared the record from the list, so
            // every role for it has to go.
            if ($link['entity_type'] === $type && !in_array((string) $link['entity_id'], $chosen, true)) {
                notes_link_delete($noteId, $type, (string) $link['entity_id']);
            }
        }
    }
}

/**
 * The owner dropdown: everyone who owns at least one note.
 *
 * @param string $currentUserId Logged-in user id
 * @return array<string, string>
 */
function notes_page_owner_options(string $currentUserId): array
{
    $owners = array();

    if ($currentUserId !== '') {
        $owners[$currentUserId] = notes_current_user_name();
    }

    foreach (notes_sql_owners() as $owner) {
        if ($owner !== '' && !isset($owners[$owner])) {
            $owners[$owner] = $owner;
        }
    }

    return $owners;
}