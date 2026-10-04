<?php
declare(strict_types=1);

namespace Ksfraser\Notes\Tests\Unit;

use Ksfraser\Notes\Tests\Support\FaDbFake;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the inter-module hook surface.
 *
 * These are the tests that matter most for the modules that depend on Notes:
 * CRM, HRM and Project Management call these responders over hook_invoke(), so
 * the argument handling and the error codes are a published contract.
 *
 * @BABOK Related: FR-NT-001-003
 */
class NotesResponderTest extends TestCase
{
    /** @var hooks_ksf_FA_Notes */
    private $hooks;

    protected function setUp(): void
    {
        FaDbFake::reset();
        \hooks::reset();
        $this->hooks = new \hooks_ksf_FA_Notes();
    }

    // --- discovery contract ---------------------------------------------

    public function testCapabilitiesAreAdvertised(): void
    {
        $data = array();
        $this->hooks->getModuleCapabilities($data);

        $this->assertSame(
            array('CREATE_NOTE', 'GET_NOTE', 'SEARCH_NOTES', 'GET_NOTES_FOR_ENTITY'),
            $data['capabilities']
        );
    }

    public function testHasCapabilityAnswersTrueForEachAdvertisedCapability(): void
    {
        $data = array();

        foreach (array('CREATE_NOTE', 'GET_NOTE', 'SEARCH_NOTES', 'GET_NOTES_FOR_ENTITY') as $capability) {
            $data = array();
            $this->assertTrue(
                $this->hooks->hasCapability($data, array('capability' => $capability)),
                "$capability must be reported as available"
            );
            $this->assertTrue($data['has_capability']);
        }
    }

    public function testHasCapabilityAnswersFalseForAnUnknownCapability(): void
    {
        $data = array('capability' => 'DELETE_EVERYTHING');

        $this->assertFalse($this->hooks->hasCapability($data));
        $this->assertFalse($data['has_capability']);
    }

    public function testHasCapabilityReadsTheCapabilityFromEitherArgumentChannel(): void
    {
        $fromOpts = array();
        $fromData = array('capability' => 'GET_NOTE');

        $this->assertTrue($this->hooks->hasCapability($fromOpts, array('capability' => 'GET_NOTE')));
        $this->assertTrue($this->hooks->hasCapability($fromData));
    }

    public function testConstantsAreAdvertised(): void
    {
        $data = array();
        $this->hooks->getModuleConstants($data);

        $this->assertSame(SS_ksf_FA_Notes, $data['constants']['SS_ksf_FA_Notes']);
        $this->assertSame(SA_NOTES_VIEW, $data['constants']['SA_NOTES_VIEW']);
    }

    /**
     * The RESPONDERS constant is the single source of truth; the discovery
     * methods and the advertised-values reader must all report it, so they
     * cannot drift apart.
     */
    public function testTheResponderListIsDeclaredOnceAndReusedByEveryDiscoveryMethod(): void
    {
        $this->assertSame(
            array('CREATE_NOTE', 'GET_NOTE', 'SEARCH_NOTES', 'GET_NOTES_FOR_ENTITY'),
            \hooks_ksf_FA_Notes::RESPONDERS
        );

        $data = array();
        $this->hooks->getModuleCapabilities($data);
        $this->assertSame(\hooks_ksf_FA_Notes::RESPONDERS, $data['capabilities']);

        foreach (\hooks_ksf_FA_Notes::RESPONDERS as $capability) {
            $cap = array();
            $this->assertTrue(
                $this->hooks->hasCapability($cap, array('capability' => $capability)),
                "$capability must resolve through the shared RESPONDERS list"
            );
        }
    }

    public function testRespondToCapabilityRequestDefaultsToCapabilities(): void
    {
        $data = array();
        $this->assertTrue($this->hooks->respondToCapabilityRequest($data));

        $this->assertSame('capabilities', $data['request']);
        $this->assertSame(\hooks_ksf_FA_Notes::RESPONDERS, $data['capabilities']);
    }

    public function testRespondToCapabilityRequestAnswersCapabilityChecks(): void
    {
        $data = array();
        $this->assertTrue($this->hooks->respondToCapabilityRequest($data, array('request' => 'has:GET_NOTE')));

        $other = array();
        $this->assertFalse($this->hooks->respondToCapabilityRequest($other, array('request' => 'has:NOPE')));
    }

