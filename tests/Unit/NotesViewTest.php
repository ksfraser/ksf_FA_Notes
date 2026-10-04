<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\Notes\DTO\NotesFilter;
use ksfraser\FrontAccounting\Notes\Entity\EntityOptionsProvider;
use ksfraser\FrontAccounting\Notes\View\NoteFilterBar;
use ksfraser\FrontAccounting\Notes\View\NoteFormView;
use ksfraser\FrontAccounting\Notes\View\NoteSummaryTableView;

/**
 * Tests for the notes summary table, filter bar and data-entry form.
 *
 * These pin the behaviour the user asked for: the table opens on your own
 * notes, you can widen it to anyone, you can filter by the record a note is
 * about, and every record dropdown is filled from what the owning module
 * supplied rather than from a table Notes queries itself.
 *
 * @BABOK Related: FR-NT-001-004, FR-NT-001-005
 */
class NotesViewTest extends TestCase
{
    /**
     * @return EntityOptionsProvider
     */
    private function provider(): EntityOptionsProvider
    {
        return new EntityOptionsProvider(
            array(
                'customer' => array(
                    'title' => 'Customer',
                    'picker' => true,
                    'multiple' => true,
                ),
                'contact' => array(
                    'title' => 'Contact',
                    'picker' => true,
                    'multiple' => true,
                ),
                'contract' => array(
                    'title' => 'Contract',
                    'picker' => false,
                    'multiple' => true,
                ),
            ),
            array(
                'customer' => array('C001' => 'Acme Pty Ltd', 'C002' => 'Beta & Co'),
                'contact' => array('7' => 'Jane Smith'),
                // Deliberately supplied: picker=false must still win over data.
                'contract' => array('K1' => 'Contract One'),
            )
        );
    }

    /**
     * @param array $notes Rows to render
     * @param array $get   Request query
     * @return string
     */
    private function summary(array $notes, array $get = array()): string
    {
        $_GET = $get;

        $view = new NoteSummaryTableView(
            $this->provider(),
            $notes,
            NotesFilter::fromRequest('kevin'),
            array('Comment' => 'Comment', 'Call' => 'Call'),
            'kevin',
            'Kevin Smith',
            array('kevin' => 'Kevin Smith', 'sue' => 'Sue Jones')
        );

        ob_start();
        $view->render();

        return ob_get_clean();
    }

    protected function tearDown(): void
    {
        $_GET = array();
    }

    public function testTheTableOpensOnTheLoggedInUsersOwnNotes(): void
    {
        $html = $this->summary(array(), array());

        $this->assertStringContainsString('owned by you', $html);
    }

    public function testTheOwnerFilterDefaultsToMine(): void
    {
        $html = $this->summary(array(), array());

        $this->assertStringContainsString('name="owner_scope"', $html);
        $this->assertStringContainsString('<option value="self" selected>My notes</option>', $html);
    }

    public function testTheOwnerFilterOffersEveryoneAndNamedUsers(): void
    {
        $html = $this->summary(array(), array());

        $this->assertStringContainsString('<option value="any">Everyone</option>', $html);
        $this->assertStringContainsString('Sue Jones', $html);
    }

    public function testWideningToEveryoneChangesTheHeading(): void
    {
        $html = $this->summary(array(), array('owner_scope' => 'any'));

        $this->assertStringContainsString('for everyone', $html);
        $this->assertStringNotContainsString('owned by you', $html);
    }

    public function testFilteringByOneUsersNotesNamesThem(): void
    {
        $html = $this->summary(array(), array('owner' => 'sue'));

        $this->assertStringContainsString('for Sue Jones', $html);
        $this->assertStringContainsString('<option value="sue" selected>Sue Jones</option>', $html);
    }

    public function testARowShowsItsSubjectTypeOwnerAndAttachment(): void
    {
        $html = $this->summary(array(
            array(
                'id' => 5,
                'subject' => 'Chase invoice',
                'note' => 'Overdue 30 days',
                'note_type' => 'Comment',
                'owner' => 'kevin',
                'created_at' => '2026-10-04 09:15:00',
                'links' => array(
                    array(
                        'entity_type' => 'customer',
                        'entity_id' => 'C001',
                        'link_role' => 'regarding',
                        'known' => true,
                        'label' => 'Acme Pty Ltd',
                    ),
                ),
            ),
        ));

        $this->assertStringContainsString('Chase invoice', $html);
        $this->assertStringContainsString('Overdue 30 days', $html);
        $this->assertStringContainsString('Acme Pty Ltd', $html);
        $this->assertStringContainsString('regarding', $html);
        $this->assertStringContainsString('2026-10-04 09:15:00', $html);
    }

    public function testANoteAttachedToNothingSaysSo(): void
    {
        $html = $this->summary(array(
            array('id' => 6, 'subject' => 'Standalone', 'note' => '', 'note_type' => 'Call', 'owner' => 'kevin'),
        ));

        $this->assertStringContainsString('(not attached)', $html);
    }

