<?php
/**
 * KSF FrontAccounting Module Hooks — ksf_FA_Notes
 *
 * Notes is a shared capability: it owns 0_ksf_Notes and 0_ksf_Notes_link and
 * lets CRM, HRM and Project Management hang notes off their own records
 * without knowing anything about each other. Consumers talk to it through the
 * hook responders CREATE_NOTE / GET_NOTE / SEARCH_NOTES / GET_NOTES_FOR_ENTITY.
 *
 * STANDARD PATTERNS:
 *
 * 1. ADDING MODULE TABS
 *    Define a class extending 'application' in hooks.php.
 *    Return new instance from install_tabs().
 *    Include add_extensions() to load other modules' install_options.
 *
 * 2. ADDING MENU ITEMS TO EXISTING APPS
 *    Use install_options() with switch($app->id).
 *    Use add_module_app() + add_rapp_function() for new menu sections.
 *
 * 3. DATABASE SCHEMA
 *    DO NOT create tables in PHP code.
 *    Ship one sql/<table>.sql per table and call $this->update_databases()
 *    from activate_extension().
 *    Table names use the literal 0_ prefix: db_import() substitutes the real
 *    company prefix. @TB_PREF@ / {TB_PREF} are NOT substituted by the FA
 *    install path and will fail. Schema work belongs in activate_extension(),
 *    not install_extension(), which is a no-op stub in the base hooks class.
 *
 * 4. SECURITY
 *    Define SS_<MODULE> constant (section << 8).
 *    Define SA_<MODULE>_<ACTION> in install_access().
 *
 * @package KsfFA_ksf_FA_Notes
 * @version 2.4.3
 */

define('SS_ksf_FA_Notes', 131 << 8);

/**
 * Security areas.
 *
 * Declared as constants rather than inline in install_access() so pages and
 * other modules can reference them: pages/notes.php needs SA_NOTES_VIEW and
 * SA_NOTES_MANAGE before install_access() has run.
 */
if (!defined('SA_NOTES_VIEW')) {
    define('SA_NOTES_VIEW', SS_ksf_FA_Notes | 1);
    define('SA_NOTES_MANAGE', SS_ksf_FA_Notes | 2);
}

// ComposerDependencies.php is a per-module copy of
// ksf_FA_Common/src/Utils/ComposerDependencies.template.php with MODULENAME
// replaced in the namespace. It is copied rather than autoloaded precisely
// because it is what MAKES the autoloader: a PSR-4 autoloader cannot be used
// to load the thing that installs the vendor tree. Requiring it first breaks
// that chicken-and-egg loop, and the namespace-scoped guard inside it means
// several modules can each carry their own copy without redeclaring anything.
require_once __DIR__ . '/ComposerDependencies.php';

\ksfraser\FrontAccounting\Notes\Utils\ComposerDependencies::ensure(__DIR__);

$autoload_path = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

require_once __DIR__ . '/includes/entity_types.inc';
require_once __DIR__ . '/includes/ksf_notes_db.inc';
require_once __DIR__ . '/includes/ksf_notes_link_db.inc';
require_once __DIR__ . '/includes/events.inc';

use ksfraser\FrontAccounting\Common\Traits\WorkflowHooksTrait;
use ksfraser\FrontAccounting\Notes\Service\NoteCreationService;
use ksfraser\FrontAccounting\Notes\Service\NoteQueryService;

class hooks_ksf_FA_Notes extends hooks {
    use Ksfraser\Traits\HookQueryProviderTrait;

    // Shared lifecycle traits from ksf_FA_Common. registerWorkflowType() maps a
    // record type to a hook prefix; fireWorkflowHooks() runs the ordered
    // before_save -> save -> new/edited -> after_save sequence. Notes keeps its
    // own events.inc listeners for that, so the trait is the seam other modules
    // hook into rather than a second dispatch path.
    use WorkflowHooksTrait;

    /**
     * The hook names this module answers to. One list, so getModuleCapabilities()
     * and hasCapability() cannot drift apart, and so _getAdvertisedValues() has
     * something to advertise.
     */
    const RESPONDERS = array('CREATE_NOTE', 'GET_NOTE', 'SEARCH_NOTES', 'GET_NOTES_FOR_ENTITY');