    public function testRespondToCapabilityRequestAnswersConstants(): void
    {
        $data = array();
        $this->assertTrue($this->hooks->respondToCapabilityRequest($data, array('request' => 'constants')));
        $this->assertSame(SS_ksf_FA_Notes, $data['constants']['SS_ksf_FA_Notes']);
    }

    public function testRespondToCapabilityRequestReportsUnknownRequests(): void
    {
        $data = array();
        $this->assertNull($this->hooks->respondToCapabilityRequest($data, array('request' => 'boogey')));
        $this->assertStringStartsWith('Unknown request type:', $data['error']);
    }

    // --- CREATE_NOTE ----------------------------------------------------

    public function testCreateNoteRefusesAnEmptyBody(): void
    {
        $data = array();

        $this->assertFalse($this->hooks->CREATE_NOTE($data, array('note' => '   ')));
        $this->assertSame('note_body_required', $data['error']);
        $this->assertSame(array(), FaDbFake::$log);
    }

    public function testCreateNoteInsertsAndReportsTheNewId(): void
    {
        FaDbFake::$insertId = 17;
        $data = array();

        $ok = $this->hooks->CREATE_NOTE($data, array(
            'note' => 'Quoted for the renewal',
            'subject' => 'Acme renewal',
            'note_type' => 'Call',
        ));

        $this->assertTrue($ok);
        $this->assertSame(17, $data['note_id']);
        $this->assertStringStartsWith('INSERT INTO ' . TB_PREF . 'ksf_Notes', FaDbFake::$log[0]);
    }

    public function testCreateNoteAttachesLinksInTheSameCall(): void
    {
        FaDbFake::$insertId = 17;
        $data = array();

        $ok = $this->hooks->CREATE_NOTE($data, array(
            'note' => 'Intro call',
            'links' => array(
                array('customer', 'CUS-001'),
                array('contact', '7', 'with'),
            ),
        ));

        $this->assertTrue($ok);
        $this->assertSame(2, $data['links_added']);
        $this->assertTrue(FaDbFake::saw('INSERT INTO ' . TB_PREF . 'ksf_Notes_link'));
    }

    public function testCreateNoteCountsOnlyLinksThatWereActuallyAdded(): void
    {
        FaDbFake::$insertId = 17;
        $data = array();

        // Query order: the note INSERT, then the link's existence check. The
        // check finds a row, so the link is already present.
        FaDbFake::willReturnForNext(array());
        FaDbFake::willReturnForNext(array(array('id' => '5')));

        $this->hooks->CREATE_NOTE($data, array(
            'note' => 'Intro call',
            'links' => array(array('contact', '7', 'with')),
        ));

        $this->assertSame(0, $data['links_added']);
    }

    public function testCreateNoteSkipsLinksItCannotValidate(): void
    {
        FaDbFake::$insertId = 17;
        $data = array();

        $ok = $this->hooks->CREATE_NOTE($data, array(
            'note' => 'Intro call',
            'links' => array(
                array('unicorn', '1'),
                array('contact', '7', 'document'),
                'not-an-array',
            ),
        ));

        $this->assertTrue($ok, 'the note is still created');
        $this->assertSame(0, $data['links_added'], 'no invalid link is written');
        $this->assertFalse(FaDbFake::saw('ksf_Notes_link'));
    }

    public function testCreateNoteAcceptsFieldsFromThePayloadAsWellAsTheOptions(): void
    {
        FaDbFake::$insertId = 5;
        $data = array('note' => 'From the payload');

        $this->assertTrue($this->hooks->CREATE_NOTE($data, null));
        $this->assertSame(5, $data['note_id']);
        $this->assertStringContainsString("'From the payload'", FaDbFake::$log[0]);
    }

    // --- GET_NOTE -------------------------------------------------------

    public function testGetNoteRequiresAnId(): void
    {
        $data = array();

        $this->assertFalse($this->hooks->GET_NOTE($data, array()));
        $this->assertSame('note_id_required', $data['error']);
    }

    public function testGetNoteReportsAMissingNote(): void
    {
        $data = array();

        $this->assertFalse($this->hooks->GET_NOTE($data, array('note_id' => 404)));
        $this->assertSame('note_not_found', $data['error']);
    }

