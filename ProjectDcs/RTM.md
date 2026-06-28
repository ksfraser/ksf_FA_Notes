# Requirements Traceability Matrix (RTM) - ksf_FA_Notes

## Overview

This Requirements Traceability Matrix maps Business Requirements (BR) to Functional Requirements (FR), User Requirements (UR), and Use Cases (UC) for the ksf_FA_Notes module. It covers both existing v1.0.0 features and new v2.0.0 features (multi-entity linking, ACL, file upload, OCR).

## Traceability Matrix

| BR ID | BR Title | FR ID(s) | UC ID(s) | Test Coverage |
|-------|----------|----------|----------|---------------|
| BR-NT-001 | Note Creation | FR-NT-001 | UC-FA-NT-001, UC-FA-NT-002 | TP-NT-001, TP-NT-002 |
| BR-NT-002 | Note Retrieval | FR-NT-002 | UC-FA-NT-001 | TP-NT-003 |
| BR-NT-003 | Note Editing and Deletion | FR-NT-003 | UC-FA-NT-001 | TP-NT-004, TP-NT-005 |
| BR-NT-004 | Note Search | FR-NT-004 | — | TP-NT-006 |
| BR-NT-005 | Single-Entity Linking | FR-NT-005 | UC-FA-NT-001 | TP-NT-007 |
| BR-NT-006 | Multi-Entity Linking | FR-NT-006 | UC-FA-NT-005 | TP-NT-008, TP-NT-009, TP-NT-010 |
| BR-NT-007 | Access Control on Notes | FR-NT-007 | UC-FA-NT-006 | TP-NT-011, TP-NT-012, TP-NT-013 |
| BR-NT-008 | File Attachment Upload | FR-NT-008 | UC-FA-NT-003 | TP-NT-014, TP-NT-015 |
| BR-NT-009 | OCR Text Extraction | FR-NT-009, FR-NT-010, FR-NT-011 | UC-FA-NT-004 | TP-NT-016, TP-NT-017, TP-NT-018 |
| BR-NT-010 | Backward Compatibility | FR-NT-001 through FR-NT-005 | UC-FA-NT-001, UC-FA-NT-002 | TP-NT-019 |

## Detailed Mapping

### BR-NT-001: Note Creation
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-001 | Create Note |
| UC | UC-FA-NT-001 | Add Note to Customer |
| UC | UC-FA-NT-002 | Quick Note Entry |
| Test | TP-NT-001 | Unit test: `add_note()` creates DB row |
| Test | TP-NT-002 | Unit test: `add_note()` returns valid note_id |

### BR-NT-002: Note Retrieval
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-002 | View Notes |
| UC | UC-FA-NT-001 | Add Note to Customer (view notes panel) |
| Test | TP-NT-003 | Unit test: `get_notes()` returns notes for entity |

### BR-NT-003: Note Editing and Deletion
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-003 | Edit and Delete Notes |
| UC | UC-FA-NT-001 | Add Note to Customer (edit/delete) |
| Test | TP-NT-004 | Unit test: `update_note()` modifies note text |
| Test | TP-NT-005 | Unit test: `delete_note()` removes row |

### BR-NT-004: Note Search
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-004 | Search Notes |
| Test | TP-NT-006 | Unit test: `search_notes()` finds matching notes |

### BR-NT-005: Single-Entity Linking
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-005 | Link Note to Primary Entity |
| UC | UC-FA-NT-001 | Add Note to Customer |
| Test | TP-NT-007 | Unit test: entity_id/entity_type stored correctly |

### BR-NT-006: Multi-Entity Linking
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-006 | Link Note to Multiple Entities |
| UC | UC-FA-NT-005 | Link Note to Multiple Entities |
| Test | TP-NT-008 | Unit test: `link_note_to_entity()` creates link row |
| Test | TP-NT-009 | Unit test: `get_linked_entities()` returns all links |
| Test | TP-NT-010 | Integration test: view entity shows linked notes |

### BR-NT-007: Access Control
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-007 | ACL Enforcement on Notes |
| UC | UC-FA-NT-006 | Set Note Owner/Group Permissions |
| Test | TP-NT-011 | Unit test: owner can edit own note |
| Test | TP-NT-012 | Unit test: non-owner cannot edit |
| Test | TP-NT-013 | Unit test: admin bypasses ACL |

### BR-NT-008: File Attachment Upload
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-008 | File Upload on Note Form |
| UC | UC-FA-NT-003 | Upload File Attachment to Note |
| Test | TP-NT-014 | Integration test: file stored via ksf_FA_Attachments |
| Test | TP-NT-015 | Integration test: attachment linked to note |

### BR-NT-009: OCR Text Extraction
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-009 | OCR Processing of Uploaded Image |
| FR | FR-NT-010 | Store OCR Text as Attachment |
| FR | FR-NT-011 | Graceful Degradation for OCR |
| UC | UC-FA-NT-004 | Send Attached Image to OCR |
| Test | TP-NT-016 | Integration test: OCR flow with mock service |
| Test | TP-NT-017 | Unit test: OCR text appended to note body |
| Test | TP-NT-018 | Integration test: OCR service unreachable degrades gracefully |

