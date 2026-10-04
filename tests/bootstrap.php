<?php
declare(strict_types=1);

/**
 * Unit test bootstrap.
 *
 * Stands up the minimum of FA's runtime that the gateways touch, then loads
 * the module's own includes directly. Nothing here reaches a database.
 */

if (!defined('TB_PREF')) {
    define('TB_PREF', '0_');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Support/FaDbFake.php';

use Ksfraser\Notes\Tests\Support\FaDbFake;

/**
 * FA's procedural DB wrappers, delegated to the recording fake.
 *
 * These are declared as plain functions because the gateways call them
 * unqualified, exactly as they would inside FA. Each is guarded because
 * vendor/autoload.php already provides gettext's _() and a future dependency
 * could provide any of the rest.
 */
if (!function_exists('db_query')) {
    function db_query($sql, $error = null)
    {
        return FaDbFake::query($sql, $error);
    }
}

if (!function_exists('db_fetch_assoc')) {
    function db_fetch_assoc($result)
    {
        return FaDbFake::fetchAssoc($result);
    }
}

if (!function_exists('db_num_rows')) {
    function db_num_rows($result)
    {
        return FaDbFake::numRows($result);
    }
}

if (!function_exists('db_insert_id')) {
    function db_insert_id()
    {
        return FaDbFake::insertId();
    }
}

if (!function_exists('_')) {
    // FA's gettext wrapper. The views translate through it, so it has to exist
    // for them to be renderable outside FA.
    function _($text)
    {
        return $text;
    }
}

if (!function_exists('sql2date')) {
    function sql2date($date, $short = true)
    {
        return (string) $date;
    }
}

require_once __DIR__ . '/../includes/entity_types.inc';
require_once __DIR__ . '/../includes/ksf_notes_db.inc';
require_once __DIR__ . '/../includes/ksf_notes_link_db.inc';

/**
 * Load the module's hooks class against the stubbed base class, so the
 * responders and the installer can be exercised directly.
 */
require_once __DIR__ . '/Support/FaHooksStub.php';
require_once __DIR__ . '/../hooks.php';
