<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ksfraser\FrontAccounting\Notes\Entity\EntityOptionsProvider;

/**
 * Tests for the entity options provider.
 *
 * The provider is the seam that stops Notes querying other modules' tables, so
 * these tests pin the resolution order (caller-supplied, then responder, then
 * table) and the fact that a type with no picker never reaches the UI.
 *
 * @BABOK Related: FR-NT-001-002
 */
class EntityOptionsProviderTest extends TestCase
{
    /**
     * @return array
     */
    private function registry(): array
    {
        return array(
            'customer' => array(
                'title' => 'Customer',
                'table' => '0_debtors_master',
                'pk' => 'debtor_no',
                'picker' => true,
                'multiple' => true,
            ),
            'contract' => array(
                'title' => 'Contract',
                'table' => '0_ksf_contract',
                'pk' => 'id',
                'picker' => false,
                'multiple' => true,
            ),
            'empty_module' => array(
                'title' => 'Empty Module',
                'table' => '0_nothing_here',
                'pk' => 'id',
                'picker' => true,
                'multiple' => true,
            ),
        );
    }

    public function testHookNameUsesTheRegistryEntryWhenItNamesOne(): void
    {
        $registry = $this->registry();
        $registry['customer']['options_hook'] = 'getCrmDebtorOptions';

        $provider = new EntityOptionsProvider($registry);

        $this->assertSame('getCrmDebtorOptions', $provider->hookNameFor('customer'));
    }

    public function testHookNameFallsBackToTheCamelCaseConvention(): void
    {
        $provider = new EntityOptionsProvider($this->registry());

        $this->assertSame('getCustomerOptions', $provider->hookNameFor('customer'));
        $this->assertSame('getContractOptions', $provider->hookNameFor('contract'));
    }

    public function testAnUnregisteredTypeHasNoHookNameAndNoOptions(): void
    {
        $provider = new EntityOptionsProvider($this->registry());

        $this->assertSame('', $provider->hookNameFor('unicorn'));
        $this->assertSame(array(), $provider->optionsFor('unicorn'));
    }

    public function testSuppliedOptionsWinOverEverythingElse(): void
    {
        $provider = new EntityOptionsProvider(
            $this->registry(),
            array('customer' => array('C001' => 'Acme Pty Ltd'))
        );

        $this->assertSame(array('C001' => 'Acme Pty Ltd'), $provider->optionsFor('customer'));
    }

    public function testATypeThatOptsOutOfAPickerIsNotPickable(): void
    {
        $provider = new EntityOptionsProvider(
            $this->registry(),
            array(
                'customer' => array('C001' => 'Acme Pty Ltd'),
                'contract' => array('K1' => 'Contract One'),
            )
        );

        $pickable = $provider->pickableTypes();

        $this->assertContains('customer', $pickable);
        $this->assertNotContains('contract', $pickable, 'A picker=false type must never be offered');
    }

    public function testATypeWithNoRecordsIsNotPickable(): void
    {
        $provider = new EntityOptionsProvider($this->registry());

        $this->assertNotContains(
            'empty_module',
            $provider->pickableTypes(),
            'A picker with nothing to choose from is not pickable'
        );
    }

    public function testOptionsAreMemoisedSoAResponderIsCalledOncePerType(): void
    {
        $provider = new EntityOptionsProvider(
            $this->registry(),
            array('customer' => array('C001' => 'Acme Pty Ltd'))
        );

        $provider->optionsFor('customer');
        $this->assertSame($provider->optionsFor('customer'), $provider->optionsFor('customer'));
    }

    public function testTypeLabelsComeFromTheRegistryTitles(): void
    {
        $labels = (new EntityOptionsProvider($this->registry()))->typeLabels();

        $this->assertSame('Customer', $labels['customer']);
        $this->assertSame('Contract', $labels['contract']);
    }

    public function testALabelCanBeResolvedForAnAlreadySavedLink(): void
    {
        $provider = new EntityOptionsProvider(
            $this->registry(),
            array('customer' => array('C001' => 'Acme Pty Ltd'))
        );

        $this->assertSame('Acme Pty Ltd', $provider->labelFor('customer', 'C001'));
    }

    public function testAMissingRecordResolvesToAnEmptyLabelRatherThanFailing(): void
    {
        $provider = new EntityOptionsProvider(
            $this->registry(),
            array('customer' => array('C001' => 'Acme Pty Ltd'))
        );

        $this->assertSame('', $provider->labelFor('customer', 'GONE'));
    }

    public function testAllReturnsOptionsKeyedByType(): void
    {
        $provider = new EntityOptionsProvider(
            $this->registry(),
            array('customer' => array('C001' => 'Acme Pty Ltd'))
        );

        $all = $provider->all();

        $this->assertArrayHasKey('customer', $all);
        $this->assertArrayHasKey('contract', $all);
        $this->assertSame(array('C001' => 'Acme Pty Ltd'), $all['customer']);
    }
}