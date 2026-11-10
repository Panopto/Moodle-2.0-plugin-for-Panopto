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
 * Unit test for panopto block.
 *
 * @package    block_panopto
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \block_panopto
 */
final class block_panopto_test extends \advanced_testcase {
    /**
     * Test that a panopto block can be created on a course.
     *
     * @return void
     * @covers ::init
     */
    public function test_create_block_on_course(): void {
        $this->resetAfterTest();

        // Ensure we have the proper privileges to add blocks.
        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Add the panopto block to the course page.
        $page = self::construct_page($course);
        $page->blocks->add_block_at_end_of_default_region('panopto');

        // Reload page and blocks to pick up added block.
        $page = self::construct_page($course);
        $page->blocks->load_blocks();
    }

    /**
     * Test that the return URL handed to the Panopto provisioning pages points back at the
     * course page this block lives on.
     *
     * The block configuration form is a dynamic form, so it is normally rendered by an AJAX
     * request to /lib/ajax/service.php. The return URL has to describe the page the block is on,
     * not the endpoint that happened to render the form, and must not include bui_editid (that
     * opens Moodle's legacy full-page editor instead of the modal).
     *
     * @return void
     * @covers \block_panopto_edit_form::get_block_config_url
     */
    public function test_edit_form_return_url_is_the_block_config_page(): void {
        global $CFG;

        $this->resetAfterTest();

        // Ensure we have the proper privileges to add blocks.
        $this->setAdminUser();

        require_once($CFG->dirroot . '/blocks/edit_form.php');
        require_once($CFG->dirroot . '/blocks/panopto/edit_form.php');

        $course = $this->getDataGenerator()->create_course();

        // Add the panopto block to the course page and hold on to the instance that was created.
        $page = self::construct_page($course);
        $block = $page->blocks->add_block_at_end_of_default_region('panopto');
        $this->assertNotNull($block);

        // Build the form without its constructor: defining the form needs a configured Panopto
        // server, and the URL only depends on the page and block handed to the form.
        $form = (new \ReflectionClass(\block_panopto_edit_form::class))->newInstanceWithoutConstructor();
        $customdata = new \ReflectionProperty(\moodleform::class, '_customdata');
        $customdata->setValue($form, ['page' => $page, 'block' => $block]);

        $geturl = new \ReflectionMethod(\block_panopto_edit_form::class, 'get_block_config_url');
        $this->assertSame(
            '/course/view.php?id=' . $course->id,
            $geturl->invoke($form)
        );
    }

    /**
     * "Sync this course to Panopto" must pass course_id so the page confirms provisioning that
     * course instead of showing the bulk form, and must not put the sesskey in the URL.
     *
     * @return void
     * @covers \block_panopto_edit_form::get_sync_course_url
     */
    public function test_sync_course_url_includes_course_id_without_sesskey(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        require_once($CFG->dirroot . '/blocks/edit_form.php');
        require_once($CFG->dirroot . '/blocks/panopto/edit_form.php');

        $course = $this->getDataGenerator()->create_course();
        $page = self::construct_page($course);
        $block = $page->blocks->add_block_at_end_of_default_region('panopto');
        $this->assertNotNull($block);

        $form = (new \ReflectionClass(\block_panopto_edit_form::class))->newInstanceWithoutConstructor();
        $customdata = new \ReflectionProperty(\moodleform::class, '_customdata');
        $customdata->setValue($form, ['page' => $page, 'block' => $block]);

        $geturl = new \ReflectionMethod(\block_panopto_edit_form::class, 'get_sync_course_url');
        $url = $geturl->invoke($form);

        $this->assertStringContainsString('/blocks/panopto/provision_course.php?', $url);

        $query = [];
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame((string) $course->id, $query['course_id']);
        $this->assertArrayNotHasKey('sesskey', $query);
        $this->assertSame('/course/view.php?id=' . $course->id, $query['return_url']);
    }

    /**
     * Construct a course view moodle_page for the given course.
     *
     * @param \stdClass $course
     * @return \moodle_page
     */
    protected static function construct_page($course): \moodle_page {
        $context = \context_course::instance($course->id);
        $page = new \moodle_page();
        $page->set_context($context);
        $page->set_course($course);
        $page->set_url('/course/view.php', ['id' => $course->id]);
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->blocks->load_blocks();
        return $page;
    }
}
