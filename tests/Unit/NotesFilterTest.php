<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\Notes\DTO\NotesFilter;

/**
 * Tests for the summary table's filter.
 *
 * The owner default is the point of these: a user lands on Notes and must see
 * their own notes, and must be able to widen that to anyone without the page
 * having to be told which mode to open in.
 *
 * @BABOK Related: FR-NT-001-005
 */
class NotesFilterTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = array();
    }

    protected function tearDown(): void
    {
        $_GET = array();
    }

    public function testOwnerDefaultsToTheLoggedInUser(): void
    {
        $filter = NotesFilter::fromRequest('kevin');

        $this->assertSame(NotesFilter::OWNER_SELF, $filter->ownerScope());
        $this->assertSame('kevin', $filter->currentUserId());
        $this->assertSame('kevin', $filter->effectiveOwner());
        $this->assertSame('kevin', $filter->toCriteria()['owner']);
    }

    public function testFromRequestStoresTheSuppliedUserId(): void
    {
        // Guards the bug where the user id was accepted but never stored,
        // which filtered on an empty owner and silently showed everything.
        $filter = NotesFilter::fromRequest('sue');

        $this->assertSame('sue', $filter->currentUserId());
        $this->assertSame('sue', $filter->toCriteria()['owner']);
    }

    public function testOwnerCanBeWidenedToEveryone(): void
    {
        $_GET['owner_scope'] = 'any';

        $filter = NotesFilter::fromRequest('kevin');

        $this->assertSame(NotesFilter::OWNER_ANY, $filter->ownerScope());
        $this->assertSame('', $filter->effectiveOwner());
        $this->assertArrayNotHasKey('owner', $filter->toCriteria());
    }

    public function testOwnerCanBeSetToAnyNamedUser(): void
    {
        $_GET['owner_scope'] = 'any';
        $_GET['owner'] = 'sue';

        $filter = NotesFilter::fromRequest('kevin');

        $this->assertSame('sue', $filter->effectiveOwner());
        $this->assertSame('sue', $filter->toCriteria()['owner']);
    }

    public function testANamedOwnerAloneSwitchesScopeOffSelf(): void
    {
        $_GET['owner'] = 'sue';

        $filter = NotesFilter::fromRequest('kevin');

        $this->assertSame('sue', $filter->effectiveOwner());
    }

    public function testUnknownOwnerScopeFallsBackToSelf(): void
    {
        $_GET['owner_scope'] = 'nonsense';

        $filter = NotesFilter::fromRequest('kevin');

        $this->assertSame(NotesFilter::OWNER_SELF, $filter->ownerScope());
    }

    public function testInactiveNotesAreExcludedUnlessAskedFor(): void
    {
        $filter = NotesFilter::fromRequest('kevin');
        $this->assertSame(0, $filter->toCriteria()['inactive']);

        $_GET['inactive'] = '1';
        $included = NotesFilter::fromRequest('kevin');
        $this->assertTrue($included->includeInactive());
        $this->assertArrayNotHasKey('inactive', $included->toCriteria());
    }

    public function testRecordAndKeywordFiltersAreCarriedThrough(): void
    {
        $_GET['keyword'] = '  overdue  ';
        $_GET['note_type'] = 'Call';
        $_GET['entity_type'] = 'customer';
        $_GET['entity_id'] = 'C001';

        $criteria = NotesFilter::fromRequest('kevin')->toCriteria();

        $this->assertSame('overdue', $criteria['keyword']);
        $this->assertSame('Call', $criteria['note_type']);
        $this->assertSame('customer', $criteria['entity_type']);
        $this->assertSame('C001', $criteria['entity_id']);
    }

    public function testEmptyCriteriaAreNotSentToTheGateway(): void
    {
        $criteria = NotesFilter::fromRequest('kevin')->toCriteria();

        $this->assertArrayNotHasKey('keyword', $criteria);
        $this->assertArrayNotHasKey('note_type', $criteria);
        $this->assertArrayNotHasKey('entity_type', $criteria);
    }

    public function testANonPositiveLimitFallsBackToTheDefault(): void
    {
        $_GET['limit'] = '-5';

        $this->assertSame(100, NotesFilter::fromRequest('kevin')->limit());
    }

    public function testOnlyGetIsReadSoTheSummaryTableCannotChangeData(): void
    {
        $_POST = array('note' => 'should be ignored');
        $_GET['note'] = 'also ignored';

        $filter = NotesFilter::fromRequest('kevin');

        $this->assertSame('', $filter->toCriteria()['note'] ?? '');
    }
}