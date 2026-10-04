# Functional Requirements — ksf_FA_Notes (as built)

The pre-existing `ProjectDcs/Functional Requirements.md` describes the retired
v1 design (`fa_crm_notes`, `fa_note_links`, `add_note()`, `get_notes()`, an
`entity_id`/`entity_type` pair of columns on the note itself, and the security
area name `SA_ksf_FA_NotesVIEW`). None of that is what the module does now.

This file records the requirements the code actually satisfies, keyed to the
`@BABOK Related:` annotations in `src/`, `includes/` and `tests/`. The
sub-numbering (`FR-NT-001-001` …) follows the convention in the master
`AGENTS.md`: a module sequence plus a sub-sequence.

Status legend: **IMPLEMENTED** / **PARTIAL** / **NOT STARTED**.

---

## FR-NT-001-001 — Note creation

**Status:** IMPLEMENTED
**Code:** `src/Ksfraser/FA/Notes/Service/NoteCreationService.php`,
`hooks.php::CREATE_NOTE`
**Tests:** `tests/Unit/NoteServiceTest.php`

1. The system SHALL create a note from a `note` body plus optional `subject`,
   `note_type`, `owner`, `created_by` and `group_id`.
2. A note with an empty or whitespace-only body SHALL be rejected with
   `error = note_body_required`, and NO SQL SHALL be issued.
3. `created_by` SHALL default to the logged-in user when the caller omits it.
4. Caller arguments in `$opts` SHALL take precedence over the `$data` payload.
5. On success the new `note_id` and the number of links added SHALL be
   returned.

### Links supplied at creation

6. `CREATE_NOTE` SHALL accept a `links` argument in either the associative form
   (`entity_type`/`entity_id`/`link_role`) or the compact
   `[$entity_type, $entity_id, $role]` form.
7. Malformed link entries SHALL be skipped without failing the note.
8. Links SHALL be de-duplicated by `notes_link_add()` before insertion, so a
   repeated attachment does not create a second row.

---

## FR-NT-001-002 — Entity registry and responder-supplied options

**Status:** IMPLEMENTED
**Code:** `includes/entity_types.inc`,
`src/Ksfraser/FA/Notes/Entity/EntityOptionsProvider.php`
**Tests:** `tests/Unit/EntityTypesTest.php`,
`tests/Unit/EntityOptionsProviderTest.php`

The registry is the contract Notes shares with CRM, HRM and Project Management:
it names, for each kind of record a note can point at, the table, the primary
key, and whether the type can be offered a picker.

1. The registry SHALL cover exactly nine types: customer, contact, meeting,
   opportunity, contract, project, task, calendar, attachment.
2. Contact SHALL resolve to the native `0_crm_persons` table, because FA's
   `0_crm_persons` holds customer contacts *and* HRM employees; no module-local
   contacts table SHALL be introduced.
3. Option data SHALL be resolved in this order: caller-supplied overrides,
   then a hook responder, then a direct table read.
4. A responder SHALL be addressed by the registry's `options_hook` key when
   present, otherwise by the camelCase convention `get<Type>Options`.
5. A type whose registry entry sets `picker => false` SHALL NOT be offered in
   any dropdown. `contract` is currently `false` because `ksf_FA_Contract` has
   not been built.
6. A type with no available options SHALL NOT be offered, since the picker
   would be empty.
7. `labelFor()` SHALL return an empty string, never `null`, for a record that
   cannot be resolved — callers render the result directly into HTML.
8. Options SHALL be memoised per request so a responder is invoked at most once
   per type.

---

## FR-NT-001-003 — Note retrieval and search

**Status:** IMPLEMENTED
**Code:** `src/Ksfraser/FA/Notes/Service/NoteQueryService.php`,
`hooks.php::GET_NOTE`, `SEARCH_NOTES`, `GET_NOTES_FOR_ENTITY`
**Tests:** `tests/Unit/NoteServiceTest.php`

1. `GET_NOTE` SHALL return one note with its links resolved to labels, or
   `note_id_required` / `note_not_found`.
2. `SEARCH_NOTES` SHALL filter by `keyword`, `note_type`, `created_by`,
   `entity_type`, `entity_id` and `limit`.
3. Empty or null criteria SHALL be dropped rather than sent to the query
   builder, so they do not become accidental filters.
4. Results SHALL be ordered `created_at DESC, id DESC`.
5. Filtering by record SHALL join the link table rather than reading columns
   off the note row.
6. `GET_NOTES_FOR_ENTITY` SHALL require both `entity_type` and `entity_id` and
   return `entity_required` otherwise.
