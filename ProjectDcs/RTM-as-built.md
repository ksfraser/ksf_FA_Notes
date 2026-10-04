# RTM as built — ksf_FA_Notes

`RTM.md` maps a BR/UC/Test matrix for the retired v1 design (`add_note()`,
`get_notes()`, `fa_note_links`, OCR). This file maps the requirements the code
actually satisfies to the classes and test methods that satisfy them.

Requirement definitions: `FR-as-built.md`.
Test detail: `UT-as-built.md`.

| FR | Requirement | Implementation | Tests |
|----|-------------|----------------|-------|
| FR-NT-001-001 | Note creation | `NoteCreationService`, `CREATE_NOTE` | UT-NT-001-001 |
| FR-NT-001-002 | Entity registry and responder options | `includes/entity_types.inc`, `EntityOptionsProvider` | UT-NT-001-003, UT-NT-001-004 |
| FR-NT-001-003 | Retrieval and search | `NoteQueryService`, `GET_NOTE`, `SEARCH_NOTES`, `GET_NOTES_FOR_ENTITY` | UT-NT-001-002 |
| FR-NT-001-004 | Data-entry form | `NoteFormView`, `pages/notes.php` | UT-NT-001-006 |
| FR-NT-001-005 | Summary table and filters | `NotesFilter`, `NoteFilterBar`, `NoteSummaryTableView` | UT-NT-001-005, UT-NT-001-006 |
| FR-NT-001-006 | Schema ownership and installation | `sql/ksf_notes.sql`, `sql/ksf_notes_link.sql`, `activate_extension()` | UT-NT-001-007, UT-NT-001-008, UT-NT-001-009 |
| FR-NT-001-007 | Attachment upload | *none* | *none* |
| FR-NT-001-008 | Access control | `pages/notes.php`, `install_access()` | partial — responders unchecked |
| FR-NT-001-009 | Bootstrap and dependencies | `ComposerDependencies.php`, `hooks.php` | UT-NT-001-009 |
| FR-NT-001-010 | Responder contract, lifecycle hooks | `hooks.php`, `includes/events.inc` | UT-NT-001-009 |

Coverage: **8 of 10 requirements implemented and tested.** FR-NT-001-007 is
unimplemented. FR-NT-001-008 is partial: the page enforces its security areas,
the hook responders do not.

## `@BABOK` annotation index

Every code class and test file carries `@BABOK Related:` tags. The reverse map:

| Annotation | Found in |
|-----------|----------|
| FR-NT-001-001 | `Service/NoteCreationService.php`, `tests/Unit/NoteServiceTest.php` |
| FR-NT-001-002 | `Entity/EntityOptionsProvider.php`, `includes/entity_types.inc`, `tests/Unit/EntityTypesTest.php`, `tests/Unit/EntityOptionsProviderTest.php` |
| FR-NT-001-003 | `Service/NoteQueryService.php`, `includes/ksf_notes_db.inc`, `tests/Unit/NoteServiceTest.php` |
| FR-NT-001-004 | `View/NoteFormView.php`, `pages/notes.php`, `tests/Unit/NotesViewTest.php` |
| FR-NT-001-005 | `DTO/NotesFilter.php`, `View/NoteFilterBar.php`, `View/NoteSummaryTableView.php`, `View/AbstractListView.php`, `tests/Unit/NotesFilterTest.php` |
| FR-NT-001-006 | `includes/ksf_notes_link_db.inc`, `sql/*.sql`, `hooks.php`, `tests/Unit/NotesLinkTest.php`, `tests/Unit/NotesSqlTest.php` |

Regenerate this table after changing annotations:

```bash
grep -rhoE 'FR-NT-[0-9-]+' src/ includes/ pages/ tests/ | sort -u
```