# AGENTS.md - ksf_FA_Notes#

## Architecture Overview#

**FA Module** for Notes/Comments - attach notes to transactions, contacts, and projects.

### Core Principles#
- **SOLID**, **DRY**, **TDD**, **DI**, **SRP**#

## Repository Structure#

```
ksf_FA_Notes/
├── sql/#
│   ├── fa_notes.sql#
│   └── fa_note_links.sql#
├── includes/#
│   ├── notes_db.inc#
│   └── links_db.inc#
├── pages/#
├── hooks.php#
├── composer.json#
└── ProjectDocs/#
```

## Dependencies#

- **ksf_FA_Notes_Core** (business logic)#
- **FrontAccounting 2.4+**#
