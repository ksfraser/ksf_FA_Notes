-- ksf_FA_Notes Module Schema

-- Add ACL columns to fa_crm_notes
ALTER TABLE `0_fa_crm_notes`
    ADD COLUMN `owner` INT NULL COMMENT 'FK to FA users',
    ADD COLUMN `group_id` INT NULL COMMENT 'Access group ID for RBAC';

-- Multi-entity link table
CREATE TABLE IF NOT EXISTS `0_fa_note_links` (
    `id`            INT           NOT NULL AUTO_INCREMENT,
    `note_id`       INT           NOT NULL  COMMENT 'FK to fa_crm_notes.id',
    `entity_type`   VARCHAR(64)   NOT NULL  COMMENT 'Entity type (debtor, contact, opportunity, etc.)',
    `entity_id`     INT           NOT NULL  COMMENT 'FK to entity record',
    `created_at`    DATETIME      NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_note` (`note_id`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    UNIQUE KEY `uq_note_entity` (`note_id`, `entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Multi-entity links for notes';
