<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Unit;

use Ksfraser\Notes\Tests\Support\FaDbFake;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for the 0_ksf_Notes_link x-link gateway.
 *
 * @BABOK Related: FR-NT-001-002
 */
class NotesLinkTest extends TestCase
{
    protected function setUp(): void
    {
        FaDbFake::reset();
    }

    // --- validation -----------------------------------------------------

    public function testPrepareAcceptsAWellFormedRow(): void
    {
        $link = notes_link_prepare(array(
            'note_id' => 3,
            'entity_type' => 'contact',
            'entity_id' => '7',
            'link_role' => 'with',
        ));

        $this->assertSame(3, $link['note_id']);
        $this->assertSame('contact', $link['entity_type']);
        $this->assertSame('7', $link['entity_id']);
        $this->assertSame('with', $link['link_role']);
    }

    public function testPrepareDefaultsRoleToTheFirstDeclaredForTheType(): void
    {
        $link = notes_link_prepare(array('note_id' => 1, 'entity_type' => 'attachment', 'entity_id' => '4'));

        $this->assertSame('document', $link['link_role']);
    }

    public function testPrepareRejectsAnUnknownEntityType(): void
    {
        // Without this gate a typo would become a link row nothing can resolve.
        $this->assertNull(notes_link_prepare(array(
            'note_id' => 1,
            'entity_type' => 'unicorn',
            'entity_id' => '4',
        )));
    }

    public function testPrepareRejectsARoleThatIsInvalidForTheType(): void
    {
        // 'document' belongs to attachments; a contact cannot be a document.
        $this->assertNull(notes_link_prepare(array(
            'note_id' => 1,
            'entity_type' => 'contact',
            'entity_id' => '4',
            'link_role' => 'document',
        )));
    }

    public function testPrepareRejectsAMissingNoteId(): void
    {
        $this->assertNull(notes_link_prepare(array('entity_type' => 'contact', 'entity_id' => '4')));
    }

    public function testPrepareRejectsAnEmptyEntityId(): void
    {
        $this->assertNull(notes_link_prepare(array(
            'note_id' => 1,
            'entity_type' => 'contact',
            'entity_id' => '   ',
        )));
    }

    public function testPrepareKeepsEntityIdZero(): void
    {
        // A falsy-but-real id must survive validation.
        $link = notes_link_prepare(array('note_id' => 1, 'entity_type' => 'contact', 'entity_id' => '0'));

        $this->assertNotNull($link);
        $this->assertSame('0', $link['entity_id']);
    }

    public function testPrepareCastsNoteIdToInteger(): void
    {
        $link = notes_link_prepare(array('note_id' => '12abc', 'entity_type' => 'contact', 'entity_id' => '4'));

        $this->assertSame(12, $link['note_id']);
    }

    // --- SQL building ---------------------------------------------------

    public function testInsertTargetsOwnedTableWithCompanyPrefix(): void
    {
        $sql = notes_link_sql_insert(notes_link_prepare(array(
            'note_id' => 3,
            'entity_type' => 'customer',
            'entity_id' => 'CUS-001',
        )));

        $this->assertStringStartsWith('INSERT INTO ' . TB_PREF . 'ksf_Notes_link ', $sql);
        $this->assertStringNotContainsString('fa_note_links', $sql);
    }

    public function testSelectFiltersByEveryCriterionGiven(): void
    {
        $sql = notes_link_sql_select(array(
            'note_id' => 3,
            'entity_type' => 'customer',
            'entity_id' => 'CUS-001',
        ));

        $this->assertStringContainsString('`note_id` = 3', $sql);
        $this->assertStringContainsString("`entity_type` = 'customer'", $sql);
        $this->assertStringContainsString("`entity_id` = 'CUS-001'", $sql);
        $this->assertStringContainsString('ORDER BY `entity_type`, `link_role`, `id`', $sql);
    }

    public function testSelectWithoutCriteriaReturnsEverythingOrdered(): void
    {
        $sql = notes_link_sql_select(array());

        $this->assertStringNotContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
    }

    public function testSelectEscapesEntityId(): void
    {
        $sql = notes_link_sql_select(array('entity_type' => 'customer', 'entity_id' => "x' OR '1'='1"));

        $this->assertStringContainsString("\\'", $sql);
    }

