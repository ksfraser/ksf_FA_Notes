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

## FA-Specific Features

### Database Integration
- FA-compliant tables: `fa_notes`, `fa_note_attachments`
- Links to FA customers, projects, users

### UI Integration
- FA form for note entry
- Note tabs on customer/project views
- Quick-add note buttons

## Link to Core BR
This adapter implements: `/home/kevin/Documents/ksf_Notes/ProjectDcs/Business Requirements.md`

*Document Version: 1.0.0*
*Last Updated: 2026-05-11*