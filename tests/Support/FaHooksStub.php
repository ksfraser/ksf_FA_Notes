<?php
declare(strict_types=1);

/**
 * Minimal stand-in for FA's base `hooks` class.
 *
 * FA's real includes/hooks.inc cannot be loaded outside the application, so the
 * test suite declares just the surface hooks_ksf_FA_Notes relies on. Every call
 * is recorded so tests can assert on what the module asked FA to do.
 *
 * @BABOK Related: UT-NT-001-001-001
 */
class hooks
{
    /** @var array[] update_databases() calls, each with company/updates/check_only. */
    public static $updateDatabasesCalls = array();

    /** @var array[] add_module_app() calls. */
    public static $addedApps = array();

    /**
     * FA's base hooks class has a constructor; the module's calls parent::__construct()
     * when registering its workflow types, so the stub needs one.
     */
    public function __construct()
    {
    }

    public static function reset(): void
    {
        self::$updateDatabasesCalls = array();
        self::$addedApps = array();
    }

    /**
     * @param int   $comp       Company number
     * @param array $updates    File => [table, field, property]
     * @param bool  $check_only Check only
     * @return bool
     */
    public function update_databases($comp, $updates, $check_only = false)
    {
        self::$updateDatabasesCalls[] = array(
            'company' => $comp,
            'updates' => $updates,
            'check_only' => $check_only,
        );

        return true;
    }

    /**
     * @param string $id     Menu id
     * @param string $title  Menu label
     * @param string $page   Page path
     * @param int    $access Security area
     * @return void
     */
    public function add_module_app($id, $title, $page, $access = 0)
    {
        self::$addedApps[] = array(
            'id' => $id,
            'title' => $title,
            'page' => $page,
            'access' => $access,
        );
    }

    /**
     * FA calls install_extension() during registration; the base implementation
     * is a no-op, which is why schema work must live in activate_extension().
     *
     * @param int $company Company number
     * @return void
     */
    public function install_extension($company)
    {
    }
}