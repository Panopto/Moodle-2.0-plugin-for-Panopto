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

namespace block_panopto;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/panopto/lib/panopto_data.php');
require_once($CFG->dirroot . '/blocks/panopto/classes/rollingsync.php');

/**
 * Tests for the Moodle event observers in block_panopto_rollingsync.
 *
 * None of these tests may reach Panopto. The point of the observers is that the web request that
 * created a course or unenrolled a user never waits on a SOAP call: all Panopto work is queued as
 * an adhoc task for cron, and every observer can be switched off from site administration.
 *
 * @package    block_panopto
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_panopto_rollingsync
 */
final class rollingsync_test extends \advanced_testcase {
    /** @var string adhoc task class queued when a new course should be provisioned */
    private const PROVISION_TASK = '\block_panopto\task\provision_course';

    /** @var string adhoc task class queued when a user's Panopto permissions need re-syncing */
    private const SYNC_USER_TASK = '\block_panopto\task\sync_user';

    /**
     * Point the plugin at a (never contacted) Panopto server so is_main_block_configured() passes.
     *
     * @return void
     */
    private function configure_panopto_server(): void {
        set_config('server_number', 1, 'block_panopto');
        set_config('server_name1', 'demo.panopto.test', 'block_panopto');
        set_config('application_key1', '00000000-0000-0000-0000-000000000000', 'block_panopto');
    }

    /**
     * Count queued adhoc tasks of one class.
     *
     * @param string $classname fully qualified task class, with leading backslash
     * @return int
     */
    private function count_adhoc(string $classname): int {
        global $DB;
        return $DB->count_records('task_adhoc', ['classname' => $classname]);
    }

    /**
     * Enrol a user and return the manual enrol plugin and instance needed to change that enrolment.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @return array [enrol_manual_plugin, enrol instance record]
     */
    private function enrol_manually(\stdClass $course, \stdClass $user): array {
        global $DB;

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student', 'manual');

        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);

