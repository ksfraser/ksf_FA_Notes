<?php
/**
 * FA_Notes Module Hooks for FrontAccounting
 */

define('SS_NOTES', 128 << 8);

class hooks_fa_notes extends hooks {
    var $module_name = 'fa_notes';
    var $version = '1.0.0';

    function install_options($app) {
        global $path_to_root;

        switch($app->id) {
            case 'CRM':
                $app->add_lapp_function(0, _("Notes"),
                    $path_to_root."/modules/".$this->module_name."/notes.php", 'SA_NOTESVIEW', MENU_ENTRY);
                break;
        }
    }

    function install_access() {
        $security_sections[SS_NOTES] = _("Notes Management");
        $security_areas['SA_NOTESVIEW'] = array(SS_NOTES | 1, _("View Notes"));
        $security_areas['SA_NOTESMANAGE'] = array(SS_NOTES | 2, _("Manage Notes"));
        return array($security_areas, $security_sections);
    }

    function activate_extension($company, $check_only=true) {
        $updates = array('sql/update.sql' => array($this->module_name));
        $ok = $this->update_databases($company, $updates, $check_only);
        if ($check_only || !$ok) {
            return $ok;
        }
        $this->ensure_notes_schema();
        return $ok;
    }

    private function table_exists($table) {
        $sql = "SHOW TABLES LIKE " . db_escape($table);
        $res = db_query($sql, 'Failed checking table existence');
        return db_num_rows($res) > 0;
    }

    private function ensure_notes_schema() {
        $tables = array(
            TB_PREF . "fa_crm_notes" => "
                CREATE TABLE IF NOT EXISTS `" . TB_PREF . "fa_crm_notes` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        foreach ($tables as $table_name => $sql) {
            db_query($sql, "Could not create Notes table: $table_name");
        }
    }

    function db_prevoid($trans_type, $trans_no) {
        // Handle voiding if needed
    }
}
?>
