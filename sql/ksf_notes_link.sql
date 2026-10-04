-- ksf_FA_Notes — note to record links (the cross-reference x-link)
--
-- One row per (note, referenced record). entity_id is VARCHAR because the
-- referenced primary keys are not uniform across the estate:
--   0_crm_persons.id            INT   (native person: customer contacts AND employees)
--   0_ksf_crm_meetings.id       INT
--   0_ksf_crm_opportunities.id  INT
--   0_attachments.id            INT UNSIGNED
--   0_debtors_master.debtor_no  VARCHAR
--   0_fa_pm_projects.project_id VARCHAR(20)
--   0_fa_pm_tasks.task_id       VARCHAR(20)
--   0_fa_cal_entries.id         INT
--   0_ksf_contract.id           INT   (ksf_FA_Contract, not yet built)
--
-- link_role records WHY a record is referenced, so a single note can express
-- "meeting with a client regarding a project due to a contract":
--   with      the note is about / held with this record (a person)
--   regarding the subject matter of the note
--   attendee  a person attending the referenced meeting
--   document  a file attached to the note (entity_type = 'attachment')
--
-- Table names use the literal 0_ prefix: db_import() substitutes the real
-- company prefix, and check_table() gates on 'ksf_Notes_link'.

CREATE TABLE IF NOT EXISTS `0_ksf_Notes_link` (
    `id`          INT(11)      NOT NULL AUTO_INCREMENT,
    `note_id`     INT(11)      NOT NULL COMMENT 'FK to ksf_Notes.id',
    `entity_type` VARCHAR(32)  NOT NULL COMMENT 'Registry key, see includes/entity_types.inc',
    `entity_id`   VARCHAR(64)  NOT NULL COMMENT 'PK of the referenced record',
    `link_role`   VARCHAR(32)  NOT NULL DEFAULT 'reference'
                                  COMMENT 'regarding, with, attendee, document',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_note_entity` (`note_id`, `entity_type`, `entity_id`, `link_role`),
    KEY `idx_note` (`note_id`),
    KEY `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cross-reference links from a note to any other record';