    // --- execution ------------------------------------------------------

    public function testAddRefusesAnInvalidRowWithoutTouchingTheDatabase(): void
    {
        $this->assertSame(0, notes_link_add(array('note_id' => 1, 'entity_type' => 'unicorn', 'entity_id' => '1')));
        $this->assertSame(array(), FaDbFake::$log);
    }

    public function testAddInsertsAndReturnsTheNewLinkId(): void
    {
        FaDbFake::$insertId = 88;

        $link_id = notes_link_add(array(
            'note_id' => 3,
            'entity_type' => 'contact',
            'entity_id' => '7',
            'link_role' => 'with',
        ));

        $this->assertSame(88, $link_id);
        $this->assertStringContainsString('VALUES (3, ', FaDbFake::lastQuery());
    }

    public function testAddIsIdempotentForAnExistingReference(): void
    {
        // Re-submitting the same picker value must not duplicate the row.
        FaDbFake::willReturnForNext(array(array('id' => '5')));

        $link_id = notes_link_add(array(
            'note_id' => 3,
            'entity_type' => 'contact',
            'entity_id' => '7',
            'link_role' => 'with',
        ));

        $this->assertSame(0, $link_id);
        $this->assertFalse(FaDbFake::saw('INSERT INTO'), 'no insert should be issued');
    }

    public function testGetByNoteReadsEveryLinkRow(): void
    {
        FaDbFake::willReturnForNext(array(
            array('id' => '1', 'entity_type' => 'contact', 'entity_id' => '7'),
            array('id' => '2', 'entity_type' => 'customer', 'entity_id' => 'CUS-1'),
        ));

        $links = notes_link_get_by_note(3);

        $this->assertCount(2, $links);
        $this->assertSame('customer', $links[1]['entity_type']);
        $this->assertStringContainsString('WHERE `note_id` = 3', FaDbFake::onlyQuery());
    }

    public function testGetByEntityFiltersBothTypeAndId(): void
    {
        notes_link_get_by_entity('customer', 'CUS-1');

        $sql = FaDbFake::onlyQuery();
        $this->assertStringContainsString("`entity_type` = 'customer'", $sql);
        $this->assertStringContainsString("`entity_id` = 'CUS-1'", $sql);
    }

    public function testDeleteWithoutRoleRemovesEveryRoleForThatReference(): void
    {
        notes_link_delete(3, 'contact', '7');

        $sql = FaDbFake::onlyQuery();
        $this->assertStringStartsWith('DELETE FROM ' . TB_PREF . 'ksf_Notes_link WHERE ', $sql);
        $this->assertStringContainsString('`note_id` = 3', $sql);
        $this->assertStringNotContainsString('`link_role`', $sql);
    }

    public function testDeleteWithRoleIsNarrower(): void
    {
        notes_link_delete(3, 'contact', '7', 'regarding');

        $this->assertStringContainsString("`link_role` = 'regarding'", FaDbFake::onlyQuery());
    }

    // --- resolution -----------------------------------------------------

    public function testResolveLooksTheLabelUpInTheOwningTable(): void
    {
        FaDbFake::willReturnForNext(array(array('label' => 'Jane Smith')));

        $resolved = notes_link_resolve(array(
            array('note_id' => '3', 'entity_type' => 'contact', 'entity_id' => '7', 'link_role' => 'with'),
        ));

        $this->assertSame('Jane Smith', $resolved[0]['label']);
        $this->assertTrue($resolved[0]['known']);
        $this->assertStringContainsString(
            'SELECT `name` AS label FROM ' . TB_PREF . 'crm_persons WHERE `id` = \'7\'',
            FaDbFake::onlyQuery()
        );
    }

    public function testResolveKeepsAReferenceWhoseOwningModuleIsMissing(): void
    {
        // A note can outlive the module that owned one of its references, so an
        // unresolvable link is reported, never dropped.
        $resolved = notes_link_resolve(array(
            array('note_id' => '3', 'entity_type' => 'contract', 'entity_id' => '9', 'link_role' => 'regarding'),
        ));

        $this->assertCount(1, $resolved);
        $this->assertNull($resolved[0]['label']);
        $this->assertFalse($resolved[0]['known']);
        $this->assertSame('9', $resolved[0]['entity_id']);
    }
}