        return [$plugin, $instance];
    }

    /**
     * Course creation with auto-provision on queues the provisioning for cron instead of calling
     * Panopto from the request that created the course. The task records the creator so it can sync
     * them, but runs as the site admin so a suspended creator cannot cancel provisioning.
     *
     * @return void
     * @covers \block_panopto_rollingsync::coursecreated
     */
    public function test_course_created_queues_provision_task(): void {
        $this->resetAfterTest();
        $this->configure_panopto_server();
        set_config('auto_provision_new_courses', 'oncoursecreation', 'block_panopto');

        $creator = $this->getDataGenerator()->create_user();
        $this->setUser($creator);

        $course = $this->getDataGenerator()->create_course();

        $this->assertSame(1, $this->count_adhoc(self::PROVISION_TASK));

        $tasks = \core\task\manager::get_adhoc_tasks(self::PROVISION_TASK);
        $task = reset($tasks);
        $data = $task->get_custom_data();
        $this->assertSame((int) $course->id, (int) $data->courseid);
        $this->assertSame((int) $creator->id, (int) $data->creatorid);
        $this->assertNull($task->get_userid());
    }

    /**
     * Course creation without auto-provision queues nothing.
     *
     * @return void
     * @covers \block_panopto_rollingsync::coursecreated
     */
    public function test_course_created_without_auto_provision_queues_nothing(): void {
        $this->resetAfterTest();
        $this->configure_panopto_server();
        set_config('auto_provision_new_courses', 'off', 'block_panopto');

        $this->getDataGenerator()->create_course();

        $this->assertSame(0, $this->count_adhoc(self::PROVISION_TASK));
    }

    /**
     * Unenrolling and suspending a user each queue one sync_user task and never run it inline,
     * regardless of the async_tasks setting.
     *
     * @return void
     * @covers \block_panopto_rollingsync::userenrolmentdeleted
     * @covers \block_panopto_rollingsync::userenrolmentupdated
     */
    public function test_unenrolment_and_suspension_are_always_queued(): void {
        $this->resetAfterTest();
        $this->configure_panopto_server();
        // The old behaviour ran the sync inline when this was off; it must be queued either way.
        set_config('async_tasks', 0, 'block_panopto');

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        [$plugin, $instance] = $this->enrol_manually($course, $user);

        // Sync on enrolment is off by default, so enrolling queued nothing.
        $this->assertSame(0, $this->count_adhoc(self::SYNC_USER_TASK));

        $plugin->update_user_enrol($instance, $user->id, ENROL_USER_SUSPENDED);
        $this->assertSame(1, $this->count_adhoc(self::SYNC_USER_TASK));

        $plugin->unenrol_user($instance, $user->id);
        $this->assertSame(2, $this->count_adhoc(self::SYNC_USER_TASK));

        $tasks = \core\task\manager::get_adhoc_tasks(self::SYNC_USER_TASK);
        foreach ($tasks as $task) {
            $data = $task->get_custom_data();
            $this->assertSame((int) $course->id, (int) $data->courseid);
            $this->assertSame((int) $user->id, (int) $data->userid);
        }
    }

    /**
     * sync_on_unenrolment = 0 stops the unenrolment observers without touching enrolment sync.
     *
     * @return void
     * @covers \block_panopto_rollingsync::userenrolmentdeleted
     * @covers \block_panopto_rollingsync::userenrolmentupdated
     */
    public function test_sync_on_unenrolment_disabled_queues_nothing(): void {
        $this->resetAfterTest();
        $this->configure_panopto_server();
        set_config('sync_on_unenrolment', 0, 'block_panopto');

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        [$plugin, $instance] = $this->enrol_manually($course, $user);

        $plugin->update_user_enrol($instance, $user->id, ENROL_USER_SUSPENDED);
        $plugin->unenrol_user($instance, $user->id);

        $this->assertSame(0, $this->count_adhoc(self::SYNC_USER_TASK));
    }

    /**
     * The master switch stops every observer: neither course creation nor unenrolment queues work.
     *
     * @return void
     * @covers \block_panopto_rollingsync::coursecreated
     * @covers \block_panopto_rollingsync::userenrolmentdeleted
     * @covers \panopto_data::should_handle_events
     */
    public function test_event_sync_disabled_queues_nothing(): void {
        $this->resetAfterTest();
        $this->configure_panopto_server();
        set_config('auto_provision_new_courses', 'oncoursecreation', 'block_panopto');
        set_config('event_sync_enabled', 0, 'block_panopto');

        $this->assertFalse(\panopto_data::should_handle_events());

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        [$plugin, $instance] = $this->enrol_manually($course, $user);
        $plugin->unenrol_user($instance, $user->id);

        $this->assertSame(0, $this->count_adhoc(self::PROVISION_TASK));
        $this->assertSame(0, $this->count_adhoc(self::SYNC_USER_TASK));
    }

    /**
     * should_handle_events() mirrors the admin-visible state of the plugin.
     *
     * @return void
     * @covers \panopto_data::should_handle_events
     */
    public function test_should_handle_events_guard(): void {
        global $DB;
        $this->resetAfterTest();

        // No server configured: nothing to talk to.
        unset_config('server_name1', 'block_panopto');
        unset_config('application_key1', 'block_panopto');
        $this->assertFalse(\panopto_data::should_handle_events());

        // Configured, setting never written (fresh upgrade): treated as enabled.
        $this->configure_panopto_server();
        unset_config('event_sync_enabled', 'block_panopto');
        $this->assertTrue(\panopto_data::should_handle_events());

        set_config('event_sync_enabled', 1, 'block_panopto');
        $this->assertTrue(\panopto_data::should_handle_events());

        set_config('event_sync_enabled', 0, 'block_panopto');
        $this->assertFalse(\panopto_data::should_handle_events());

        // Hiding the block in Site administration > Plugins > Blocks also stops events.
        set_config('event_sync_enabled', 1, 'block_panopto');
        $DB->set_field('block', 'visible', 0, ['name' => 'panopto']);
        $this->assertFalse(\panopto_data::should_handle_events());
    }
}
