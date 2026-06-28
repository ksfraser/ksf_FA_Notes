# Architecture.md

## Overview

ksf_FA_Notes is the FrontAccounting adapter module for ksf_Notes. It provides FA-compatible database persistence, UI components, and integration hooks for the note-taking system. This document covers the module's structural architecture, data model, service layer, UI components, security model, and external dependencies.

## Module Structure

```
ksf_FA_Notes/
├── sql/
│   ├── install.sql                    # Schema creation (fa_crm_notes, fa_note_links)
├── includes/
│   ├── notes_db.inc                   # Core CRUD + search for notes (updated with ACL)
│   └── links_db.inc                   # Multi-entity link/unlink/query functions (NEW)
├── pages/
│   ├── notes.php                      # Standalone notes management page
│   └── notes_ui.inc                   # Reusable UI components (updated with file upload + OCR)
├── hooks.php                          # FA module hooks (install, security, menus)
├── composer.json                      # Dependency declaration
└── ProjectDcs/                        # BABOK documentation
```

## Data Model

### Table: fa_crm_notes (UPDATED)

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT PK | Primary key |
| entity_id | INT | Primary linked entity ID (backward compat) |
| entity_type | VARCHAR(60) | Primary linked entity type (backward compat) |
| note_type | VARCHAR(60) | Classification (Comment, Internal, Public) |
| note | TEXT | Note body text |
| created_by | VARCHAR(100) | Username of creator |
| created_at | TIMESTAMP | Auto-set on creation |
| owner | INT | FK to FA users table (NULL = legacy) |
| group_id | INT | Access group ID for future RBAC (NULL = no restriction) |

**ACL columns added**: `owner`, `group_id` — enable per-note ownership and group-based access control.

### Table: fa_note_links (NEW)

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT PK | Primary key |
| note_id | INT FK -> fa_crm_notes.id | The linked note |
| entity_type | VARCHAR(60) | Entity type (debtor, project, ticket, etc.) |
| entity_id | INT | Entity ID within that type |

**Multi-entity linking**: A note can be linked to N entities via this table. The `entity_id`/`entity_type` columns on `fa_crm_notes` serve as the "primary" link for backward compatibility. Additional associations are stored here.

### Table: fa_attachments (via ksf_FA_Attachments module)

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT PK | Primary key |
| source_type | VARCHAR(60) | `'note'` for note attachments |
| source_id | INT | FK to fa_crm_notes.id |
| filename | VARCHAR(255) | Original filename |
| file_path | VARCHAR(512) | Storage path |
| mime_type | VARCHAR(127) | MIME type |
| file_size | INT | Size in bytes |
| created_at | TIMESTAMP | Upload timestamp |

The `fa_attachments` table is owned by the `ksf_FA_Attachments` module. ksf_FA_Notes references it via `source_type = 'note'` and `source_id = <note_id>`.

## Service Layer

### notes_db.inc — Core CRUD

| Function | Description | ACL Check |
|----------|-------------|-----------|
| `add_note(entity_id, entity_type, note, note_type, created_by, owner, group_id)` | Create a new note | None (creation allowed for all authenticated users) |
| `get_note(note_id)` | Fetch single note by ID | None |
| `get_notes(entity_id, entity_type)` | Fetch notes by primary entity | None (caller filters by ACL if needed) |
| `update_note(note_id, note, note_type, user_id)` | Update note content | Yes — verifies owner match or admin |
| `delete_note(note_id, user_id)` | Delete a note | Yes — verifies owner match or admin |
| `search_notes(keyword, entity_type, limit, user_id)` | Full-text search on note body | Yes — filters results by ACL |
| `get_note_count(entity_id, entity_type)` | Count notes for entity | None |
| `get_entity_notes_summary(entity_type)` | Aggregate summary | None |

### links_db.inc — Multi-Entity Linking (NEW)

| Function | Description |
|----------|-------------|
| `link_note_to_entity(note_id, entity_type, entity_id)` | Add an entity link for a note |
| `unlink_note_from_entity(link_id)` | Remove a specific entity link |
| `unlink_all_note_links(note_id)` | Remove all links for a note |
| `get_linked_entities(note_id)` | Return all linked entities for a note |
| `get_notes_for_entity(entity_type, entity_id)` | Find all notes linked to an entity (via links table) |

