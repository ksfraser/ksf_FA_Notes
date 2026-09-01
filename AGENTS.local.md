<!-- Repo-specific appendix to the shared AGENTS.md. Generic conventions live in AGENTS_ARCH.md (hardlinked). -->

```markdown
# AGENTS.local.md — ksf_FA_Notes
## Overview
**FA Module** for Notes/Comments — attach notes to transactions, contacts, and projects.
## Repository Structure
```
ksf_FA_Notes/
├── sql/
│   ├── fa_notes.sql
│   └── fa_note_links.sql
├── includes/
│   ├── notes_db.inc
│   └── links_db.inc
├── pages/
├── hooks.php
├── composer.json
└── ProjectDocs/
```
## Dependencies
- **ksf_FA_Notes_Core** (business logic)
- **FrontAccounting 2.4+**
```
