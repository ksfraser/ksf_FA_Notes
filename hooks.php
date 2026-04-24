<?php
/**
 * FA_Notes Module Hooks for FrontAccounting
 */

$module_name = 'FA_Notes';
$module_version = '1.0.0';
$module_description = 'Reusable Notes System - polymorphic notes for CRM entities';
$module_author = 'KSFII Development Team';
$module_category = 'CRM';

function fa_notes_install(): bool
{
    global $db;

    @include_once __DIR__ . '/vendor-src/Ksfraser/Common/ComposerDependencyManager.php';
    if (class_exists('Ksfraser\Common\ComposerDependencyManager')) {
        $composerMgr = new \Ksfraser\Common\ComposerDependencyManager(__DIR__);
        $composerMgr->ensureDependencies();
        @include_once $composerMgr->getAutoloadPath();
    }

    if (!fa_notes_create_tables()) return false;
    return true;
}

function fa_notes_activate(): bool
{
    @include_once __DIR__ . '/vendor-src/Ksfraser/Common/ComposerDependencyManager.php';
    if (class_exists('Ksfraser\Common\ComposerDependencyManager')) {
        $composerMgr = new \Ksfraser\Common\ComposerDependencyManager(__DIR__);
        $composerMgr->ensureDependencies();
        @include_once $composerMgr->getAutoloadPath();
    }

    add_hook('note_added', 'fa_notes_on_note_added');
    add_hook('note_updated', 'fa_notes_on_note_updated');
    add_hook('note_deleted', 'fa_notes_on_note_deleted');
    return true;
}

function fa_notes_deactivate(): bool { return true; }
function fa_notes_uninstall(): bool { return true; }

function fa_notes_create_tables(): bool
{
    global $db;

    $sql = "CREATE TABLE IF NOT EXISTS `" . TB_PREF . "fa_crm_notes` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `entity_id` INT(11) NOT NULL,
        `entity_type` VARCHAR(20) NOT NULL,
        `note_type` VARCHAR(20) DEFAULT 'Comment',
        `note` TEXT NOT NULL,
        `created_by` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_entity` (`entity_id`, `entity_type`),
        KEY `idx_note_type` (`note_type`),
        KEY `idx_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return db_query($sql, "Could not create fa_crm_notes table");
}

function fa_notes_on_note_added($noteId) { error_log("Note added: $noteId"); }
function fa_notes_on_note_updated($noteId) { error_log("Note updated: $noteId"); }
function fa_notes_on_note_deleted($noteId) { error_log("Note deleted: $noteId"); }