7. Each responder SHALL return the number of rows it returned in `count`.

---

## FR-NT-001-004 — Note data-entry form

**Status:** IMPLEMENTED (attachment upload **NOT STARTED** — see FR-NT-001-007)
**Code:** `src/Ksfraser/FA/Notes/View/NoteFormView.php`, `pages/notes.php`
**Tests:** `tests/Unit/NotesViewTest.php`

1. The form SHALL render one multi-select per pickable type, pre-selected with
   the note's current attachments.
2. The form SHALL post a create or an update, distinguished by the presence of
   `edit_id`.
3. Existing links SHALL be listed with a per-link remove control.
4. A link whose target record no longer exists SHALL be shown as
   `(not found)` rather than being silently dropped, so a note is never quietly
   edited down.
5. The `picker` flag SHALL gate every dropdown the module renders, not only the
   form's pickers — a filter on a type with no table would always be empty.
6. All rendered values SHALL be HTML-escaped.
7. Submitting a record list that no longer contains an existing attachment
   SHALL remove that attachment regardless of the role it was stored under.

---

## FR-NT-001-005 — Note summary table

**Status:** IMPLEMENTED
**Code:** `src/Ksfraser/FA/Notes/DTO/NotesFilter.php`,
`src/Ksfraser/FA/Notes/View/NoteFilterBar.php`,
`src/Ksfraser/FA/Notes/View/NoteSummaryTableView.php`, `pages/notes.php`
**Tests:** `tests/Unit/NotesFilterTest.php`, `tests/Unit/NotesViewTest.php`

1. The summary table SHALL default to the notes owned by the logged-in user.
2. The owner filter SHALL offer "mine", "everyone", and any individual user who
   owns at least one note.
3. Naming a user SHALL select that user's notes, whether or not `owner_scope` is
   also supplied.
4. The owner list SHALL come from a `SELECT DISTINCT owner` projection, not from
   scanning note rows, so populating the dropdown does not pull every note.
5. The table SHALL also filter by free text over subject and body, by note type,
   by the kind of record a note is attached to, and by a specific record.
6. Inactive notes SHALL be excluded unless explicitly requested.
7. `NotesFilter::fromRequest()` SHALL read only `$_GET`, so the summary table is
   a safe link target that cannot change data.
8. An empty subject SHALL render as `(no subject)`.
9. A note attached to nothing SHALL render as `(not attached)`.

---

## FR-NT-001-006 — Schema ownership and installation

**Status:** IMPLEMENTED
**Code:** `sql/ksf_notes.sql`, `sql/ksf_notes_link.sql`,
`hooks.php::activate_extension`

1. Each owned table SHALL have its own SQL file carrying its definition and any
   pre-seed data: `sql/ksf_notes.sql` and `sql/ksf_notes_link.sql`.
2. SQL files SHALL use the literal `0_` table prefix. FA's `db_import()`
   substitutes the real company prefix and does NOT understand `@TB_PREF@` or
   `{TB_PREF}`.
3. `activate_extension()` SHALL gate each file on its own table via
   `update_databases()`, with the note table imported before the link table.
4. The keys passed to `update_databases()` SHALL be BARE filenames. FA builds
   the import path as `<path_to_root>/modules/<module_name>/sql/<file>`, so an
   absolute key would resolve to a path that does not exist and the table would
   silently never be created.
5. `entity_id` SHALL be `VARCHAR(64)`, because referenced primary keys are not
   uniform across the estate.
6. `link_role` SHALL record WHY a record is referenced (`regarding`, `with`,
   `attendee`, `document`), allowing one note to express several relationships.
7. Table names SHALL retain their mixed case. FA's `check_table()` uses a
   case-insensitive `SHOW TABLES LIKE`, so a stray lowercase twin would satisfy
   the installer gate while being a different table.

---

## FR-NT-001-007 — Attachment upload

**Status:** NOT STARTED

1. The form SHALL offer file upload for a note.
2. Uploads SHALL be written through FA's native `add_attachment()`.
3. Note attachment selection SHALL reuse FA's existing attachment viewer rather
   than duplicating upload handling.

**Constraint:** FA's native upload validation reads a closed `ST_*` registry
(`transactions_db.inc`), so a custom note target type is not available through
the standard upload path. Direct `add_attachment()` writes are possible. The
custom type `NOTES_ATTACHMENT_TYPE_NO = 100` is currently provisional and must
be settled before this requirement is implemented.

---

## FR-NT-001-008 — Access control

