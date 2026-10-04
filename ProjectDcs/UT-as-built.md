# Test coverage as built — ksf_FA_Notes

`Test Plan.md` and `RTM.md` describe the retired v1 design (`add_note()`,
`get_notes()`, `fa_note_links`, `SA_ksf_FA_NotesVIEW`, and an OCR pipeline).
This file records the tests that exist and pass today.

Run with:

```bash
./vendor/bin/phpunit --no-coverage
```

Current result: **OK (146 tests, 440 assertions)**.

No DB, no web server, no FA bootstrap. `tests/bootstrap.php` stubs the FA
functions the gateways need (`db_query`, `db_escape`, `db_insert_id`, `_()`,
`sql2date`) and `tests/Support/FaDbFake.php` records every statement, so the
tests assert on the SQL that would have been sent rather than on a result set.

---

## UT-NT-001-001 — NoteCreationService

**File:** `tests/Unit/NoteServiceTest.php` · **FR:** FR-NT-001-001

| Test | Asserts |
|------|---------|
| `testCreateReportsTheNewId` | returns `ok = true` and the `db_insert_id()` value |
| `testCreateRejectsAnEmptyBody` | `note_body_required` and **zero** SQL statements |
| `testCreateRejectsWhitespaceOnlyBody` | whitespace is rejected, not trimmed into a note |
| `testCreateAcceptsTheCompactLinkForm` | `[$type, $id, $role]` triples create links, in order |
| `testCreateAcceptsTheAssociativeLinkForm` | `entity_type`/`entity_id`/`link_role` keys work |
| `testCreateSkipsMalformedLinks` | bad entries are dropped, valid ones still saved |
| `testCreateDefaultsCreatedByToTheSessionUser` | omitted `created_by` falls back to the session user |
| `testCreateDefaultsSubjectAndType` | subject stays null, type falls back to `Comment` |

The compact-form test asserts on `FaDbFake::$log` positions: the note INSERT is
first, then a `SELECT` + `INSERT` pair per link (the gateway de-duplicates
before inserting), with `customer` before `contact`.

## UT-NT-001-002 — NoteQueryService

**File:** `tests/Unit/NoteServiceTest.php` · **FR:** FR-NT-001-003

| Test | Asserts |
|------|---------|
| `testFindRequiresANoteId` | `note_id_required` when absent |
| `testFindReturnsTheNoteWithItsLinks` | row plus one link each, resolved |
| `testFindReportsAMissingNote` | `note_not_found` when the SELECT returns nothing |
| `testSearchDropsEmptyCriteria` | nulls and `''` are omitted from the WHERE clause |
| `testSearchReturnsTheRowCount` | `count` matches rows returned |
| `testSearchForEntityRequiresBothParts` | `entity_required` without `entity_type` |
| `testSearchForEntityJoinsTheLinkTable` | filtering by record joins `ksf_Notes_link` |
| `testSearchForEntityLetsExplicitOptionsWin` | caller options beat the payload |

## UT-NT-001-003 — Entity registry

**File:** `tests/Unit/EntityTypesTest.php` · **FR:** FR-NT-001-002

Covers the nine registered types, that `contact` resolves to the native
`0_crm_persons` table, that `contract` is present but not pickable, and that
each entry carries a usable table and primary key.

## UT-NT-001-004 — EntityOptionsProvider

**File:** `tests/Unit/EntityOptionsProviderTest.php` · **FR:** FR-NT-001-002

Covers override-beats-responder-beats-table precedence, the `options_hook` key
and the camelCase fallback, memoisation of a responder to one call per type,
dropping a type with no options, and `labelFor()` returning `''` rather than
`null` for an unresolvable record.

## UT-NT-001-005 — NotesFilter

**File:** `tests/Unit/NotesFilterTest.php` · **FR:** FR-NT-001-005

Covers the "mine" default, `everyone`, naming a user directly, and that
`fromRequest()` reads `$_GET` only.

## UT-NT-001-006 — Views

**File:** `tests/Unit/NotesViewTest.php` · **FR:** FR-NT-001-004, FR-NT-001-005

Covers the summary table's `(no subject)` and `(not attached)` fallbacks, the
form's `(not found)` for a link whose target is gone, HTML escaping, the
`picker` gate on both form and filter dropdowns, and removal of an attachment
that dropped out of the submitted record list regardless of its role.

## UT-NT-001-007 — Link gateway

**File:** `tests/Unit/NotesLinkTest.php` · **FR:** FR-NT-001-006

Covers `notes_link_add()` de-duplication on
`(note_id, entity_type, entity_id, link_role)`, `notes_link_remove()`, and
`notes_link_find()`.

## UT-NT-001-008 — SQL builders

**File:** `tests/Unit/NotesSqlTest.php` · **FR:** FR-NT-001-006

Covers the insert/update/select SQL builders, the owner list using a
`SELECT DISTINCT owner` projection, and that `update_databases()` is passed
**bare** filenames with the note table ahead of the link table.

## UT-NT-001-009 — Installation and responders

**File:** `tests/Unit/NotesResponderTest.php` · **FR:** FR-NT-001-006,
FR-NT-001-010

Covers `activate_extension()` calling `update_databases()` once per table in
order, `ensure_composer_dependencies()` delegating to the copied bootstrap, and
the discovery methods returning the single `RESPONDERS` list.

---

## Gaps

Not covered, and not currently implemented either:

- **FR-NT-001-007** attachment upload — no implementation, so no tests.
- **FR-NT-001-008** hook-responder RBAC — the responders still perform no
  security-area check, so there is nothing to test yet.
- No integration test against a real MariaDB. The two-table create, the
  `0_` → `TB_PREF` substitution and the mixed-case `check_table()` gate are all
  verified only against the fake and by reading FA's source.
- No test that the bare-filename keys survive a real `update_databases()` run;
  that is asserted against a stub.
- The bare-vs-prefixed lifecycle hook divergence (FR-NT-001-010 item 5) is
  unresolved, so no test asserts which name a subscriber actually receives.