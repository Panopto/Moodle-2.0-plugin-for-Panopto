<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_panopto\task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/panopto/lib/panopto_data.php');

/**
 * Tests that automatic provisioning never overrides a course provisioned in the meantime.
 *
 * Course creation queues provision_course, and cron can start it within a second, while a teacher
 * is provisioning the same course by hand to another server.
 *
 * @package    block_panopto
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provision_course_test extends \advanced_testcase {
    /** @var string a server a teacher provisions to by hand */
    private const MANUAL_SERVER = 'scratch.panopto.test';

    /** @var string the automatic operation target server */
    private const AUTO_SERVER = 'lti13.panopto.test';

    /**
     * Configure the two Panopto servers. Neither is ever contacted.
     *
     * @return void
     */
    private function configure_servers(): void {
        set_config('auto_provision_new_courses', 'off', 'block_panopto');
        set_config('server_number', 1, 'block_panopto');
        set_config('server_name1', self::MANUAL_SERVER, 'block_panopto');
        set_config('application_key1', '11111111-1111-1111-1111-111111111111', 'block_panopto');
        set_config('server_name2', self::AUTO_SERVER, 'block_panopto');
        set_config('application_key2', '22222222-2222-2222-2222-222222222222', 'block_panopto');
    }

    /**
     * A panopto_data for the course on the automatic server, with Panopto replaced by stubs that
     * return a new folder.
     *
     * @param int $courseid
     * @param string $newfolderid the folder the stubbed Panopto provisions
     * @return \panopto_data
     */
    private function automatic_provisioner(int $courseid, string $newfolderid): \panopto_data {
        $panopto = new \panopto_data($courseid);
        $panopto->servername = self::AUTO_SERVER;
        $panopto->applicationkey = panopto_get_app_key(self::AUTO_SERVER);

        $panopto->sessionmanager = new class ($newfolderid) {
            /** @var string the folder to return */
            private $folderid;

            /**
             * Constructor.
             *
             * @param string $folderid the folder to return
             */
            public function __construct(string $folderid) {
                $this->folderid = $folderid;
            }

            /**
             * Stands in for creating the folder on Panopto.
             *
             * @param string $fullname
             * @param string $externalcourseid
             * @return \stdClass
             */
            public function provision_external_course_with_roles($fullname, $externalcourseid) {
                return (object) ['Id' => $this->folderid];
            }
        };
        $panopto->authmanager = new class {
            /**
             * Stands in for reporting integration info to Panopto.
             *
             * @return void
             */
            public function report_integration_info() {
            }
        };

        return $panopto;
    }

    /**
     * Provisioning info for a course, as get_provisioning_info() would build it.
     *
     * @param \stdClass $course
     * @return \stdClass
     */
    private function provisioning_info(\stdClass $course): \stdClass {
        return (object) ['fullname' => $course->fullname, 'externalcourseid' => 'moodle:' . $course->id];
    }

    /**
     * A course provisioned by hand after the task was queued is left on its server.
     *
     * @return void
     * @covers \block_panopto\task\provision_course::execute
     */
    public function test_task_skips_course_already_provisioned(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_servers();
        set_config('automatic_operation_target_server', self::AUTO_SERVER, 'block_panopto');

        $course = $this->getDataGenerator()->create_course();
        \panopto_data::set_course_foldermap(
            $course->id,
            'manual-folder',
            self::MANUAL_SERVER,
            panopto_get_app_key(self::MANUAL_SERVER),
            'moodle:' . $course->id
        );

        $task = new provision_course();
        $task->set_custom_data(['courseid' => (int) $course->id, 'creatorid' => 0]);

        $this->expectOutputRegex('/already provisioned to scratch\.panopto\.test; skipping automatic provisioning/');
        $task->execute();

        $mapping = $DB->get_record('block_panopto_foldermap', ['moodleid' => $course->id]);
        $this->assertSame('manual-folder', $mapping->panopto_id);
        $this->assertSame(self::MANUAL_SERVER, $mapping->panopto_server);
    }

    /**
     * A manual provision that finishes while automatic provisioning talks to Panopto is kept.
     *
     * @return void
     * @covers \panopto_data::provision_course
     */
    public function test_provision_keeps_mapping_saved_while_it_ran(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_servers();

        $course = $this->getDataGenerator()->create_course();
        $panopto = $this->automatic_provisioner((int) $course->id, 'automatic-folder');

        // The manual provision saves its mapping after the automatic one has started.
        \panopto_data::set_course_foldermap(
            $course->id,
            'manual-folder',
            self::MANUAL_SERVER,
            panopto_get_app_key(self::MANUAL_SERVER),
            'moodle:' . $course->id
        );

        $this->expectOutputRegex('/keeping that mapping/');
        $result = $panopto->provision_course($this->provisioning_info($course), true, false, true);

        $this->assertTrue($result->keptexistingmapping);
        $mapping = $DB->get_record('block_panopto_foldermap', ['moodleid' => $course->id]);
        $this->assertSame('manual-folder', $mapping->panopto_id);
        $this->assertSame(self::MANUAL_SERVER, $mapping->panopto_server);
    }

    /**
     * A mapping to a server that is no longer configured is not kept, so automatic provisioning can repair it.
     *
     * @return void
     * @covers \panopto_data::provision_course
     */
    public function test_provision_replaces_mapping_to_unconfigured_server(): void {
        global $DB;
        $this->resetAfterTest();
        $this->configure_servers();

        $course = $this->getDataGenerator()->create_course();
        \panopto_data::set_course_foldermap($course->id, 'old-folder', 'removed.panopto.test', '', 'moodle:' . $course->id);
        $panopto = $this->automatic_provisioner((int) $course->id, 'automatic-folder');
        $panopto->sessiongroupid = null;

        $result = $panopto->provision_course($this->provisioning_info($course), true, false, true);

        $this->assertEmpty($result->keptexistingmapping ?? null);
        $mapping = $DB->get_record('block_panopto_foldermap', ['moodleid' => $course->id]);
        $this->assertSame('automatic-folder', $mapping->panopto_id);
        $this->assertSame(self::AUTO_SERVER, $mapping->panopto_server);
    }
}