**Entity resolution flow**:
1. When displaying notes for an entity, first query `fa_crm_notes` by `entity_id`/`entity_type` (primary link)
2. Also query `fa_note_links` where `entity_type`/`entity_id` match
3. Merge and deduplicate results by `note_id`
4. Return unified list sorted by `created_at DESC`

## UI Components

### display_notes_panel — UPDATED

The reusable panel now includes:

1. **Note text area** (existing)
2. **Note type selector** (existing)
3. **File upload input** (NEW) — `<input type="file" name="note_file">`
4. **"Send to OCR" checkbox** (NEW) — `<input type="checkbox" name="ocr_enabled">`
5. **Multi-entity link selector** (NEW) — multi-select or tag-style input for additional entity associations
6. **Owner/group fields** (NEW) — hidden or editable depending on permissions

### OCR Integration Flow

```
User uploads image + checks "Send to OCR"
        │
        ▼
┌─────────────────────────────┐
│ 1. Save note to fa_crm_notes│
│    (get $note_id)           │
└──────────┬──────────────────┘
           │
           ▼
┌──────────────────────────────────────┐
│ 2. Store uploaded file via            │
│    ksf_FA_Attachments:               │
│    fa_attachments(source_type='note', │
│    source_id=$note_id)                │
│    Returns $attachment_id             │
└──────────┬───────────────────────────┘
           │
           ▼
   ┌── OCR checked? ──┐
   │                  │
  YES                NO
   │                  │
   ▼                  ▼
┌─────────────────┐   Done
│ 3. POST file to  │
│    OCR service   │
│    (cURL)        │
│    http://ksf-   │
│    tesseract:    │
│    8100/ocr      │
└────────┬─────────┘
         │
         ▼
┌──────────────────────────────────────┐
│ 4. Save OCR result as .txt file       │
│ 5. Store .txt via ksf_FA_Attachments  │
│    fa_attachments(source_type='note', │
│    source_id=$note_id)                │
│    Returns $ocr_attachment_id         │
└──────────┬───────────────────────────┘
           │
           ▼
┌──────────────────────────────────────┐
│ 6. Concatenate user note + OCR text  │
│    OR store separately with link      │
│    OCR text appended to note body     │
│    with delimiter "[OCR Text]"        │
└──────────────────────────────────────┘
```

**Design decision**: OCR text is appended to the note body with a `--- OCR Text ---` delimiter. This keeps the data model simple while preserving both the user's original input and the extracted text in a single query. An alternative (storing OCR text separately with a link) can be implemented later if search indexing demands it.

## Security (ACL Enforcement)

### Data-Level ACL

| Operation | Rule |
|-----------|------|
| View note | Any authenticated user can view if `owner IS NULL` OR user is the `owner` OR user belongs to `group_id` group |
| Edit note | Only the `owner` OR admin-level users (`SA_ksf_FA_NotesMANAGE`) |
| Delete note | Same as edit |
| Change owner | Only admin-level users |

### FA Security Areas

| Security Area | Permission |
|---------------|------------|
| `SA_ksf_FA_NotesVIEW` | View notes across the system |
| `SA_ksf_FA_NotesMANAGE` | Create, edit, delete any note (bypasses owner check) |

### ACL Enforcement Points

1. **notes_db.inc** — All mutation functions accept an optional `$user_id` parameter. Before UPDATE/DELETE, the function checks that the caller is the `owner` or has `MANAGE` privilege.
2. **notes_ui.inc** — The UI hides Edit/Delete buttons when the current user does not have appropriate permissions.
3. **notes.php** — Server-side re-validation before processing form submissions.

## Multi-Link Entity Resolution

