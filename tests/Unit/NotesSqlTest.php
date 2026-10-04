<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Unit;

use Ksfraser\Notes\Tests\Support\FaDbFake;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for the 0_ksf_Notes gateway.
 *
 * These replace a previous suite that asserted only function_exists(), which
 * stayed green while every query it targeted a nonexistent table.
 *
 * @BABOK Related: FR-NT-001-001
 */
class NotesSqlTest extends TestCase
{
    protected function setUp(): void
    {
        FaDbFake::reset();
    }

    // --- prepare() defaults and whitelisting ---------------------------

    public function testPrepareAppliesTypeAndInactiveDefaults(): void
    {
        $prepared = notes_prepare(array('note' => 'Called back'));

        $this->assertSame('Called back', $prepared['note']);
        $this->assertSame('Comment', $prepared['note_type']);
        $this->assertSame(0, $prepared['inactive']);
    }

    public function testPrepareDropsColumnsThatAreNotWritable(): void
    {
        // id and created_at are assigned by the gateway, never by the caller.
        $prepared = notes_prepare(array(
            'note' => 'x',
            'id' => 99,
            'created_at' => '1999-01-01 00:00:00',
            'totally_made_up' => 'nope',
        ));

        $this->assertArrayNotHasKey('id', $prepared);
        $this->assertArrayNotHasKey('created_at', $prepared);
        $this->assertArrayNotHasKey('totally_made_up', $prepared);
    }

    public function testPrepareKeepsEveryWritableColumn(): void
    {
        $prepared = notes_prepare(array(
            'note' => 'body',
            'subject' => 'Q4 review',
            'note_type' => 'Call',
            'created_by' => 'admin',
            'owner' => 'gm',
            'group_id' => 2,
            'inactive' => 1,
        ));

        $this->assertSame('Q4 review', $prepared['subject']);
        $this->assertSame('Call', $prepared['note_type']);
        $this->assertSame('admin', $prepared['created_by']);
        $this->assertSame('gm', $prepared['owner']);
        $this->assertSame(2, $prepared['group_id']);
        $this->assertSame(1, $prepared['inactive']);
    }

    // --- escaping -------------------------------------------------------

    public function testEscapingNeutralisesQuotesInValues(): void
    {
        $sql = notes_sql_insert(notes_prepare(array('note' => "O'Brien \\ \"x\"")));

        $this->assertStringContainsString("\\'", $sql, 'single quote must be escaped');
        $this->assertStringContainsString('\\\\', $sql, 'backslash must be escaped');
        $this->assertStringNotContainsString("O'Brien", $sql);
    }

    public function testNullValuesAreWrittenAsNullNotEmptyString(): void
    {
        $sql = notes_sql_insert(notes_prepare(array('note' => 'x', 'owner' => null)));

        $this->assertStringContainsString('`owner`', $sql);
        $this->assertStringContainsString('NULL', $sql);
    }

    // --- INSERT ---------------------------------------------------------

    public function testInsertTargetsOwnedTableWithCompanyPrefix(): void
    {
        $sql = notes_sql_insert(notes_prepare(array('note' => 'body')));

        $this->assertStringStartsWith('INSERT INTO ' . TB_PREF . 'ksf_Notes (', $sql);
        $this->assertStringNotContainsString('fa_crm_notes', $sql, 'must not touch the retired table');
    }

    public function testInsertQuotesEachColumnExactlyOnce(): void
    {
        $sql = notes_sql_insert(notes_prepare(array('note' => 'body')));

        $this->assertStringContainsString('(`note`, `note_type`, `inactive`)', $sql);
        $this->assertStringNotContainsString('``', $sql);
    }

    public function testInsertDoesNotEmitEmptyColumnList(): void
    {
        $sql = notes_sql_insert(notes_prepare(array()));

        $this->assertStringNotContainsString('()', $sql);
        $this->assertStringContainsString('`note_type`', $sql, 'defaults still written');
    }

    // --- UPDATE ---------------------------------------------------------

    public function testUpdateSetsModifiedAtAndScopedToOneRow(): void
    {
        $sql = notes_sql_update(7, array('note' => 'Revised'));

        $this->assertStringStartsWith('UPDATE ' . TB_PREF . 'ksf_Notes SET ', $sql);
        $this->assertStringContainsString('`note` = ', $sql);
        $this->assertStringContainsString('`modified_at` = ', $sql);
        $this->assertStringEndsWith('WHERE `id` = 7', $sql);
    }

