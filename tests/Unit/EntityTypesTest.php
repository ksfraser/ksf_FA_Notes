<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Unit;

use ksfraser\FrontAccounting\Notes\Repository\EntityRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the entity-type registry.
 *
 * The registry is the contract Notes shares with CRM, HRM and Project
 * Management: it says which table backs each kind of thing a note can point at.
 * Getting an entry wrong silently breaks label resolution for that one type,
 * so the expected values are pinned here.
 *
 * @BABOK Related: FR-NT-001-002
 */
class EntityTypesTest extends TestCase
{
    /**
     * @var EntityRegistry
     */
    private $registry;

    protected function setUp(): void
    {
        $this->registry = new EntityRegistry();
    }

    public function testEveryKindOfRecordTheUserAskedForIsRegistered(): void
    {
        $types = $this->registry->all();

        foreach (array('customer', 'contact', 'meeting', 'opportunity', 'contract',
                     'project', 'task', 'calendar', 'attachment') as $key) {
            $this->assertArrayHasKey($key, $types, "entity type $key must be registered");
        }
    }

    public function testContactResolvesToTheNativePersonTable(): void
    {
        // Contacts are native FA persons. ksf_crm_contacts is the legacy flat
        // duplicate and must never be used as the resolution target.
        $def = $this->registry->get('contact');

        $this->assertSame('crm_persons', $def['table']);
        $this->assertSame('id', $def['pk']);
        $this->assertNotSame('ksf_crm_contacts', $def['table']);
    }

    public function testCustomerResolvesToTheDebtorMaster(): void
    {
        $def = $this->registry->get('customer');

        $this->assertSame('debtors_master', $def['table']);
        $this->assertSame('debtor_no', $def['pk']);
        $this->assertSame('name', $def['label']);
    }

    public function testAttachmentResolvesToTheNativeAttachmentTable(): void
    {
        $def = $this->registry->get('attachment');

        $this->assertSame('attachments', $def['table']);
        $this->assertSame('id', $def['pk']);
    }

    public function testContractPointsAtTheTableTheNewModuleWillOwn(): void
    {
        $def = $this->registry->get('contract');

        $this->assertSame('ksf_contract', $def['table']);
        $this->assertFalse($def['picker'], 'the picker stays disabled until ksf_FA_Contract ships');
    }

    public function testEveryEntryDeclaresTheThreeFieldsResolutionNeeds(): void
    {
        foreach ($this->registry->all() as $key => $def) {
            $this->assertArrayHasKey('table', $def, "$key needs a table");
            $this->assertArrayHasKey('pk', $def, "$key needs a primary key column");
            $this->assertArrayHasKey('label', $def, "$key needs a label column");
            $this->assertNotSame('', $def['table'], "$key has no table");
            $this->assertNotSame('', $def['pk'], "$key has no primary key");
            $this->assertNotSame('', $def['label'], "$key has no label column");
        }
    }

    public function testEveryTableNameLivesInAnOwnedOrNativeNamespace(): void
    {
        // Guards against a typo inventing a table nobody owns.
        foreach ($this->registry->all() as $key => $def) {
            $this->assertMatchesRegularExpression(
                '/^[a-z_][a-z0-9_]*$/',
                $def['table'],
                "$key has a malformed table name"
            );
        }
    }

    public function testUnknownTypeResolvesToNull(): void
    {
        $this->assertNull($this->registry->get('unicorn'));
        $this->assertNull($this->registry->get(''));
    }

    public function testAContactAndACustomerCanBothAppearOnOneNote(): void
    {
        // The whole point of the x-link: several different kinds of record per
        // note, which a single foreign key could not express.
        $this->assertTrue($this->registry->get('contact')['multiple']);
        $this->assertTrue($this->registry->get('customer')['multiple']);
        $this->assertTrue($this->registry->get('attachment')['multiple']);
    }

    public function testRolesAreScopedToTheTypeTheyBelongTo(): void
    {
        $this->assertArrayHasKey('with', $this->registry->linkRolesFor('contact'));
        $this->assertArrayNotHasKey('with', $this->registry->linkRolesFor('opportunity'));
        $this->assertArrayHasKey('document', $this->registry->linkRolesFor('attachment'));
        $this->assertArrayNotHasKey('document', $this->registry->linkRolesFor('contact'));
    }

    public function testATypeWithNoDeclaredRolesFallsBackToTheDefault(): void
    {
        $this->assertSame(array(), $this->registry->linkRolesFor('unicorn'));
        $this->assertSame(array('reference' => 'Reference'), $this->registry->defaultLinkRoles());
    }

    public function testEveryRoleKeyIsNonEmptyAndHumanReadable(): void
    {
        foreach ($this->registry->all() as $type => $def) {
            $roles = $this->registry->linkRolesFor($type);
            $this->assertNotSame(array(), $roles, "$type declares no roles");
            foreach ($roles as $key => $label) {
                $this->assertNotSame('', trim((string) $key), "$type has an empty role key");
                $this->assertNotSame('', trim((string) $label), "role $key has no label");
            }
        }
    }
}
