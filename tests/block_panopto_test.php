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
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->blocks->load_blocks();
        return $page;
    }
}