### BR-NT-010: Backward Compatibility
| Artifact | ID | Description |
|----------|----|-------------|
| FR | FR-NT-001 through FR-NT-005 | Existing features unchanged |
| UC | UC-FA-NT-001, UC-FA-NT-002 | Existing use cases unchanged |
| Test | TP-NT-019 | Regression test: legacy data flows unchanged |

## Traceability by Use Case

| UC ID | UC Title | FR ID(s) | BR ID(s) |
|-------|----------|----------|----------|
| UC-FA-NT-001 | Add Note to Customer | FR-NT-001, FR-NT-002, FR-NT-003, FR-NT-005 | BR-NT-001, BR-NT-002, BR-NT-003, BR-NT-005 |
| UC-FA-NT-002 | Quick Note Entry | FR-NT-001, FR-NT-005 | BR-NT-001, BR-NT-005 |
| UC-FA-NT-003 | Upload File Attachment to Note | FR-NT-008 | BR-NT-008 |
| UC-FA-NT-004 | Send Attached Image to OCR | FR-NT-009, FR-NT-010, FR-NT-011 | BR-NT-009 |
| UC-FA-NT-005 | Link Note to Multiple Entities | FR-NT-006 | BR-NT-006 |
| UC-FA-NT-006 | Set Note Owner/Group Permissions | FR-NT-007 | BR-NT-007 |

## Traceability by Test

| Test ID | Test Title | FR ID(s) | BR ID(s) | UC ID(s) |
|---------|------------|----------|----------|----------|
| TP-NT-001 | add_note() creates DB row | FR-NT-001 | BR-NT-001 | UC-FA-NT-001 |
| TP-NT-002 | add_note() returns valid note_id | FR-NT-001 | BR-NT-001 | UC-FA-NT-001 |
| TP-NT-003 | get_notes() returns notes | FR-NT-002 | BR-NT-002 | UC-FA-NT-001 |
| TP-NT-004 | update_note() modifies text | FR-NT-003 | BR-NT-003 | UC-FA-NT-001 |
| TP-NT-005 | delete_note() removes row | FR-NT-003 | BR-NT-003 | UC-FA-NT-001 |
| TP-NT-006 | search_notes() finds matches | FR-NT-004 | BR-NT-004 | — |
| TP-NT-007 | Primary entity link storage | FR-NT-005 | BR-NT-005 | UC-FA-NT-001 |
| TP-NT-008 | link_note_to_entity() creates link | FR-NT-006 | BR-NT-006 | UC-FA-NT-005 |
| TP-NT-009 | get_linked_entities() returns all | FR-NT-006 | BR-NT-006 | UC-FA-NT-005 |
| TP-NT-010 | View entity shows linked notes | FR-NT-006 | BR-NT-006 | UC-FA-NT-005 |
| TP-NT-011 | Owner can edit own note | FR-NT-007 | BR-NT-007 | UC-FA-NT-006 |
| TP-NT-012 | Non-owner cannot edit | FR-NT-007 | BR-NT-007 | UC-FA-NT-006 |
| TP-NT-013 | Admin bypasses ACL | FR-NT-007 | BR-NT-007 | UC-FA-NT-006 |
| TP-NT-014 | File stored via attachments module | FR-NT-008 | BR-NT-008 | UC-FA-NT-003 |
| TP-NT-015 | Attachment linked to note | FR-NT-008 | BR-NT-008 | UC-FA-NT-003 |
| TP-NT-016 | OCR flow with mock service | FR-NT-009, FR-NT-010 | BR-NT-009 | UC-FA-NT-004 |
| TP-NT-017 | OCR text appended to note body | FR-NT-009, FR-NT-010 | BR-NT-009 | UC-FA-NT-004 |
| TP-NT-018 | OCR unreachable degrades gracefully | FR-NT-011 | BR-NT-009 | UC-FA-NT-004 |
| TP-NT-019 | Legacy data backward compatible | FR-NT-001–FR-NT-005 | BR-NT-010 | UC-FA-NT-001, UC-FA-NT-002 |

## Verification Status

| BR ID | FR Coverage | UC Coverage | Test Coverage | Status |
|-------|-------------|-------------|---------------|--------|
| BR-NT-001 | 1/1 | 2/2 | 2/2 | ✅ |
| BR-NT-002 | 1/1 | 1/1 | 1/1 | ✅ |
| BR-NT-003 | 1/1 | 1/1 | 2/2 | ✅ |
| BR-NT-004 | 1/1 | 0/0 | 1/1 | ✅ |
| BR-NT-005 | 1/1 | 1/1 | 1/1 | ✅ |
| BR-NT-006 | 1/1 | 1/1 | 3/3 | ✅ |
| BR-NT-007 | 1/1 | 1/1 | 3/3 | ✅ |
| BR-NT-008 | 1/1 | 1/1 | 2/2 | ✅ |
| BR-NT-009 | 3/3 | 1/1 | 3/3 | ✅ |
| BR-NT-010 | 5/5 | 2/2 | 1/1 | ✅ |

*Document Version: 2.0.0*
*Last Updated: 2026-06-28*
