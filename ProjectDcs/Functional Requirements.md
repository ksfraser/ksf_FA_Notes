# Functional Requirements — ksf_FA_Notes (redirect)

> **SUPERSEDED — see [`FR-as-built.md`](FR-as-built.md).**
>
> The former `Functional Requirements.md` described the retired v1 design and has
> been removed. The current requirements are keyed `FR-NT-001-001` …
> `FR-NT-001-010` and match the `@BABOK Related:` annotations in the code.

The retired document specified `fa_crm_notes` / `fa_note_links` tables,
`add_note()` / `get_notes()` helpers, an `entity_id` + `entity_type` pair of
columns carried on the note row itself, and a security area named
`SA_ksf_FA_NotesVIEW`. None of that is what the module does.

The current design owns `0_ksf_Notes` and `0_ksf_Notes_link`, keeps every
association in the link table so one note can reference many records, and uses
`SA_NOTES_VIEW` / `SA_NOTES_MANAGE`.