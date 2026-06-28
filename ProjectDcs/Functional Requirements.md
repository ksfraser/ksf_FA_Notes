# Functional Requirements - ksf_FA_Notes

## Overview

This document defines all functional requirements for the ksf_FA_Notes module, including existing functionality and new features for multi-entity linking, access control, file upload, and OCR integration.

Each requirement follows the format: **FR-NT-NNN: Title** with associated acceptance criteria.

---

## FR-NT-001: Create Note

**Description**: Authenticated users can create a new text note.

**Trigger**: User submits the note creation form.

**Preconditions**:
- User is authenticated in FA
- User has `SA_ksf_FA_NotesVIEW` security area access

**Postconditions**:
- A new row is inserted into `fa_crm_notes`
- The note is visible in the entity's notes panel
- `note_added` event is dispatched

**Acceptance Criteria**:
1. User enters note text and selects a note type (Comment, Internal, Public)
2. User optionally sets an entity link via entity_id/entity_type
3. User submits the form
4. System validates required fields (note text, entity_id, entity_type)
5. On success, note appears in the entity's notes list
6. On failure, error message is displayed

---

## FR-NT-002: View Notes

**Description**: Users can view notes associated with a specific entity.

**Trigger**: User navigates to an entity detail page with notes panel.

**Preconditions**:
- Entity exists in the system
- User has `SA_ksf_FA_NotesVIEW` security area access

**Postconditions**:
- Notes are displayed sorted by `created_at DESC`

**Acceptance Criteria**:
1. Notes panel shows all notes for the entity
2. Notes from both `fa_crm_notes.entity_id` and `fa_note_links` are merged and deduplicated
3. Each note shows date, type, text (truncated if long), and creator
4. Empty state message when no notes exist

---

## FR-NT-003: Edit and Delete Notes

**Description**: Users can edit or delete notes they own. Administrators can edit or delete any note.

**Trigger**: User clicks Edit or Delete button on a note.

**Preconditions**:
- Note exists
- User is the `owner` of the note OR has `SA_ksf_FA_NotesMANAGE` privilege

**Postconditions**:
- Edit: note text/type is updated in `fa_crm_notes`
- Delete: row is removed from `fa_crm_notes`, related links from `fa_note_links`, and attachments from `fa_attachments`
- Events `note_updated` or `note_deleted` are dispatched

**Acceptance Criteria**:
1. Edit button is visible only for authorized users
2. Delete button is visible only for authorized users
3. Unauthorized users see the note in read-only mode
4. Edit preserves created_at and created_by; updated_at is refreshed
5. Delete cascades to linked entities and attachments

---

## FR-NT-004: Search Notes

**Description**: Users can search notes by keyword across entities or filtered by entity type.

**Trigger**: User enters a keyword in the notes search form.

**Preconditions**:
- User has `SA_ksf_FA_NotesVIEW` security area access

**Postconditions**:
- Matching notes are displayed in search results

**Acceptance Criteria**:
1. Search returns notes where note text contains the keyword (LIKE %keyword%)
2. Optional entity_type filter narrows results
3. Results limited to configurable count (default 50)
4. Search respects ACL — users only see notes they are permitted to view
5. Empty results display appropriate message

---

## FR-NT-005: Link Note to Primary Entity

**Description**: Notes are linked to a primary entity via `entity_id` and `entity_type` columns on `fa_crm_notes`.

**Trigger**: Note creation or edit form submission.

**Preconditions**:
- Entity exists in the referenced FA module

**Postconditions**:
- `fa_crm_notes.entity_id` and `fa_crm_notes.entity_type` are set

**Acceptance Criteria**:
1. Primary entity type maps to valid FA entity types (debtor, contact, opportunity, ticket, call_log, lead)
2. Notes are retrievable via `get_notes(entity_id, entity_type)`
3. Backward compatible with existing data

---

## FR-NT-006: Link Note to Multiple Entities

**Description**: Notes can be linked to multiple entities via the `fa_note_links` table.

**Trigger**: Note creation or edit — user adds additional entity associations.

**Preconditions**:
- Note exists (for edit) or is being created
- Referenced entities exist in their respective modules

**Postconditions**:
- Rows inserted into `fa_note_links` for each additional entity association
- `note_linked` event dispatched for each link

**Acceptance Criteria**:
1. User can select one or more additional entities when creating or editing a note
2. Entity selection shows type picker (debtor, project, ticket, etc.) then entity search/select
3. When viewing entity notes, notes linked via `fa_note_links` appear alongside primary-linked notes
4. Results are merged and deduplicated by note_id
5. Deleting a note cascades to remove all rows in `fa_note_links` for that note_id
6. API functions: `link_note_to_entity()`, `unlink_note_from_entity()`, `unlink_all_note_links()`, `get_linked_entities()`, `get_notes_for_entity()`

---

## FR-NT-007: ACL Enforcement on Notes

**Description**: Notes support ownership and group-level access control.

**Trigger**: Any note view, edit, or delete operation.

**Preconditions**:
- Note exists
- `owner` and/or `group_id` may be set on the note

**Postconditions**:
- Access is granted or denied based on ACL rules

