<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ksfraser\Notes\Tests\Support\FaDbFake;
use ksfraser\FrontAccounting\Notes\Service\NoteCreationService;
use ksfraser\FrontAccounting\Notes\Service\NoteQueryService;

/**
 * Tests for the services the responders delegate to.
 *
 * The responders themselves are thin adapters; this is where the behaviour
 * they moved out of hooks.php now has to be pinned.
 *
 * @BABOK Related: FR-NT-001-001, FR-NT-001-003
 */
class NoteServiceTest extends TestCase
{
    protected function setUp(): void
    {
        FaDbFake::reset();
    }

    public function testCreateWritesTheNoteAndReportsItsId(): void
    {
        FaDbFake::$insertId = 12;

        $result = (new NoteCreationService())->create(array('note' => 'Overdue invoice'));

        $this->assertTrue($result['ok']);
        $this->assertSame(12, $result['note_id']);
        $this->assertSame(0, $result['links_added']);
    }

    public function testCreateRefusesANoteWithNoBody(): void
    {
        $result = (new NoteCreationService())->create(array('subject' => 'Only a title'));

        $this->assertFalse($result['ok']);
        $this->assertSame('note_body_required', $result['error']);
        $this->assertSame(array(), FaDbFake::$log, 'nothing should reach the database');
    }

    public function testCreateAcceptsTheCompactLinkForm(): void
    {
        FaDbFake::$insertId = 3;

        $result = (new NoteCreationService())->create(
            array('note' => 'body'),
            array('links' => array(
                array('customer', 'C001', 'regarding'),
                array('contact', '7'),
            ))
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['links_added']);

        // notes_link_add() de-duplicates before inserting, so the trace is
        // note INSERT, then (lookup + INSERT) per link in the order given.
        $this->assertStringContainsString('INSERT INTO ' . TB_PREF . 'ksf_Notes ', FaDbFake::$log[0]);
        $this->assertStringContainsString("'customer'", FaDbFake::$log[2]);
        $this->assertStringContainsString("'contact'", FaDbFake::$log[4]);
    }

    public function testCreateAcceptsTheAssociativeLinkForm(): void
    {
        FaDbFake::$insertId = 4;

        $result = (new NoteCreationService())->create(
            array('note' => 'body'),
            array('links' => array(
                array('entity_type' => 'customer', 'entity_id' => 'C001', 'link_role' => 'with'),
            ))
        );

        $this->assertSame(1, $result['links_added']);
    }

    public function testCreateIgnoresMalformedLinksRatherThanFailing(): void
    {
        FaDbFake::$insertId = 5;

        $result = (new NoteCreationService())->create(
            array('note' => 'body'),
            array('links' => array('not-an-array', array('customer', 'C001')))
        );

        $this->assertTrue($result['ok'], 'a bad link must not sink the note');
        $this->assertSame(1, $result['links_added']);
    }

    public function testCreateDefaultsCreatedByToTheLoggedInUser(): void
    {
        FaDbFake::$insertId = 6;
        $_SESSION['wa_current_user'] = (object) array('loginname' => 'kevin');

        (new NoteCreationService())->create(array('note' => 'body'));

        $this->assertStringContainsString("'kevin'", FaDbFake::onlyQuery());

        unset($_SESSION['wa_current_user']);
    }

    public function testFindRequiresANoteId(): void
    {
        $result = (new NoteQueryService())->find(null);

        $this->assertFalse($result['ok']);
        $this->assertSame('note_id_required', $result['error']);
    }

    public function testFindReportsAMissingNote(): void
    {
        FaDbFake::willReturnForNext(array());

        $result = (new NoteQueryService())->find(999);

        $this->assertFalse($result['ok']);
        $this->assertSame('note_not_found', $result['error']);
    }

    public function testFindReturnsTheNoteWithItsLinksResolved(): void
    {
        FaDbFake::willReturnForNext(
            array(array('id' => 5, 'note' => 'body', 'subject' => 'Chase')),
            array() // link lookup
        );

        $result = (new NoteQueryService())->find(5);

        $this->assertTrue($result['ok']);
        $this->assertSame(5, $result['note']['id']);
        $this->assertArrayHasKey('links', $result['note']);
    }

    public function testSearchDropsEmptyCriteriaSoTheyDoNotBecomeFilters(): void
    {
        FaDbFake::willReturnForNext(array());

        (new NoteQueryService())->search(array('keyword' => '', 'note_type' => 'Call'));

        $sql = FaDbFake::onlyQuery();
        $this->assertStringNotContainsString("`note` LIKE", $sql);
        $this->assertStringContainsString("`note_type`", $sql);
    }

    public function testSearchCountsWhatItReturned(): void
    {
        FaDbFake::willReturnForNext(array(
            array('id' => 1),
            array('id' => 2),
        ));

        $result = (new NoteQueryService())->search(array('keyword' => 'x'));

        $this->assertSame(2, $result['count']);
        $this->assertCount(2, $result['notes']);
    }

    public function testForEntityRequiresBothHalvesOfTheReference(): void
    {
        $service = new NoteQueryService();

        $this->assertSame('entity_required', $service->forEntity(array('entity_type' => 'customer'))['error']);
        $this->assertSame('entity_required', $service->forEntity(array('entity_id' => 'C001'))['error']);
    }

    public function testForEntityJoinsTheLinkTable(): void
    {
        FaDbFake::willReturnForNext(array(array('id' => 9)));

        $result = (new NoteQueryService())->forEntity(array(
            'entity_type' => 'customer',
            'entity_id' => 'C001',
        ));

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString('ksf_Notes_link', FaDbFake::onlyQuery());
    }

    public function testOptsWinOverThePayload(): void
    {
        FaDbFake::willReturnForNext(array());

        (new NoteQueryService())->search(
            array('note_type' => 'Comment'),
            array('note_type' => 'Call')
        );

        $this->assertStringContainsString('Call', FaDbFake::onlyQuery());
        $this->assertStringNotContainsString('Comment', FaDbFake::onlyQuery());
    }
}