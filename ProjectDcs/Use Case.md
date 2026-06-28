# Use Cases - ksf_FA_Notes

## Overview
ksf_FA_Notes provides the FrontAccounting UI for ksf_Notes.

## Note vs Document Clarification

### Notes (ksf_Notes)
Quick text entries with optional attachments:
- "Quick note about customer call"
- "Meeting summary"
- Can attach supporting file (optional)

### Documents (ksf_Documents)
File-centric records:
- "Contract.pdf" with description "Signed service agreement"
- "ID_Scan.jpg" with description "Government ID for compliance"
- Primary purpose is the file

---

## UC-FA-NT-001: Add Note to Customer
**Actor**: Sales Rep (FA User)

**FA-Specific Flow**:
1. Open customer in FA-CRM
2. Click "Add Note" tab
3. Enter note text
4. Optionally attach file
5. Optionally check "Send to OCR" (if file attached)
6. Optionally add additional entity links (project, ticket, etc.)
7. Save → stored in `fa_notes`
8. File stored in `fa_attachments` (if provided)
9. OCR text appended to note body (if OCR checked)

---

## UC-FA-NT-002: Quick Note Entry
**Actor**: Any FA User

**FA-Specific Flow**:
1. Use global "Quick Note" in header
2. Enter note
3. Link to entity (customer, project)
4. Optionally add additional entity links
5. Save

---

## UC-FA-NT-003: Upload File Attachment to Note
**Actor**: Any FA User

**Precondition**: User is creating or editing a note.

**FA-Specific Flow**:
1. Begin creating or editing a note
2. Click "Choose File" button in the note form
3. Select a file from local filesystem
4. Optionally check "Send to OCR" checkbox (enabled only when file is selected)
5. Submit the form
6. System stores the file via ksf_FA_Attachments (`fa_attachments.source_type = 'note'`)
7. File download link appears in the notes panel
8. User can click the link to download the original file

**Alternative Flow (No File)**:
1. User submits note without selecting a file
2. Note is saved with text only
3. No attachment reference created

**Postcondition**: File is stored and associated with the note. The note panel displays a download link for the attachment.

---

## UC-FA-NT-004: Send Attached Image to OCR
**Actor**: Any FA User

**Precondition**: User has selected a file for upload and checked "Send to OCR".

**FA-Specific Flow**:
1. User attaches an image file to a note
2. User checks "Send to OCR" checkbox
3. User submits the form
4. System stores original file as attachment (step 1)
5. System sends file via cURL to OCR service at `http://ksf-tesseract:8100/ocr`
6. OCR service returns JSON with extracted text
7. System saves extracted text as a `.txt` file via ksf_FA_Attachments
8. System appends OCR text to note body with delimiter `--- OCR Text ---`
9. Note panel shows:
   - Original file download link
   - OCR text file download link
   - Note text including OCR output (full body searchable)

**Alternative Flow (OCR Service Unreachable)**:
1. cURL connection fails or times out
2. Note and original file are saved successfully
3. User sees warning: "File saved but OCR processing failed"
4. Note body does not contain OCR text
5. Error is logged for administrative review

**Alternative Flow (OCR Returns Empty Text)**:
1. OCR service responds with 200 but empty/blank text
2. Note and file are saved
3. Warning displayed: "OCR returned no text"
4. No OCR text file attachment created

**Postcondition**: Either OCR text is appended to the note and stored as attachment, or a graceful failure notification is shown.

---

## UC-FA-NT-005: Link Note to Multiple Entities
**Actor**: Any FA User

**Precondition**: User is creating or editing a note.

**FA-Specific Flow**:
1. User begins creating a new note
2. User selects primary entity (existing behavior — `entity_id`/`entity_type`)
3. User clicks "Add Link" in the "Additional Links" section
4. System shows entity type picker (Customer, Project, Ticket, etc.)
5. User selects entity type
6. System shows entity search/selector for that type
7. User selects the specific entity
8. Entity link is added to the list (tag/chip style display)
9. User repeats steps 3-8 to add more links
10. User fills in note text and submits
11. System stores primary link on `fa_crm_notes.entity_id`/`entity_type`
12. System stores additional links in `fa_note_links` table
13. Note appears in the notes panel of ALL linked entities

**Edit Flow**:
1. User clicks "Edit" on an existing note
2. Additional links are displayed in the "Additional Links" section
3. User can add new links or remove existing ones (click "X" on a link chip)
4. On save, removed links are deleted from `fa_note_links`, new ones are inserted
5. The primary link on `fa_crm_notes` remains editable via entity_id/entity_type fields

**Postcondition**: The note is associated with all selected entities. It is discoverable from each entity's notes panel.

---

## UC-FA-NT-006: Set Note Owner/Group Permissions
**Actor**: Admin User (with SA_ksf_FA_NotesMANAGE privilege)

**Precondition**: User has admin-level permissions.

**FA-Specific Flow**:
1. User creates a new note or edits an existing one
2. In the "Permissions" section (visible only to admin users), user sees:
   - **Owner**: Dropdown/autocomplete to select an FA user
   - **Group**: Dropdown to select an access group (future RBAC)
3. By default:
   - Owner = current user (for new notes)
   - Group = empty (no restriction)
4. Admin can change owner to any FA user
5. Admin can optionally assign a group
6. User saves the note
7. ACL is enforced on subsequent view/edit/delete operations

**Non-Admin Flow**:
1. Standard user creates a note
2. Permissions section is not visible
3. Owner is automatically set to the current user
4. Group is set to NULL (unrestricted viewing)

**Postcondition**: Note has an owner and optional group assignment. ACL rules apply to future operations.

---

## Use Case Dependency Map

| UC ID | Title | Depends On | FR ID(s) | BR ID(s) |
|-------|-------|------------|----------|----------|
| UC-FA-NT-001 | Add Note to Customer | — | FR-NT-001, FR-NT-002, FR-NT-003, FR-NT-005 | BR-NT-001–003, BR-NT-005 |
| UC-FA-NT-002 | Quick Note Entry | — | FR-NT-001, FR-NT-005 | BR-NT-001, BR-NT-005 |
| UC-FA-NT-003 | Upload File Attachment to Note | UC-FA-NT-001 | FR-NT-008 | BR-NT-008 |
| UC-FA-NT-004 | Send Attached Image to OCR | UC-FA-NT-003 | FR-NT-009, FR-NT-010, FR-NT-011 | BR-NT-009 |
| UC-FA-NT-005 | Link Note to Multiple Entities | UC-FA-NT-001 | FR-NT-006 | BR-NT-006 |
| UC-FA-NT-006 | Set Note Owner/Group Permissions | UC-FA-NT-001 | FR-NT-007 | BR-NT-007 |

## Reference Use Cases
- Core UC: ksf_Notes/ProjectDcs/Use Case.md (UC-NT-001 through UC-NT-010)

*Document Version: 2.0.0*
*Last Updated: 2026-06-28*