When fetching notes for display (e.g., viewing a customer's notes page):

```
Input: entity_type = 'debtor', entity_id = 42
                    │
                    ▼
┌───────────────────────────────────┐
│ Query 1: fa_crm_notes             │
│ WHERE entity_id=42                │
│   AND entity_type='debtor'        │
│ ORDER BY created_at DESC          │
└──────────────┬────────────────────┘
               │
               ▼
┌───────────────────────────────────┐
│ Query 2: fa_note_links ln         │
│ JOIN fa_crm_notes n               │
│   ON ln.note_id = n.id            │
│ WHERE ln.entity_id=42             │
│   AND ln.entity_type='debtor'     │
│ ORDER BY n.created_at DESC        │
└──────────────┬────────────────────┘
               │
               ▼
┌───────────────────────────────────┐
│ Merge results by note_id           │
│ (deduplicate)                      │
│ Sort combined by created_at DESC   │
└───────────────────────────────────┘
```

## Dependencies

### Required

| Dependency | Purpose | Integration |
|------------|---------|-------------|
| FrontAccounting 2.4+ | Host platform | FA hooks, security, DB abstraction, UI library |
| ksf_FA_Attachments | File storage | `fa_attachments` table, `store_attachment()`, `get_attachments_for_source()` |

### Optional

| Dependency | Purpose | Integration |
|------------|---------|-------------|
| ksf-tesseract OCR service | OCR text extraction | cURL POST to `http://ksf-tesseract:8100/ocr`, receives JSON with extracted text |

### Deployment Configuration

The OCR service URL is configurable via a module setting or constant:

```php
define('KSF_OCR_SERVICE_URL', 'http://ksf-tesseract:8100/ocr');
```

When the OCR service is unreachable, the file upload proceeds without OCR. The error is logged but not fatal.

## Event Dispatching

The module uses generic SuiteCRM-style lifecycle hooks with `entity_type` and `action` in the payload.
Events are dispatched via `includes/events.inc` and broadcast via `hook_invoke_all`/`hook_invoke_first`.

### Generic Lifecycle Hooks

| Hook Name | Direction | entity_type | action | Fired |
|-----------|-----------|-------------|--------|-------|
| `before_save` | hook_invoke_first (filter) | `note` | `create` | Before note INSERT |
| `after_save` | hook_invoke_all | `note` | `create` | After note INSERT |
| `before_save` | hook_invoke_first | `note` | `update` | Before note UPDATE |
| `after_save` | hook_invoke_all | `note` | `update` | After note UPDATE |
| `before_delete` | hook_invoke_first | `note` | — | Before note DELETE |
| `after_delete` | hook_invoke_all | `note` | — | After note DELETE |
| `after_load` | hook_invoke_first | `note` | `load` | After note DB fetch |
| `before_save` | hook_invoke_first | `note_link` | `create` | Before link INSERT |
| `after_save` | hook_invoke_all | `note_link` | `create` | After link INSERT |
| `before_delete` | hook_invoke_first | `note_link` | — | Before link DELETE |
| `after_delete` | hook_invoke_all | `note_link` | — | After link DELETE |

### Entity Type Registration

The hooks class implements `_getAdvertisedValues()` via `HookQueryProviderTrait`:

```
notes.entity_types → ['note', 'note_link']
notes.events      → ['before_save', 'after_save', 'before_delete', 'after_delete', 'after_load']
```

### Payload Structure

```php
$payload = [
    'event'       => 'after_save',
    'module'      => 'notes',
    'entity_type' => 'note',
    'entity_id'   => $noteId,
    'action'      => 'create',
    'timestamp'   => date('Y-m-d H:i:s'),
    // ... entity-specific fields
];

// Dual dispatch:
hook_invoke_all('after_save', $payload);
hook_invoke_all('ksf_crud_event', $payload);  // Workflow integration
```

### After-Load Enrichment

```php
$payload = ['entity_type' => 'note', 'action' => 'load', 'data' => $row];
$modified = hook_invoke_first('after_load', $payload);
$row = $modified['data'] ?? $modified;
```

## Schema Migration

### Upgrade from v1.0.0 to v2.0.0

```sql
-- Add ACL columns
ALTER TABLE fa_crm_notes
  ADD COLUMN owner INT NULL AFTER created_at,
  ADD COLUMN group_id INT NULL AFTER owner;

-- Create links table
CREATE TABLE fa_note_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  note_id INT NOT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id INT NOT NULL,
  FOREIGN KEY (note_id) REFERENCES fa_crm_notes(id) ON DELETE CASCADE,
  INDEX idx_note_links_entity (entity_type, entity_id),
  INDEX idx_note_links_note (note_id)
);
```

*Document Version: 2.0.0*
*Last Updated: 2026-06-28*
