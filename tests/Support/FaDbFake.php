<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Support;

/**
 * Recording stand-in for FA's procedural database layer.
 *
 * The gateways are written so that every statement is built by a pure
 * *_sql_* function and executed by a thin db_* wrapper. That split lets these
 * tests assert on the SQL text itself and on the rows the gateway hands back,
 * without a MariaDB server.
 *
 * Only the db_* functions the gateways actually call are implemented; if a
 * gateway grows a new call the test fails loudly with "undefined" rather than
 * silently passing.
 */
class FaDbFake
{
    /** @var string[] Every statement passed to db_query(), in order. */
    public static $log = array();

    /** @var array[] Rows returned by successive db_fetch_assoc() calls, per handle. */
    private static $rows = array();

    /** @var int Handle counter. */
    private static $handle = 0;

    /** @var int Value returned by db_insert_id(). */
    public static $insertId = 0;

    /** @var bool Value returned by db_query() when it should simulate failure. */
    public static $querySucceeds = true;

    /** @var array[] Queue of row-sets, one per upcoming db_query() call. */
    private static $pendingRows = array();

    public static function reset(): void
    {
        self::$log = array();
        self::$rows = array();
        self::$handle = 0;
        self::$insertId = 0;
        self::$querySucceeds = true;
        self::$pendingRows = array();
    }

    /**
     * Record a statement and register the rows its handle should yield.
     *
     * @param string $sql        Statement text
     * @param string|null $error Ignored; present for signature compatibility
     * @return int|false Handle, or false when the statement is made to fail,
     *                      which is what db_query() returns in that case
     */
    public static function query($sql, $error = null)
    {
        self::$log[] = $sql;

        if (!self::$querySucceeds) {
            return false;
        }

        $handle = ++self::$handle;
        self::$rows[$handle] = self::$pendingRows === array()
            ? array()
            : array_shift(self::$pendingRows);

        return $handle;
    }

    /**
     * Queue rows for the next statement the module issues.
     *
     * Tests cannot know a handle in advance, because the module issues its own
     * db_query() calls; this hands the rows to whichever call comes next. Calls
     * queue in order, so a multi-query gateway can be described exactly: push
     * an empty set for a query whose result is irrelevant.
     *
     * @param array[] $rows Associative rows
     * @return void
     */
    public static function willReturnForNext(array $rows = array()): void
    {
        self::$pendingRows[] = $rows;
    }

    /**
     * Queue the rows a handle should return, in order.
     *
     * @param int    $handle Handle from query()
     * @param array[] $rows  Associative rows
     * @return void
     */
    public static function willReturn($handle, array $rows): void
    {
        self::$rows[$handle] = array_values($rows);
    }

    /**
     * @param int $handle Handle from query()
     * @return array|false Next row, or false when exhausted
     */
    public static function fetchAssoc($handle)
    {
        if (!isset(self::$rows[$handle]) || self::$rows[$handle] === array()) {
            return false;
        }

        return array_shift(self::$rows[$handle]);
    }

    /**
     * @param int $handle Handle from query()
     * @return int
     */
    public static function numRows($handle): int
    {
        return isset(self::$rows[$handle]) ? count(self::$rows[$handle]) : 0;
    }

    /**
     * @return int
     */
    public static function insertId(): int
    {
        return self::$insertId;
    }

    /**
     * The single statement passed to db_query().
     *
     * @return string
     */
    public static function onlyQuery(): string
    {
        if (count(self::$log) !== 1) {
            throw new \RuntimeException(
                'Expected exactly 1 query, got ' . count(self::$log) . ': ' . implode(' | ', self::$log)
            );
        }

        return self::$log[0];
    }

    /**
     * The last statement passed to db_query().
     *
     * @return string
     */
    public static function lastQuery(): string
    {
        if (self::$log === array()) {
            throw new \RuntimeException('No queries were issued');
        }

        return self::$log[count(self::$log) - 1];
    }

    /**
     * @param string $needle Substring to look for
     * @return bool
     */
    public static function saw(string $needle): bool
    {
        foreach (self::$log as $sql) {
            if (strpos($sql, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}