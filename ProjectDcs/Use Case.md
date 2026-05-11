# Use Cases - ksf_FA_Notes

## Overview
ksf_FA_Notes provides the FrontAccounting UI for ksf_Notes.

## Note vs Document Clarification

### Notes (ksf_Notes)
Quick text entries with optional attachments:
- "Quick note about customer call"
- "Meeting summary"
- Can attach supporting file (optional)

### Documents (ksf_Documents)
File-centric records:
- "Contract.pdf" with description "Signed service agreement"
- "ID_Scan.jpg" with description "Government ID for compliance"
- Primary purpose is the file

---

## UC-FA-NT-001: Add Note to Customer
**Actor**: Sales Rep (FA User)

**FA-Specific Flow**:
1. Open customer in FA-CRM
2. Click "Add Note" tab
3. Enter note text
4. Optionally attach file
5. Save → stored in `fa_notes`

---

## UC-FA-NT-002: Quick Note Entry
**Actor**: Any FA User

**FA-Specific Flow**:
1. Use global "Quick Note" in header
2. Enter note
3. Link to entity (customer, project)
4. Save

---

## Reference Use Cases
- Core UC: ksf_Notes/ProjectDcs/Use Case.md (UC-NT-001 through UC-NT-010)

*Document Version: 1.0.0*
*Last Updated: 2026-05-11*