    public function testUpdateCannotChangeCreatedAt(): void
    {
        $sql = notes_sql_update(7, array('note' => 'Revised', 'created_at' => '1999-01-01 00:00:00'));

        $this->assertStringNotContainsString('`created_at` = ', $sql);
    }

    public function testUpdateReturnsEmptyStringWhenNothingWritableWasPassed(): void
    {
        $this->assertSame('', notes_sql_update(7, array('id' => 7, 'created_at' => 'x')));
    }

    public function testUpdateGatewayIssuesNoQueryWhenNothingChanged(): void
    {
        $this->assertFalse(notes_update(7, array('id' => 7)));
        $this->assertSame(array(), FaDbFake::$log, 'no SQL should be issued');
    }

    // --- SELECT ---------------------------------------------------------

    public function testPlainSelectDoesNotJoinTheLinkTable(): void
    {
        $sql = notes_sql_select(array());

        $this->assertStringContainsString('SELECT DISTINCT n.*', $sql);
        $this->assertStringNotContainsString('ksf_Notes_link', $sql);
    }

    public function testEntityFilteredSelectJoinsTheLinkTable(): void
    {
        $sql = notes_sql_select(array('entity_type' => 'customer', 'entity_id' => 'CUS-001'));

        $this->assertStringContainsString('INNER JOIN ' . TB_PREF . 'ksf_Notes_link', $sql);
        $this->assertStringContainsString("l.`entity_type` = 'customer'", $sql);
        $this->assertStringContainsString("l.`entity_id` = 'CUS-001'", $sql);
    }

    public function testEntityFilterRequiresBothTypeAndId(): void
    {
        // Half a filter must not silently match every row of the table.
        $sql = notes_sql_select(array('entity_type' => 'customer'));

        $this->assertStringNotContainsString('INNER JOIN', $sql);
    }

    public function testKeywordSearchCoversSubjectAndBody(): void
    {
        $sql = notes_sql_select(array('keyword' => 'renewal'));

        $this->assertStringContainsString("`subject` LIKE '%renewal%'", $sql);
        $this->assertStringContainsString("`note` LIKE '%renewal%'", $sql);
    }

    public function testKeywordIsEscapedNotInterpolated(): void
    {
        $sql = notes_sql_select(array('keyword' => "100%' OR 1=1 --"));

        $this->assertStringNotContainsString("OR 1=1 --'", $sql);
        $this->assertStringContainsString("\\'", $sql);
    }

    public function testLimitIsCastToInteger(): void
    {
        $sql = notes_sql_select(array('limit' => '5; DROP TABLE x'));

        $this->assertStringEndsWith('LIMIT 5', $sql);
        $this->assertStringNotContainsString('DROP TABLE', $sql);
    }

    public function testDefaultOrderIsNewestFirst(): void
    {
        $sql = notes_sql_select(array());

        $this->assertStringContainsString('ORDER BY n.`created_at` DESC', $sql);
    }

    public function testFiltersAreAllAppliedTogether(): void
    {
        $sql = notes_sql_select(array(
            'note_type' => 'Call',
            'created_by' => 'admin',
            'inactive' => 0,
        ));

        $this->assertStringContainsString("`note_type` = 'Call'", $sql);
        $this->assertStringContainsString("`created_by` = 'admin'", $sql);
        $this->assertStringContainsString('`inactive` = 0', $sql);
    }

    public function testCountSqlMatchesTheSelectPredicate(): void
    {
        $select = notes_sql_select(array('note_type' => 'Call'));
        $count = notes_sql_count(array('note_type' => 'Call'));

        // COUNT(*) must wrap the DISTINCT select, not replace it, otherwise a
        // note linked to five records would be counted five times.
        $this->assertStringStartsWith('SELECT COUNT(*) AS cnt FROM (', $count);
        $this->assertStringContainsString('SELECT DISTINCT n.*', $count);
        $this->assertStringContainsString("`note_type` = 'Call'", $count);
        $this->assertStringContainsString('ksf_Notes n', $select);
    }

