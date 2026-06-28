# Test Plan - ksf_FA_Notes

## Overview

This test plan covers unit and integration testing for the ksf_FA_Notes module (v2.0.0). Tests are organized by layer: database functions (notes_db.inc, links_db.inc), ACL enforcement, file upload integration, and OCR flow.

## Test Environment

| Component | Requirement |
|-----------|-------------|
| PHP | 7.4+ |
| PHPUnit | 9.5+ |
| MySQL | 5.7+ / MariaDB 10.2+ |
| FrontAccounting | 2.4+ (test helpers available) |
| ksf_FA_Attachments | Installed and active |
| OCR service | Mock endpoint for integration tests |
| Composer | For autoloading test dependencies |

## Test Database Setup

Each test suite uses a transaction-based rollback strategy:

```php
protected function setUp(): void {
    parent::setUp();
    global $db_connections;
    db_query("START TRANSACTION", "Could not start transaction");
}

protected function tearDown(): void {
    db_query("ROLLBACK", "Could not rollback");
    parent::tearDown();
}
```

## Test Suite Organization

```
tests/
├── Unit/
│   ├── NotesDbTest.php          # notes_db.inc CRUD + ACL
│   ├── LinksDbTest.php          # links_db.inc multi-entity linking
│   └── OcrServiceTest.php       # OCR integration helpers
├── Integration/
│   ├── FileUploadTest.php       # File upload via ksf_FA_Attachments
│   ├── OcrFlowTest.php          # End-to-end OCR with mock service
│   └── EntityResolutionTest.php # Multi-entity note resolution
└── Regression/
    └── BackwardCompatTest.php   # v1.0.0 data compatibility
```

---

## Unit Tests

### TP-NT-001: add_note() creates database row

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-001 |
| **FR** | FR-NT-001 |
| **Type** | Unit |
| **Priority** | Critical |

**Test**: Verify `add_note()` inserts a row and returns a positive integer note_id.

```php
public function testAddNoteCreatesRow(): void {
    $note_id = add_note(42, 'debtor', 'Test note', 'Comment', 'tester');
    $this->assertIsInt($note_id);
    $this->assertGreaterThan(0, $note_id);

    $note = get_note($note_id);
    $this->assertIsArray($note);
    $this->assertEquals('Test note', $note['note']);
    $this->assertEquals('debtor', $note['entity_type']);
    $this->assertEquals(42, (int)$note['entity_id']);
}
```

### TP-NT-002: add_note() returns valid note_id

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-002 |
| **FR** | FR-NT-001 |
| **Type** | Unit |
| **Priority** | Critical |

**Test**: Verify returned note_id matches the inserted row's ID.

### TP-NT-003: get_notes() returns notes for entity

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-003 |
| **FR** | FR-NT-002 |
| **Type** | Unit |
| **Priority** | High |

**Test**: Create multiple notes for an entity, verify `get_notes()` returns all in DESC order.

### TP-NT-004: update_note() modifies note text

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-004 |
| **FR** | FR-NT-003 |
| **Type** | Unit |
| **Priority** | High |

**Test**: `update_note()` changes the note text. Verify with `get_note()`.

### TP-NT-005: delete_note() removes row

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-005 |
| **FR** | FR-NT-003 |
| **Type** | Unit |
| **Priority** | High |

**Test**: `delete_note()` removes the row. Verify `get_note()` returns null/false.

### TP-NT-006: search_notes() finds matching keyword

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-006 |
| **FR** | FR-NT-004 |
| **Type** | Unit |
| **Priority** | Medium |

**Test**: Create notes with known keywords, search for keyword, verify only matching notes returned.

### TP-NT-007: Primary entity link stored correctly

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-007 |
| **FR** | FR-NT-005 |
| **Type** | Unit |
| **Priority** | High |

**Test**: Verify `entity_id` and `entity_type` are stored and retrievable via `get_notes()`.

### TP-NT-008: link_note_to_entity() creates link row

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-008 |
| **FR** | FR-NT-006 |
| **Type** | Unit |
| **Priority** | High |

**Test**: Create a note, link it to an additional entity, verify row exists in `fa_note_links`.

### TP-NT-009: get_linked_entities() returns all links

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-009 |
| **FR** | FR-NT-006 |
| **Type** | Unit |
| **Priority** | High |

**Test**: Link a note to multiple entities, verify `get_linked_entities()` returns all with correct entity_type and entity_id.

### TP-NT-010: View entity shows linked notes (Integration)

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-010 |
| **FR** | FR-NT-006 |
| **Type** | Integration |
| **Priority** | High |

**Test**: Create note linked to entity A via primary link and entity B via links table. Verify entity B's notes panel includes the note.

### TP-NT-011: Owner can edit own note

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-011 |
| **FR** | FR-NT-007 |
| **Type** | Unit |
| **Priority** | Critical |

**Test**: Create note with `owner = 1`. Call `update_note()` with `user_id = 1`. Should succeed.

