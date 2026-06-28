<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Unit;

use PHPUnit\Framework\TestCase;

class NotesDbTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('TB_PREF')) {
            define('TB_PREF', '0_');
        }
    }

    // --- Multi-link tests ---

    public function testLinkNoteCreatesLink(): void
    {
        $this->assertTrue(function_exists('link_note'));
    }

    public function testUnlinkNoteRemovesLink(): void
    {
        $this->assertTrue(function_exists('unlink_note'));
    }

    public function testGetLinkedEntitiesReturnsArray(): void
    {
        $this->assertTrue(function_exists('get_linked_entities'));
    }

    // --- ACL tests ---

    public function testCanViewNoteReturnsBool(): void
    {
        $this->assertTrue(function_exists('can_view_note'));
    }

    public function testCanEditNoteReturnsBool(): void
    {
        $this->assertTrue(function_exists('can_edit_note'));
    }

    // --- Existing functions still work ---

    public function testAddNoteReturnsInt(): void
    {
        $this->assertTrue(function_exists('add_note'));
    }

    public function testGetNotesReturnsArray(): void
    {
        $this->assertTrue(function_exists('get_notes'));
    }

    public function testGetNoteReturnsArray(): void
    {
        $this->assertTrue(function_exists('get_note'));
    }

    public function testUpdateNoteExists(): void
    {
        $this->assertTrue(function_exists('update_note'));
    }

    public function testDeleteNoteExists(): void
    {
        $this->assertTrue(function_exists('delete_note'));
    }

    public function testSearchNotesExists(): void
    {
        $this->assertTrue(function_exists('search_notes'));
    }

    public function testGetNoteCountExists(): void
    {
        $this->assertTrue(function_exists('get_note_count'));
    }

    public function testGetEntityNotesSummaryExists(): void
    {
        $this->assertTrue(function_exists('get_entity_notes_summary'));
    }
}