    public function testGetNoteReturnsTheNoteWithItsLinks(): void
    {
        $data = array();

        // First query is the note, second is its links.
        FaDbFake::willReturnForNext(array(array('id' => '7', 'note' => 'Called back')));
        FaDbFake::willReturnForNext(array(array(
            'id' => '1',
            'entity_type' => 'customer',
            'entity_id' => 'CUS-001',
            'link_role' => 'regarding',
        )));
        FaDbFake::willReturnForNext(array(array('label' => 'Acme Ltd')));

        $ok = $this->hooks->GET_NOTE($data, array('note_id' => 7));

        $this->assertTrue($ok);
        $this->assertSame('Called back', $data['note']['note']);
        $this->assertCount(1, $data['note']['links']);
        $this->assertSame('Acme Ltd', $data['note']['links'][0]['label']);
    }

    // --- SEARCH_NOTES ---------------------------------------------------

    public function testSearchNotesPassesCriteriaThrough(): void
    {
        $data = array();
        FaDbFake::willReturnForNext(array(array('id' => '1'), array('id' => '2')));

        $ok = $this->hooks->SEARCH_NOTES($data, array('keyword' => 'renewal', 'limit' => 2));

        $this->assertTrue($ok);
        $this->assertSame(2, $data['count']);
        $this->assertStringContainsString("LIKE '%renewal%'", FaDbFake::onlyQuery());
    }

    public function testSearchNotesDropsEmptyCriteria(): void
    {
        $data = array();

        $this->hooks->SEARCH_NOTES($data, array('keyword' => '', 'note_type' => null));

        $sql = FaDbFake::onlyQuery();
        $this->assertStringNotContainsString('LIKE', $sql);
        $this->assertStringNotContainsString('WHERE', $sql);
    }

    // --- GET_NOTES_FOR_ENTITY -------------------------------------------

    public function testNotesForEntityRequiresBothTypeAndId(): void
    {
        $data = array();

        $this->assertFalse($this->hooks->GET_NOTES_FOR_ENTITY($data, array('entity_type' => 'customer')));
        $this->assertSame('entity_required', $data['error']);

        $data = array();
        $this->assertFalse($this->hooks->GET_NOTES_FOR_ENTITY($data, array('entity_id' => 'CUS-001')));
        $this->assertSame('entity_required', $data['error']);
    }

    public function testNotesForEntityJoinsThroughTheLinkTable(): void
    {
        $data = array();
        FaDbFake::willReturnForNext(array(array('id' => '1')));

        $ok = $this->hooks->GET_NOTES_FOR_ENTITY($data, array(
            'entity_type' => 'contact',
            'entity_id' => '7',
        ));

        $this->assertTrue($ok);
        $this->assertSame(1, $data['count']);
        $this->assertStringContainsString('INNER JOIN ' . TB_PREF . 'ksf_Notes_link', FaDbFake::onlyQuery());
    }

    public function testNotesForEntityReturnsAnEmptyListRatherThanFailingWhenThereAreNone(): void
    {
        $data = array();

        $this->assertTrue($this->hooks->GET_NOTES_FOR_ENTITY($data, array(
            'entity_type' => 'contact',
            'entity_id' => '7',
        )));
        $this->assertSame(array(), $data['notes']);
        $this->assertSame(0, $data['count']);
    }

    // --- installer / menu -----------------------------------------------

    public function testCheckOnlyActivationTouchesNoSchema(): void
    {
        $this->assertTrue($this->hooks->activate_extension(1, true));
        $this->assertSame(array(), \hooks::$updateDatabasesCalls);
    }

    public function testActivationImportsTheSchemaThroughFa(): void
    {
        $this->assertTrue($this->hooks->activate_extension(1, false));

        $this->assertCount(1, \hooks::$updateDatabasesCalls);
        $call = \hooks::$updateDatabasesCalls[0];
        $this->assertSame(1, $call['company']);
        $this->assertFalse($call['check_only']);
        $this->assertCount(2, $call['updates'], 'one entry per owned table');
    }