### TP-NT-012: Non-owner cannot edit

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-012 |
| **FR** | FR-NT-007 |
| **Type** | Unit |
| **Priority** | Critical |

**Test**: Create note with `owner = 1`. Call `update_note()` with `user_id = 2`. Should throw or return false.

### TP-NT-013: Admin bypasses ACL

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-013 |
| **FR** | FR-NT-007 |
| **Type** | Unit |
| **Priority** | High |

**Test**: Create note with `owner = 1`. Call `update_note()` with `user_id = 3` where user 3 has `SA_ksf_FA_NotesMANAGE`. Should succeed.

### TP-NT-014: File stored via attachments module (Integration)

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-014 |
| **FR** | FR-NT-008 |
| **Type** | Integration |
| **Priority** | High |

**Test**: Simulate file upload during note creation. Verify `fa_attachments` has a row with `source_type = 'note'` and correct `source_id`.

### TP-NT-015: Attachment linked to note (Integration)

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-015 |
| **FR** | FR-NT-008 |
| **Type** | Integration |
| **Priority** | High |

**Test**: After upload, verify the attachment is retrievable via `get_attachments_for_source('note', $note_id)`.

### TP-NT-016: OCR flow with mock service (Integration)

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-016 |
| **FR** | FR-NT-009, FR-NT-010 |
| **Type** | Integration |
| **Priority** | Medium |

**Test**: Set up mock OCR endpoint. Upload image with OCR checked. Verify:
1. File stored as attachment
2. OCR service called with correct file
3. OCR text .txt file stored as second attachment
4. OCR text appended to note body

**Mock OCR Service Setup**:
```php
// Use PHP built-in server for mock OCR endpoint
// Mock returns: {"text": "Extracted text content", "confidence": 0.95}
```

### TP-NT-017: OCR text appended to note body

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-017 |
| **FR** | FR-NT-009, FR-NT-010 |
| **Type** | Unit |
| **Priority** | Medium |

**Test**: After OCR processing, verify note body contains the delimiter `--- OCR Text ---` followed by extracted text.

### TP-NT-018: OCR unreachable degrades gracefully (Integration)

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-018 |
| **FR** | FR-NT-011 |
| **Type** | Integration |
| **Priority** | Medium |

**Test**: Set OCR service URL to an unreachable address. Upload file with OCR checked. Verify:
1. Note is saved
2. File attachment is stored
3. No OCR attachment created
4. Error message displayed to user
5. Error logged

### TP-NT-019: Legacy data backward compatible (Regression)

| Field | Value |
|-------|-------|
| **Test ID** | TP-NT-019 |
| **FR** | FR-NT-001 through FR-NT-005 |
| **Type** | Regression |
| **Priority** | Critical |

**Test**: Create notes using v1.0.0 format (no owner, no group_id, no links). Verify all existing functions work identically. Legacy notes appear in all entity views.

---

## Test Execution

### Pre-Run Checklist

- [ ] Test database created and migrated with `install.sql`
- [ ] ksf_FA_Attachments module tables exist
- [ ] Mock OCR endpoint available (for integration tests)
- [ ] PHP `curl` extension enabled
- [ ] PHP `gd` or `imagick` extension enabled (image processing)
- [ ] `upload_tmp_dir` is writable by PHP

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit tests/

# Run unit tests only
./vendor/bin/phpunit tests/Unit/

# Run integration tests only (requires mock OCR endpoint)
./vendor/bin/phpunit tests/Integration/

# Run regression tests
./vendor/bin/phpunit tests/Regression/

# Run with coverage
./vendor/bin/phpunit --coverage-html ./coverage tests/
```

### Expected Coverage Targets

| Layer | Target Coverage |
|-------|-----------------|
| notes_db.inc | >= 90% |
| links_db.inc | >= 95% |
| OCR helper functions | >= 80% |
| ACL enforcement | 100% (critical path) |

---

## Test Data Fixtures

### Standard test entities

```php
const TEST_ENTITIES = [
    ['type' => 'debtor', 'id' => 1, 'name' => 'Test Customer'],
    ['type' => 'project', 'id' => 10, 'name' => 'Test Project'],
    ['type' => 'ticket', 'id' => 100, 'name' => 'Test Ticket'],
];

const TEST_USERS = [
    ['id' => 1, 'name' => 'Alice', 'role' => 'user'],
    ['id' => 2, 'name' => 'Bob', 'role' => 'user'],
    ['id' => 3, 'name' => 'Admin', 'role' => 'admin'],
];
```

### Test file fixtures

```php
// Create a small valid image for upload tests
function create_test_image(): string {
    $img = imagecreatetruecolor(100, 100);
    $color = imagecolorallocate($img, 255, 255, 255);
    imagestring($img, 5, 10, 40, 'Test OCR', $color);
    $path = tempnam(sys_get_temp_dir(), 'ocr_test_') . '.png';
    imagepng($img, $path);
    imagedestroy($img);
    return $path;
}
```

*Document Version: 2.0.0*
*Last Updated: 2026-06-28*