    var $module_name = 'ksf_FA_Notes';
    var $version = '2.4.3-0';

    public function __construct()
    {
        parent::__construct();

        $this->registerWorkflowType('note', 'NOTES_NOTE');
    }

    /**
     * Add module tab.
     *
     * Notes is reached from the Orders app's menu rather than owning a tab,
     * because a note is always about a record owned by another module.
     *
     * @param application|null $app Ignored
     * @return application|null
     */
    function install_tabs($app) {
        // No dedicated app tab; see install_options().
    }

    /**
     * Add menu items to existing FA applications.
     *
     * @param application $app FA application instance
     * @return void
     */
    function install_options($app) {
        if ($app->id == 'orders' && method_exists($this, 'add_module_app')) {
            $this->add_module_app(
                'notes',
                _("Notes"),
                'modules/ksf_FA_Notes/pages/notes.php',
                SA_NOTES_VIEW
            );
        }
    }

    /**
     * Define security areas.
     *
     * @return array [0] => security_areas, [1] => security_sections
     */
    function install_access() {
        $security_sections[SS_ksf_FA_Notes] = _("Notes");
        $security_areas['SA_NOTES_VIEW'] = array(SA_NOTES_VIEW, _("View notes"));
        $security_areas['SA_NOTES_MANAGE'] = array(SA_NOTES_MANAGE, _("Manage notes"));

        return array($security_areas, $security_sections);
    }

    /**
     * Activate extension — the only base hook that does real work.
     *
     * Imports the schema via update_databases(), which delegates to db_import()
     * per file and gates each import on check_table(). Re-running is safe:
     * db_import() ignores MySQL error 1050 (table exists) in forced mode.
     *
     * @param int  $company    Company number
     * @param bool $check_only Only report whether activation is possible
     * @return bool
     */
    function activate_extension($company, $check_only = true)
    {
        if ($check_only) {
            return true;
        }

        $this->ensure_composer_dependencies();

        // One entry per table, each gated on its own table name. update_databases()
        // builds the path itself as <path_to_root>/modules/<module_name>/sql/<file>,
        // so the keys here are BARE filenames: passing an absolute path would be
        // appended to that prefix and the import would silently find nothing.
        //
        // Notes before links, so the link table is never created ahead of the
        // table it points at.
        $this->update_databases($company, array(
            'ksf_notes.sql' => array('ksf_Notes'),
            'ksf_notes_link.sql' => array('ksf_Notes_link'),
        ), false);

        return true;
    }

    /**
     * Install composer dependencies if needed.
     *
     * @return void
     */
    private function ensure_composer_dependencies()
    {
        // The shared per-module copy, not a private reimplementation of
        // composer install. Top of file already ran it; this is the re-check
        // for the case where activation runs before any page load did.
        \ksfraser\FrontAccounting\Notes\Utils\ComposerDependencies::ensure(__DIR__);
    }

    // ------------------------------------------------------------------
    // Inter-module discovery contract (AGENTS_ARCH 11)
    // ------------------------------------------------------------------

    /**
     * Advertise this module's constants.
     *
     * @param array $data Modified by reference
     * @return bool
     */
    public function getModuleConstants(&$data, $opts = null)
    {
        $data['constants'] = array(
            'SS_ksf_FA_Notes' => SS_ksf_FA_Notes,
            'SA_NOTES_VIEW' => SA_NOTES_VIEW,
            'SA_NOTES_MANAGE' => SA_NOTES_MANAGE,
        );

        return true;
    }

    /**
     * Advertise this module's capabilities.
     *
     * @param array $data Modified by reference
     * @return bool
     */
    public function getModuleCapabilities(&$data, $opts = null)
    {
        $data['capabilities'] = self::RESPONDERS;

        return true;
    }

    /**
     * Check for a specific capability.
     *
     * @param array $data Modified by reference; $opts may carry 'capability'
     * @return bool
     */
    public function hasCapability(&$data, $opts = null)
    {
        $capability = is_array($opts) && isset($opts['capability']) ? $opts['capability'] : null;
        if ($capability === null && isset($data['capability'])) {
            $capability = $data['capability'];
        }

        $data['has_capability'] = in_array($capability, self::RESPONDERS, true);

        return $data['has_capability'];
    }

