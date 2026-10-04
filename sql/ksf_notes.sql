-- ksf_FA_Notes — note records
--
-- A note is a free-standing observation owned by this module. It carries no
-- foreign keys: every association to another record (customer, contact,
-- meeting, opportunity, contract, project, task, calendar entry, attachment)
-- lives in 0_ksf_Notes_link so one note can reference many records and one
-- record can be referenced by many notes.
--
-- Table names use the literal 0_ prefix: db_import() substitutes the real
-- company prefix, and check_table() gates on 'ksf_Notes'.

CREATE TABLE IF NOT EXISTS `0_ksf_Notes` (
    `id`          INT(11)       NOT NULL AUTO_INCREMENT,
    `subject`     VARCHAR(200)  NULL           COMMENT 'Optional title, aids search and lists',
`note_type`   VARCHAR(32)   NOT NULL DEFAULT 'Comment'
                                    COMMENT 'Free text; see notes_note_types() for the offered list',
    `note`        MEDIUMTEXT    NOT NULL,
    `created_by`  VARCHAR(50)   NULL           COMMENT 'FA user id, matches fa_cal_entries.user_id',
    `owner`       INT(11)       NULL           COMMENT 'FK to FA users, drives ACL',
    `group_id`    INT(11)       NULL           COMMENT 'RBAC access group',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified_at` DATETIME      NULL,
    `inactive`    TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Soft delete flag',
    PRIMARY KEY (`id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_owner` (`owner`),
    KEY `idx_note_type` (`note_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Free-standing notes owned by ksf_FA_Notes';