**Status:** PARTIAL

1. Two security areas SHALL exist: `SA_NOTES_VIEW` and `SA_NOTES_MANAGE`
   (`SS_ksf_FA_Notes | 1` and `| 2`).
2. `pages/notes.php` SHALL enforce `SA_NOTES_VIEW` on load and
   `SA_NOTES_MANAGE` on every mutating route, rather than relying on the
   summary table having hidden the button.
3. `add_access_extensions()` SHALL be called after `session.inc` and before
   `page_header()`.
4. **OUTSTANDING:** the hook responders (`CREATE_NOTE`, `GET_NOTE`,
   `SEARCH_NOTES`, `GET_NOTES_FOR_ENTITY`) do not yet check a security area. A
   module calling `hook_invoke('ksf_FA_Notes', 'GET_NOTE', ...)` currently
   bypasses `SA_NOTES_VIEW`.

---

## FR-NT-001-009 — Module bootstrap and dependencies

**Status:** IMPLEMENTED
**Code:** `ComposerDependencies.php`, `hooks.php`

1. The module SHALL carry a per-module copy of
   `ksf_FA_Common/src/Utils/ComposerDependencies.template.php` with `MODULENAME`
   replaced in the namespace.
2. The copy SHALL be `require_once`d at the top of `hooks.php` and
   `ensure(__DIR__)` SHALL be called before the Composer autoloader is
   required. This ordering is deliberate: a PSR-4 autoloader cannot be used to
   load the thing that installs the vendor tree.
3. The namespace-scoped guard inside the template SHALL be preserved, so
   several modules can each carry their own copy without redeclaring anything.
4. `activate_extension()` SHALL re-run `ensure()` for the case where activation
   runs before any page load did.
5. No `autoload.files` entry SHALL point at a legacy procedural include. Side
   effects on `require` were the reason for removing it.

---

## FR-NT-001-010 — Responder contract and lifecycle hooks

**Status:** IMPLEMENTED
**Code:** `hooks.php`, `includes/events.inc`

1. `hooks.php` responders SHALL be thin adapters: each SHALL instantiate the
   appropriate SRP service from `src/Ksfraser/FA/Notes/Service/` and delegate,
   with no gateway calls inline.
2. The discovery contract SHALL comprise four methods:
   `getModuleConstants`, `getModuleCapabilities`, `hasCapability`, and
   `respondToCapabilityRequest`.
3. The responder list SHALL be declared once as a class constant and reused by
   `getModuleCapabilities()`, `hasCapability()` and `_getAdvertisedValues()` so
   they cannot drift apart.
4. The hooks class SHALL use `WorkflowHooksTrait` from `ksf_FA_Common` and
   register the `note` record type against the `NOTES_NOTE` prefix.

### Known divergence to resolve

5. The gateway dispatches **bare** hook names (`before_save`, `after_save`,
   `before_delete`, `after_delete`) through `includes/events.inc`, while
   `WorkflowHooksTrait` builds **prefixed** names (`NOTES_NOTE_before_save`).
   Both paths currently exist. A subscriber listening through either name
   receives only one of them. `_getAdvertisedValues()` advertises both lists so
   the divergence is at least discoverable, but the two dispatch paths should be
   unified.

---

## Traceability

| FR | Code | Tests |
|----|------|-------|
| FR-NT-001-001 | `NoteCreationService`, `CREATE_NOTE` | `NoteServiceTest` |
| FR-NT-001-002 | `entity_types.inc`, `EntityOptionsProvider` | `EntityTypesTest`, `EntityOptionsProviderTest` |
| FR-NT-001-003 | `NoteQueryService`, `GET_NOTE`, `SEARCH_NOTES`, `GET_NOTES_FOR_ENTITY` | `NoteServiceTest` |
| FR-NT-001-004 | `NoteFormView` | `NotesViewTest` |
| FR-NT-001-005 | `NotesFilter`, `NoteFilterBar`, `NoteSummaryTableView` | `NotesFilterTest`, `NotesViewTest` |
| FR-NT-001-006 | `sql/*.sql`, `activate_extension` | `NotesResponderTest` |
| FR-NT-001-007 | — | — |
| FR-NT-001-008 | `pages/notes.php`, `install_access` | partial |
| FR-NT-001-009 | `ComposerDependencies.php`, `hooks.php` | — |
| FR-NT-001-010 | `hooks.php`, `events.inc` | `NotesResponderTest`, `NoteServiceTest` |

Test counts at time of writing: **146 tests, 440 assertions**.