    /**
     * Generic discovery responder.
     *
     * One entry point for a caller that does not want to know this module's
     * method names: it sends a request string and gets constants, capabilities
     * or a single capability check back.
     *
     * @param array      $data Modified by reference; 'request' may name it
     * @param array|null $opts May carry 'request'
     * @return mixed
     */
    public function respondToCapabilityRequest(&$data, $opts = null)
    {
        $request = is_array($opts) && isset($opts['request'])
            ? $opts['request']
            : (isset($data['request']) ? $data['request'] : 'capabilities');

        $data['request'] = $request;
        $data['module'] = $this->module_name;

        if (strpos($request, 'has:') === 0) {
            return $this->hasCapability($data, array('capability' => substr($request, 4)));
        }

        switch ($request) {
            case 'capabilities':
                return $this->getModuleCapabilities($data, $opts);
            case 'constants':
                return $this->getModuleConstants($data, $opts);
            default:
                $data['error'] = 'Unknown request type: ' . $request;

                return null;
        }
    }

    /**
     * Generic responder: create a note.
     *
     * $opts carries the note fields plus an optional 'links' array of
     * (entity_type, entity_id, link_role) triples, so a caller can create a
     * note and its cross-references in one call.
     *
     * @param array $data Modified by reference; result returned in $data
     * @return bool
     */
    public function CREATE_NOTE(&$data, $opts = null)
    {
        $result = (new NoteCreationService())->create($data, $opts);

        if (!$result['ok']) {
            $data['error'] = $result['error'];

            return false;
        }

        $data['note_id'] = $result['note_id'];
        $data['links_added'] = $result['links_added'];

        return true;
    }

    /**
     * Generic responder: fetch one note with its resolved links.
     *
     * @param array $data Modified by reference; result returned in $data
     * @return bool False when the note does not exist
     */
    public function GET_NOTE(&$data, $opts = null)
    {
        $result = (new NoteQueryService())->find(notes_opt($opts, $data, 'note_id'));

        if (!$result['ok']) {
            $data['error'] = $result['error'];

            return false;
        }

        $data['note'] = $result['note'];

        return true;
    }

    /**
     * Generic responder: search notes.
     *
     * @param array $data Modified by reference; result returned in $data
     * @return bool
     */
    public function SEARCH_NOTES(&$data, $opts = null)
    {
        $result = (new NoteQueryService())->search($data, $opts);

        $data['notes'] = $result['notes'];
        $data['count'] = $result['count'];

        return true;
    }

    /**
     * Generic responder: every note attached to one record.
     *
     * This is the read path CRM/HRM/ProjectManagement use to show the notes
     * belonging to one of their records.
     *
     * @param array $data Modified by reference; result returned in $data
     * @return bool
     */
    public function GET_NOTES_FOR_ENTITY(&$data, $opts = null)
    {
        $result = (new NoteQueryService())->forEntity($data, $opts);

        if (!$result['ok']) {
            $data['error'] = $result['error'];

            return false;
        }

        $data['notes'] = $result['notes'];
        $data['count'] = $result['count'];

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function _getAdvertisedValues(): array
    {
        $events = ['before_save', 'after_save', 'before_delete', 'after_delete'];

        // WorkflowHooksTrait maps record type 'note' to prefix 'NOTES_NOTE', so a
        // subscriber listens for NOTES_NOTE_before_save rather than the bare
        // before_save that the gateway dispatches.
        $prefixed = array();

        foreach ($events as $event) {
            $prefixed[] = 'NOTES_NOTE_' . $event;
        }

        return array(
            'notes.responders' => self::RESPONDERS,
            'notes.entity_types' => array('note', 'note_link'),
            'notes.workflow_prefix' => array('note' => 'NOTES_NOTE'),
            'notes.events' => $events,
            'notes.workflow_events' => $prefixed,
            'notes.note_types' => array_keys(notes_note_types()),
        );
    }
}