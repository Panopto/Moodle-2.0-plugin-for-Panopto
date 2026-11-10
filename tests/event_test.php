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

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the block_panopto event classes.
 *
 * @package    block_panopto
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_test extends \advanced_testcase {
    /**
     * Provisioning triggers in the course context with its other data and a readable description.
     *
     * @return void
     * @covers \block_panopto\event\course_provisioned
     */
    public function test_course_provisioned(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $other = ['servername' => 'demo.panopto.test', 'folderid' => '11111111-2222-3333-4444-555555555555'];

        $sink = $this->redirectEvents();
        event\course_provisioned::create(['context' => $context, 'other' => $other])->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(event\course_provisioned::class, $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEquals($course->id, $event->courseid);
        $this->assertSame($other, $event->other);
        $this->assertEquals(new \moodle_url('/course/view.php', ['id' => $course->id]), $event->get_url());
        $this->assertStringContainsString((string) $course->id, $event->get_description());
        $this->assertNotEmpty(event\course_provisioned::get_name());
    }

    /**
     * Provisioning refuses the system context and missing other data.
     *
     * @return void
     * @covers \block_panopto\event\course_provisioned
     */
    public function test_course_provisioned_validation(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = ['servername' => 'demo.panopto.test', 'folderid' => '11111111-2222-3333-4444-555555555555'];

        try {
            event\course_provisioned::create(['context' => \context_system::instance(), 'other' => $other]);
            $this->fail('A system context should be refused.');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('CONTEXT_COURSE', $e->getMessage());
        }

        $this->expectException(\coding_exception::class);
        event\course_provisioned::create(['context' => \context_course::instance($course->id), 'other' => []]);
    }

    /**
     * Unprovisioning a deleted course is logged in the system context and keeps the course id.
     *
     * @return void
     * @covers \block_panopto\event\course_unprovisioned
     */
    public function test_course_unprovisioned(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $sink = $this->redirectEvents();
        event\course_unprovisioned::create([
            'context' => \context_course::instance($course->id),
            'other' => ['courseid' => (int) $course->id, 'servername' => 'demo.panopto.test'],
        ])->trigger();
        event\course_unprovisioned::create([
            'context' => \context_system::instance(),
            'other' => ['courseid' => 999999, 'servername' => null],
        ])->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(2, $events);
        $this->assertEquals($course->id, $events[0]->courseid);
        $this->assertEquals(\context_system::instance(), $events[1]->get_context());
        $this->assertStringContainsString('999999', $events[1]->get_description());

        $this->expectException(\coding_exception::class);
        event\course_unprovisioned::create(['context' => \context_system::instance(), 'other' => ['courseid' => 1]]);
    }

    /**
     * Building the category structure is logged in the system context; unknown operations are refused.
     *
     * @return void
     * @covers \block_panopto\event\bulk_operation_completed
     */
    public function test_bulk_operation_completed(): void {
        $this->resetAfterTest();

        $sink = $this->redirectEvents();
        event\bulk_operation_completed::create([
            'context' => \context_system::instance(),
            'other' => ['operation' => 'build_category_structure'],
        ])->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(event\bulk_operation_completed::class, $event);
        $this->assertEquals(\context_system::instance(), $event->get_context());
        $this->assertSame('build_category_structure', $event->other['operation']);

        $this->expectException(\coding_exception::class);
        event\bulk_operation_completed::create([
            'context' => \context_system::instance(),
            'other' => ['operation' => 'drop_everything'],
        ]);
    }
}