    // --- execution path -------------------------------------------------

    public function testWriteRefusesAnEmptyBodyWithoutTouchingTheDatabase(): void
    {
        $this->assertSame(0, notes_write(array('note' => '   ')));
        $this->assertSame(array(), FaDbFake::$log);
    }

    public function testWriteInsertsAndReturnsTheNewId(): void
    {
        FaDbFake::$insertId = 42;

        $note_id = notes_write(array('note' => 'Called the customer'));

        $this->assertSame(42, $note_id);
        $this->assertStringStartsWith('INSERT INTO ' . TB_PREF . 'ksf_Notes', FaDbFake::onlyQuery());
        $this->assertStringContainsString('`created_at`', FaDbFake::onlyQuery());
    }

    public function testWriteDefaultsCreatedByToTheLoggedInUser(): void
    {
        $GLOBALS['user'] = array('id' => 'kevin');

        try {
            FaDbFake::$insertId = 1;
            notes_write(array('note' => 'body'));
            $this->assertStringContainsString("'kevin'", FaDbFake::onlyQuery());
        } finally {
            unset($GLOBALS['user']);
        }
    }

    public function testDeleteRemovesLinksFirstThenTheNote(): void
    {
        notes_delete(11);

        // Links first: the table must never be left holding orphans even if
        // the note delete itself goes on to fail.
        $this->assertCount(2, FaDbFake::$log);
        $this->assertSame(
            'DELETE FROM ' . TB_PREF . 'ksf_Notes_link WHERE `note_id` = 11',
            FaDbFake::$log[0]
        );
        $this->assertSame(
            'DELETE FROM ' . TB_PREF . 'ksf_Notes WHERE `id` = 11',
            FaDbFake::$log[1]
        );
    }

    public function testGetAllMapsFetchedRowsToNotes(): void
    {
        FaDbFake::willReturnForNext(array(
            array('id' => '1', 'note' => 'first'),
            array('id' => '2', 'note' => 'second'),
        ));

        $notes = notes_get_all(array('limit' => 2));

        $this->assertCount(2, $notes);
        $this->assertSame('first', $notes[0]['note']);
        $this->assertSame('second', $notes[1]['note']);
    }

    public function testGetReturnsNullWhenNothingMatches(): void
    {
        $this->assertNull(notes_get(5));
    }

    public function testGetReturnsTheBareRowFromOneScopedQuery(): void
    {
        FaDbFake::willReturnForNext(array(array('id' => '5', 'note' => 'body')));

        $note = notes_get(5);

        // Single responsibility: notes_get() does not fan out to the link
        // table. GET_NOTE composes the two so the UI needs one call.
        $this->assertSame('5', $note['id']);
        $this->assertSame('body', $note['note']);
        $this->assertCount(1, FaDbFake::$log);
        $this->assertSame(
            'SELECT * FROM ' . TB_PREF . 'ksf_Notes WHERE `id` = 5',
            FaDbFake::onlyQuery()
        );
    }

    public function testGetEscapesTheIdBeforeItReachesSql(): void
    {
        notes_get('5 OR 1=1');

        $this->assertSame(
            'SELECT * FROM ' . TB_PREF . 'ksf_Notes WHERE `id` = 5',
            FaDbFake::onlyQuery(),
            'the id is cast to int, not merely quoted'
        );
    }

    public function testOwnersComeFromADistinctProjectionOnTheirOwnQuery(): void
    {
        FaDbFake::reset();
        FaDbFake::willReturnForNext(array(
            array('owner' => 'kevin'),
            array('owner' => 'sue'),
        ));

        $this->assertSame(array('kevin', 'sue'), notes_sql_owners());

        $this->assertSame(
            'SELECT DISTINCT `owner` FROM ' . TB_PREF . 'ksf_Notes'
            . " WHERE `owner` IS NOT NULL AND `owner` <> '' ORDER BY `owner`",
            FaDbFake::onlyQuery(),
            'owners must not be gathered by scanning whole note rows'
        );
    }

    public function testOwnersAreEmptyWhenTheQueryFails(): void
    {
        FaDbFake::reset();
        FaDbFake::$querySucceeds = false;

        $this->assertSame(array(), notes_sql_owners());
    }
}