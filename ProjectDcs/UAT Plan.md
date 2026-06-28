# User Acceptance Test (UAT) Plan - ksf_FA_Notes

## Overview

This UAT plan validates that ksf_FA_Notes v2.0.0 meets business requirements for multi-entity linking, access control, file upload, and OCR integration. Tests are performed by business stakeholders in the UAT environment against the bind point at `~/ksf_Infrastructure/fa_modules/ksf_FA_Notes`.

## UAT Environment

| Component | Configuration |
|-----------|---------------|
| FrontAccounting | 2.4+ (UAT instance) |
| ksf_FA_Notes | v2.0.0 deployed to UAT bind point |
| ksf_FA_Attachments | Installed and active |
| OCR Service | ksf-tesseract container at http://ksf-tesseract:8100/ocr |
| Test Users | Alice (standard), Bob (standard), Charlie (admin) |
| Test Entities | Pre-loaded customers, projects, tickets |

## Test Accounts

| User | Role | Notes |
|------|------|-------|
| `alice` | Standard user (SA_ksf_FA_NotesVIEW) | Can view and create notes, edit own |
| `bob` | Standard user (SA_ksf_FA_NotesVIEW) | Same as Alice |
| `charlie` | Admin (SA_ksf_FA_NotesMANAGE) | Can view, create, edit, delete all notes |

---

## UAT Test Cases

### UAT-NT-001: Basic Note CRUD

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-001 |
| **BR** | BR-NT-001, BR-NT-002, BR-NT-003 |
| **Actor** | Alice |

**Steps**:
1. Log in as Alice
2. Navigate to a customer record
3. Click "Add Note" tab
4. Enter note text: "Called customer about Q4 renewal"
5. Select type: "Comment"
6. Click "Add Note"
7. Verify note appears in the notes panel
8. Click "Edit" on the note
9. Change text to "Called customer about Q4 renewal — leaving voicemail"
10. Save
11. Verify updated text is displayed
12. Click "Delete" on the note
13. Verify note is removed from the panel

**Expected Result**: All CRUD operations complete successfully. Note appears, updates, and deletes correctly.

**Sign-off**: ☐ Pass / ☐ Fail

---

### UAT-NT-002: Multi-Entity Linking

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-002 |
| **BR** | BR-NT-006 |
| **Actor** | Alice |

**Steps**:
1. Log in as Alice
2. Create a new note with primary entity set to Customer "Acme Corp"
3. In the "Additional Links" section, add:
   - Entity type: "Project", Entity: "Q4 Implementation"
   - Entity type: "Ticket", Entity: "SUP-0042 — Server migration"
4. Save the note with text: "Reviewed quote, project scope, and support ticket status"
5. Navigate to Customer "Acme Corp" → verify note appears in notes panel
6. Navigate to Project "Q4 Implementation" → verify same note appears (deduplicated)
7. Navigate to Ticket "SUP-0042" → verify same note appears (deduplicated)
8. Edit the note, remove the Ticket link
9. Verify the note no longer appears in Ticket SUP-0042's notes panel
10. Verify the note still appears in Customer and Project panels

**Expected Result**: Note is visible in all three entity views. Removing a link removes it from that entity only.

**Sign-off**: ☐ Pass / ☐ Fail

---

### UAT-NT-003: ACL — Owner Can Edit, Others Can View

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-003 |
| **BR** | BR-NT-007 |
| **Actor** | Alice, Bob |

**Steps**:
1. Log in as Alice
2. Create a note on a customer (note becomes owned by Alice)
3. Log out
4. Log in as Bob
5. Navigate to the same customer
6. Verify Alice's note is visible (viewable)
7. Verify there is NO Edit or Delete button on Alice's note
8. Attempt to directly POST an edit to the note (if technically feasible) → verify rejection

**Expected Result**: Bob can view Alice's note but cannot edit or delete it.

**Sign-off**: ☐ Pass / ☐ Fail

---

### UAT-NT-004: ACL — Admin Bypasses Owner Restriction

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-004 |
| **BR** | BR-NT-007 |
| **Actor** | Alice, Charlie |

**Steps**:
1. Log in as Alice
2. Create a note
3. Log out
4. Log in as Charlie (admin)
5. Navigate to the entity
6. Verify Edit and Delete buttons are present on Alice's note
7. Edit the note → verify success
8. Delete the note → verify success

**Expected Result**: Admin user can edit and delete any note regardless of owner.

**Sign-off**: ☐ Pass / ☐ Fail

---

### UAT-NT-005: ACL — Legacy Notes (No Owner)

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-005 |
| **BR** | BR-NT-007, BR-NT-010 |
| **Actor** | Alice, Bob |

**Steps**:
1. Ensure there are notes with `owner = NULL` in the database (pre-existing or create via direct SQL)
2. Log in as Alice
3. View an entity with a legacy note
4. Verify the note is visible
5. Verify Edit and Delete buttons are present (legacy notes are editable by all authenticated users)
6. Log in as Bob
7. View the same entity
8. Verify Edit and Delete buttons are also present

**Expected Result**: Legacy notes (no owner) remain editable by all authenticated users for backward compatibility.

**Sign-off**: ☐ Pass / ☐ Fail

---

### UAT-NT-006: File Upload to Note

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-006 |
| **BR** | BR-NT-008 |
| **Actor** | Alice |