**Acceptance Criteria**:
1. **View**: Users can view a note if any of: `owner IS NULL`, user is the `owner`, user is member of `group_id`, or user has `SA_ksf_FA_NotesMANAGE` privilege
2. **Edit/Delete**: Only the `owner` or users with `SA_ksf_FA_NotesMANAGE` privilege
3. Owner is automatically set to the creating user on note creation (if not explicitly overridden by admin)
4. `group_id` is optional and defaults to NULL (no group restriction)
5. ACL checks are enforced server-side in notes_db.inc; UI controls are hidden client-side for unauthorized actions
6. Admin users (`SA_ksf_FA_NotesMANAGE`) can reassign `owner` and `group_id`

---

## FR-NT-008: File Upload on Note Form

**Description**: The note entry form includes a file upload input for attaching files to notes.

**Trigger**: User selects a file and submits the note form.

**Preconditions**:
- Note is being created or edited
- ksf_FA_Attachments module is installed and active

**Postconditions**:
- File is stored on the filesystem via ksf_FA_Attachments
- Row inserted into `fa_attachments` with `source_type = 'note'` and `source_id = <note_id>`
- `note_file_attached` event dispatched

**Acceptance Criteria**:
1. File upload input is present on the note form (add and edit modes)
2. Accepted file types: any (no server-side restriction); client-side accepts standard document/image types
3. File size is limited by PHP `upload_max_filesize` and `post_max_size`
4. On note creation, the note is saved first, then the file is associated
5. Multiple files per note: supported in UI (add more); each file creates a separate attachment row
6. Attached files are displayed in the notes panel with download links
7. Deleting a note cascades to delete associated attachments
8. The form POST is `multipart/form-data` when file upload is enabled

---

## FR-NT-009: OCR Processing of Uploaded Image

**Description**: When "Send to OCR" is checked, the uploaded image file is sent to the OCR service and the extracted text is stored.

**Trigger**: User checks "Send to OCR" checkbox and submits the note form with a file attachment.

**Preconditions**:
- A file is attached to the note
- OCR checkbox is checked
- OCR service may be available or unavailable

**Postconditions**:
- Image is sent to OCR service via cURL
- OCR text response is saved as a .txt file via ksf_FA_Attachments
- OCR text is appended to the note body
- `note_ocr_completed` event dispatched

**Acceptance Criteria**:
1. OCR checkbox is disabled/hidden when no file is attached
2. OCR checkbox is enabled when a file is selected
3. When OCR is checked:
   a. File is stored as attachment (step 1)
   b. cURL POST to OCR service URL with the file
   c. OCR response (JSON with extracted text) is parsed
   d. Extracted text is saved as a .txt file via ksf_FA_Attachments
   e. OCR text is appended to note body with delimiter `--- OCR Text ---`
   f. Both attachment references (original file + OCR text) are linked to the note
4. When OCR is unchecked:
   a. File is stored as attachment
   b. No OCR processing occurs
5. OCR service timeout: cURL timeout of 30 seconds; if unreachable, log error and proceed without OCR
6. OCR service URL is configurable via constant or module setting
7. Supported image formats for OCR: JPEG, PNG, TIFF, BMP (as supported by tesseract)

---

## FR-NT-010: Store OCR Text as Attachment

**Description**: The extracted OCR text is stored as a .txt file attachment associated with the note.

**Trigger**: OCR processing completes successfully.

**Preconditions**:
- OCR service returned valid extracted text

**Postconditions**:
- .txt file is stored via ksf_FA_Attachments
- `fa_attachments` has a second row for this note with the OCR text file
- The .txt filename follows the pattern: `ocr_{note_id}_{timestamp}.txt`

**Acceptance Criteria**:
1. The OCR text file is stored in the same attachment storage backend as the original file
2. MIME type is `text/plain`
3. The filename clearly indicates it is OCR output
4. The attachment is retrievable via `get_attachments_for_source('note', $note_id)`
5. The OCR text is also concatenated into the note body for search indexing

---

## FR-NT-011: Graceful Degradation for OCR

**Description**: The OCR feature degrades gracefully when the service is unavailable, misconfigured, or returns errors.

**Trigger**: OCR service is unreachable or returns an error response.

**Postconditions**:
- File attachment is stored normally
- Note is saved without OCR text
- Error is logged
- User is notified that file was saved but OCR processing failed

**Acceptance Criteria**:
1. cURL connection timeout of 10 seconds, total request timeout of 30 seconds
2. On connection failure: log warning, notify user, proceed without OCR
3. On OCR service error (non-200 response): log response, notify user, proceed without OCR
4. On OCR service returning empty text: log warning, notify user, proceed
5. The note is always saved regardless of OCR outcome

---

## Functional Requirements Traceability

| FR ID | Description | BR ID | Priority |
|-------|-------------|-------|----------|
| FR-NT-001 | Create Note | BR-NT-001 | High |
| FR-NT-002 | View Notes | BR-NT-002 | High |
| FR-NT-003 | Edit and Delete Notes | BR-NT-003 | High |
| FR-NT-004 | Search Notes | BR-NT-004 | Medium |
| FR-NT-005 | Link Note to Primary Entity | BR-NT-005 | High |
| FR-NT-006 | Link Note to Multiple Entities | BR-NT-006 | High |
| FR-NT-007 | ACL Enforcement on Notes | BR-NT-007 | High |
| FR-NT-008 | File Upload on Note Form | BR-NT-008 | High |
| FR-NT-009 | OCR Processing of Uploaded Image | BR-NT-009 | Medium |
| FR-NT-010 | Store OCR Text as Attachment | BR-NT-009 | Medium |
| FR-NT-011 | Graceful Degradation for OCR | BR-NT-009 | Medium |

*Document Version: 2.0.0*
*Last Updated: 2026-06-28*