    public function testActivationGatesEachFileOnItsOwnTable(): void
    {
        $this->hooks->activate_extension(1, false);
        $updates = \hooks::$updateDatabasesCalls[0]['updates'];

        $sql_dir = dirname(__DIR__, 2) . '/sql/';

        $tables = array();
        foreach ($updates as $file => $update) {
            // FA's update_databases() builds the import path itself as
            // <path_to_root>/modules/<module_name>/sql/<file>, so an absolute
            // key would resolve to a path that does not exist and the table
            // would silently never be created.
            $this->assertStringStartsNotWith('/', $file, 'the key must be a bare filename');
            $this->assertFileExists($sql_dir . $file, 'update_databases() needs a real file to import');
            $tables[] = $update[0];
        }

        $this->assertSame(array('ksf_Notes', 'ksf_Notes_link'), $tables);
    }

    public function testTheLinkTableIsImportedAfterTheNoteTable(): void
    {
        // db_import() runs the files in array order, so the table the link
        // points at must exist first.
        $this->hooks->activate_extension(1, false);
        $updates = array_keys(\hooks::$updateDatabasesCalls[0]['updates']);

        $this->assertSame('ksf_notes.sql', $updates[0]);
        $this->assertSame('ksf_notes_link.sql', $updates[1]);
    }

    public function testMenuEntryIsAddedToTheOrdersApp(): void
    {
        $app = $this->fakeApp('orders');

        $this->hooks->install_options($app);

        $this->assertCount(1, $app->modules, 'a Notes module section is registered');
        $this->assertSame(_('Notes'), $app->modules[0]->name);
        $this->assertCount(1, $app->rappFunctions);
        $this->assertSame('Notes', $app->rappFunctions[0]['label']);
        $this->assertSame('modules/ksf_FA_Notes/pages/notes.php', $app->rappFunctions[0]['link']);
        // The renderer uses this as a string key into $security_areas, so it
        // must be the area NAME, not the SA_* numeric constant.
        $this->assertSame('SA_NOTES_VIEW', $app->rappFunctions[0]['access']);
    }

    public function testMenuEntryIsSkippedForOtherApps(): void
    {
        $app = $this->fakeApp('GL');

        $this->hooks->install_options($app);

        $this->assertCount(0, $app->modules);
        $this->assertCount(0, $app->rappFunctions);
    }

    public function testExistingNotesModuleIsReusedNotDuplicated(): void
    {
        $app = $this->fakeApp('orders');
        // Simulate the Notes section already being present on the app.
        $existing = new \stdClass();
        $existing->name = _('Notes');
        $app->modules[] = $existing;

        $this->hooks->install_options($app);

        $this->assertCount(1, $app->modules, 'no second Notes module is added');
        $this->assertCount(1, $app->rappFunctions);
        $this->assertSame(0, $app->rappFunctions[0]['level'], 'the function lands on the existing module level');
    }

    /**
     * A stand-in for FA's application object, recording add_module() and
     * add_rapp_function() the way install_options() uses them.
     *
     * @param string $id
     * @return object
     */
    private function fakeApp($id)
    {
        return new class($id) {
            public $id;
            public $modules = array();
            public $rappFunctions = array();

            public function __construct($id)
            {
                $this->id = $id;
            }

            public function add_module($name)
            {
                $mod = new \stdClass();
                $mod->name = $name;
                $this->modules[] = $mod;
                return $mod;
            }

            public function add_rapp_function($level, $label, $link, $access = 'SA_OPEN', $category = '')
            {
                $this->rappFunctions[] = array(
                    'level' => $level,
                    'label' => $label,
                    'link' => $link,
                    'access' => $access,
                );
            }
        };
    }

    public function testNoMenuEntryIsAddedToOtherApps(): void
    {
        $app = new \stdClass();
        $app->id = 'setup';

        $this->hooks->install_options($app);

        $this->assertSame(array(), \hooks::$addedApps);
    }

    public function testSecurityAreasAreViewAndManageUnderTheModuleSection(): void
    {
        list($areas, $sections) = $this->hooks->install_access();

        $this->assertArrayHasKey(SS_ksf_FA_Notes, $sections);
        $this->assertSame(SS_ksf_FA_Notes | 1, $areas['SA_NOTES_VIEW'][0]);
        $this->assertSame(SS_ksf_FA_Notes | 2, $areas['SA_NOTES_MANAGE'][0]);
    }

    public function testTheModuleSectionIdDoesNotCollideWithCoreFrontAccounting(): void
    {
        // 131 << 8 must not land on a section FA core already uses.
        $section = SS_ksf_FA_Notes >> 8;

        $this->assertNotSame(0, $section);
        $this->assertSame(131, $section);
    }
}