    public function testAMissingTargetIsReportedRatherThanHidden(): void
    {
        $html = $this->summary(array(
            array(
                'id' => 7,
                'subject' => 'Deleted customer',
                'note' => '',
                'note_type' => 'Comment',
                'owner' => 'kevin',
                'links' => array(
                    array('entity_type' => 'customer', 'entity_id' => 'GONE', 'link_role' => '', 'known' => false),
                ),
            ),
        ));

        $this->assertStringContainsString('(not found)', $html);
        $this->assertStringContainsString('GONE', $html);
    }

    public function testAnEmptySubjectStillGetsARow(): void
    {
        $html = $this->summary(array(
            array('id' => 8, 'subject' => '', 'note' => 'body only', 'note_type' => 'Comment', 'owner' => 'kevin'),
        ));

        $this->assertStringContainsString('(no subject)', $html);
    }

    public function testRenderedValuesAreEscaped(): void
    {
        $html = $this->summary(array(
            array(
                'id' => 9,
                'subject' => '<script>alert(1)</script>',
                'note' => '',
                'note_type' => 'Comment',
                'owner' => 'kevin',
            ),
        ));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testTheRecordFilterOffersOnlyTypesThatHaveRecords(): void
    {
        $html = $this->summary(array());

        $this->assertStringContainsString('name="entity_type"', $html);
        $this->assertStringContainsString('<option value="customer">Customer</option>', $html);
        $this->assertStringNotContainsString('<option value="contract">Contract</option>', $html);
    }

    public function testChoosingARecordTypePopulatesTheRecordDropdown(): void
    {
        $html = $this->summary(array(), array('entity_type' => 'customer', 'entity_id' => 'C002'));

        $this->assertStringContainsString('Beta &amp; Co', $html);
        $this->assertStringContainsString('<option value="C002" selected>Beta &amp; Co</option>', $html);
        $this->assertStringNotContainsString('Jane Smith', $html);
    }

    public function testTheFormOffersAPickerPerPickableType(): void
    {
        $html = (new NoteFormView($this->provider(), array(), array('Comment' => 'Comment')))->render();

        $this->assertStringContainsString('name="link_customer"', $html);
        $this->assertStringContainsString('name="link_contact"', $html);
        $this->assertStringNotContainsString('name="link_contract"', $html);
    }

    public function testTheFormPreSelectsExistingAttachments(): void
    {
        $html = (new NoteFormView($this->provider(), array(), array('Comment' => 'Comment')))->render(
            array('id' => 5),
            array(
                array('entity_type' => 'customer', 'entity_id' => 'C001', 'link_role' => 'regarding', 'known' => true, 'label' => 'Acme Pty Ltd'),
            )
        );

        $this->assertStringContainsString('<option value="C001" selected>Acme Pty Ltd</option>', $html);
    }

    public function testTheFormKnowsWhetherItIsCreatingOrUpdating(): void
    {
        $create = (new NoteFormView($this->provider(), array(), array('Comment' => 'Comment')))->render();
        $this->assertStringContainsString('value="create"', $create);
        $this->assertStringContainsString('Create Note', $create);
        $this->assertStringNotContainsString('Delete Note', $create);

        $update = (new NoteFormView($this->provider(), array(), array('Comment' => 'Comment')))
            ->render(array('id' => 5, 'note' => 'x'));
        $this->assertStringContainsString('value="update"', $update);
        $this->assertStringContainsString('Update Note', $update);
        $this->assertStringContainsString('Delete Note', $update);
    }

    public function testTheFormPopulatesTheNoteFields(): void
    {
        $html = (new NoteFormView($this->provider(), array(), array('Comment' => 'Comment')))->render(
            array('id' => 5, 'subject' => 'Chase invoice', 'note' => 'Overdue', 'owner' => 'kevin')
        );

        $this->assertStringContainsString('Chase invoice', $html);
        $this->assertStringContainsString('Overdue', $html);
        $this->assertStringContainsString('<textarea', $html);
    }

    public function testCurrentLinksAreListedWithARemoveControl(): void
    {
        $html = (new NoteFormView($this->provider()))->currentLinks(
            array(
                array('entity_type' => 'customer', 'entity_id' => 'C001', 'link_role' => 'regarding', 'known' => true, 'label' => 'Acme Pty Ltd'),
            ),
            5
        );

        $this->assertStringContainsString('Acme Pty Ltd', $html);
        $this->assertStringContainsString('Remove', $html);
        $this->assertStringContainsString('action=unlink', $html);
    }

    public function testAnEmptyLinkListSaysTheNoteIsUnattached(): void
    {
        $html = (new NoteFormView($this->provider()))->currentLinks(array(), 5);

        $this->assertStringContainsString('not attached to anything', $html);
    }

    public function testTheFilterBarCanBeRenderedOnItsOwn(): void
    {
        $html = (new NoteFilterBar($this->provider()))
            ->render(NotesFilter::fromRequest('kevin'), array('Comment' => 'Comment'));

        $this->assertStringContainsString('name="keyword"', $html);
        $this->assertStringContainsString('name="note_type"', $html);
        $this->assertStringContainsString('name="owner_scope"', $html);
    }
}