**Steps**:
1. Log in as Alice
2. Navigate to a customer → Add Note
3. Enter note text: "Received signed agreement"
4. Click "Choose File" and select a PDF or image file
5. Leave "Send to OCR" unchecked
6. Submit the note
7. Verify note appears in panel with a file attachment link
8. Click the attachment link → verify file downloads correctly
9. Create another note with two file attachments (if multi-file supported in UI)
10. Verify both files appear as separate download links

**Expected Result**: File is uploaded and linked to the note. Download link works.

**Sign-off**: ☐ Pass / ☐ Fail

---

### UAT-NT-007: OCR — Send Image to OCR

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-007 |
| **BR** | BR-NT-009 |
| **Actor** | Alice |

**Steps**:
1. Log in as Alice
2. Navigate to a customer → Add Note
3. Enter note text: "Scanned business card from conference"
4. Click "Choose File" and select a PNG/JPEG image containing text
5. Check "Send to OCR"
6. Submit the note
7. Verify note appears in panel
8. Verify note text now reads: "Scanned business card from conference\n\n--- OCR Text ---\n<extracted text from image>"
9. Verify two attachment links:
   - Original image file
   - OCR text (.txt) file
10. Download the OCR text file → verify it contains the extracted text

**Expected Result**: OCR processing extracts text and appends to note body. Both original image and OCR text file are attached.

**Sign-off**: ☐ Pass / ☐ Fail

---

### UAT-NT-008: OCR — Graceful Degradation When Service Unavailable

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-008 |
| **BR** | BR-NT-009 |
| **Actor** | Alice |

**Steps**:
1. Request UAT admin to stop the ksf-tesseract container OR point OCR URL to an invalid address
2. Log in as Alice
3. Navigate to a customer → Add Note
4. Enter note text: "Test note with OCR unavailable"
5. Attach an image file
6. Check "Send to OCR"
7. Submit the note
8. Verify note is saved successfully
9. Verify file attachment is stored (original image)
10. Verify a notification/warning is displayed: "File saved but OCR processing failed"
11. Verify note body does NOT contain "--- OCR Text ---" section
12. Verify only one attachment (original image), no OCR text .txt file

**Expected Result**: Feature degrades gracefully. Note and file are saved. OCR failure is non-fatal.

**Sign-off**: ☐ Pass / ☐ Fail

---

### UAT-NT-009: Note Search

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-009 |
| **BR** | BR-NT-004 |
| **Actor** | Alice |

**Steps**:
1. Log in as Alice
2. Navigate to Notes Management page
3. Enter a keyword that appears in at least one note
4. Submit search
5. Verify matching notes are displayed
6. Filter by entity type "Customer"
7. Verify results are filtered
8. Enter a keyword that does not match any note
9. Verify "No notes found" message

**Expected Result**: Search returns matching results. Empty state displays appropriate message.

**Sign-off**: ☐ Pass / ☐ Fail

---

### UAT-NT-010: Backward Compatibility — Legacy Workflow

| Field | Value |
|-------|-------|
| **Test ID** | UAT-NT-010 |
| **BR** | BR-NT-010 |
| **Actor** | Alice |

**Steps**:
1. Ensure UAT environment has pre-existing v1.0.0 notes (no owner, no links table entries)
2. Log in as Alice
3. Navigate to entities that had notes in v1.0.0
4. Verify all legacy notes are displayed correctly
5. Create a new note without using any v2.0.0 features (no file, no OCR, no multi-link)
6. Verify the note behaves exactly as in v1.0.0

**Expected Result**: All legacy notes remain accessible and editable. New notes without v2 features work identically to v1.0.0.

**Sign-off**: ☐ Pass / ☐ Fail

---

## UAT Sign-Off Summary

| Test ID | Test Name | Tester | Date | Result | Notes |
|---------|-----------|--------|------|--------|-------|
| UAT-NT-001 | Basic Note CRUD | | | ☐ Pass / ☐ Fail | |
| UAT-NT-002 | Multi-Entity Linking | | | ☐ Pass / ☐ Fail | |
| UAT-NT-003 | ACL — Owner vs Other | | | ☐ Pass / ☐ Fail | |
| UAT-NT-004 | ACL — Admin Bypass | | | ☐ Pass / ☐ Fail | |
| UAT-NT-005 | ACL — Legacy Notes | | | ☐ Pass / ☐ Fail | |
| UAT-NT-006 | File Upload | | | ☐ Pass / ☐ Fail | |
| UAT-NT-007 | OCR Image Processing | | | ☐ Pass / ☐ Fail | |
| UAT-NT-008 | OCR Graceful Degradation | | | ☐ Pass / ☐ Fail | |
| UAT-NT-009 | Note Search | | | ☐ Pass / ☐ Fail | |
| UAT-NT-010 | Backward Compatibility | | | ☐ Pass / ☐ Fail | |

## Overall Sign-Off

| Criteria | Result |
|----------|--------|
| All critical tests pass (UAT-NT-001, -002, -003, -004, -006, -010) | ☐ Yes / ☐ No |
| All high-priority tests pass | ☐ Yes / ☐ No |
| OCR tests pass OR waiver obtained | ☐ Yes / ☐ No |
| No P1 (critical) defects open | ☐ Yes / ☐ No |

**UAT Approved By**: ______________________ **Date**: ______________

**Business Owner Signature**: ______________________

*Document Version: 2.0.0*
*Last Updated: 2026-06-28*
