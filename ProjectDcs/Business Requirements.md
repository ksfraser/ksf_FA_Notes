# Business Requirements - ksf_FA_Notes

## Overview
ksf_FA_Notes is the FrontAccounting adapter for ksf_Notes (flexible note-taking linked to entities).

## Relationship to Core Module

### Core Module
- **ksf_Notes**: Business logic (entities, services)
- Namespace: `Ksfraser\Notes`

### FA Adapter
- **ksf_FA_Notes**: FA presentation and persistence layer
- Namespace: `Ksfraser\FA\Notes`

## Note vs Document Distinction

### ksf_Notes (Notes) - This Module
**Purpose**: Record snippets of information as text
- Short text entries (designed for quick notes)
- Linked to any entity (customer, project, task, ticket)
- Can have attachments (optional)
- Example: "Called customer - interested in upgrade", "Meeting notes from Q4 review"

### ksf_Documents (Documents) - Separate Module
**Purpose**: Store file attachments with metadata
- Primary purpose is the attachment (file)
- Text field describes the attachment
- Version tracking for documents
- Example: "Signed contract - PDF", "Employee ID scan - JPEG"

| Aspect | Note (ksf_Notes) | Document (ksf_Documents) |
|--------|------------------|---------------------------|
| **Primary Content** | Text snippet | File attachment |
| **Text Field** | Main content | Description/metadata |
| **Attachments** | Optional | Required |
| **Versioning** | Simple edit history | File version tracking |
| **Use Case** | Quick info capture | Official file storage |
| **Multi-Entity Link** | Yes (via fa_note_links) | Typically single-context |
| **OCR** | Optional attached image → text | N/A |

## Business Requirements

### BR-NT-001: Note Creation
Users must be able to create text notes and associate them with any FA entity (customer, contact, opportunity, ticket, call log, lead).

### BR-NT-002: Note Retrieval
Users must be able to view notes associated with a specific entity, sorted by most recent first.

### BR-NT-003: Note Editing and Deletion
Users must be able to edit or delete notes they own. Administrators may edit or delete any note.

### BR-NT-004: Note Search
Users must be able to search notes by keyword across all entities or filtered by entity type.

### BR-NT-005: Single-Entity Linking
Notes must support linking to a primary entity via `entity_id`/`entity_type` on the notes table.

### BR-NT-006: Multi-Entity Linking
A note must be able to link to **multiple entities simultaneously** via a dedicated links table. For example, a quote review note should link to the quote, the customer, and the company all at once. This allows a single note to appear in the context of all relevant entities without duplication.

**Rationale**: In real workflows, a single observation often spans multiple contexts. A meeting note about a customer's project quote is relevant in the customer view, the project view, and the quote view. Multi-linking avoids forcing users to create redundant copies of the same note.

### BR-NT-007: Access Control on Notes
Notes must support ownership and group-level access control.

- Each note has an `owner` (FK to FA users table) — the user who created or is responsible for the note.
- Each note has a `group_id` for future RBAC integration.
- By default (owner = NULL, group_id = NULL), notes remain visible to all authenticated users (legacy behavior).
- When owner is set, only the owner (or users with MANAGE privilege) may edit or delete the note.
- View permissions can be restricted by group membership in future iterations.

### BR-NT-008: File Attachment Upload to Notes
Users must be able to attach files to notes via the note entry form. Files are stored using the shared ksf_FA_Attachments module infrastructure (fa_attachments table).

- The attachment is associated with the note via `source_type = 'note'` and `source_id = <note_id>`.
- Multiple files may be attached to a single note (future iteration; initial release supports one file per note submission).
- Attached files are displayed alongside the note in the notes panel.

### BR-NT-009: OCR Text Extraction from Attached Images
When adding a file attachment to a note, users may optionally check "Send to OCR" to have the image processed by an OCR service.

- Upon checking the OCR option:
  1. The uploaded file is sent via cURL to `http://ksf-tesseract:8100/ocr`.
  2. The OCR service returns extracted text as JSON.
  3. The extracted text is saved as a `.txt` file and stored via ksf_FA_Attachments.
  4. The OCR text is appended to the note body with an `--- OCR Text ---` delimiter.
- If the OCR service is unreachable, the file attachment proceeds without OCR. The error is logged for diagnostic purposes.
- The OCR service is optional per deployment — the feature degrades gracefully.

### BR-NT-010: Backward Compatibility
Existing notes with `entity_id`/`entity_type` (no multi-links, no ACL, no attachments) must continue to function without migration changes to existing data. New features must be additive.

## Traceability Summary

| BR ID | Description | Priority | FR Mapping |
|-------|-------------|----------|------------|
| BR-NT-001 | Note creation | High | FR-NT-001 |
| BR-NT-002 | Note retrieval | High | FR-NT-002 |
| BR-NT-003 | Note editing/deletion | High | FR-NT-003 |
| BR-NT-004 | Note search | Medium | FR-NT-004 |
| BR-NT-005 | Single-entity linking | High | FR-NT-005 |
| BR-NT-006 | Multi-entity linking | High | FR-NT-006 |
| BR-NT-007 | Access control | High | FR-NT-007 |
| BR-NT-008 | File attachment upload | High | FR-NT-008 |
| BR-NT-009 | OCR text extraction | Medium | FR-NT-009 |
| BR-NT-010 | Backward compatibility | High | FR-NT-001 through FR-NT-005 |

## Link to Core BR
This adapter implements: `/home/kevin/Documents/ksf_Notes/ProjectDcs/Business Requirements.md`

*Document Version: 2.0.0*
*Last Updated: 2026-06-28*
