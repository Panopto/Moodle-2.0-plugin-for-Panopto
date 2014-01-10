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

/**
 * Upgrade the Panopto block
 *
 * @global moodle_database $DB
 * @param int $oldversion
 * @param object $block
 */
function xmldb_block_panopto_upgrade($oldversion, $block) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2013122001) {

        // Define table panopto_course_update_list to be created.
        $table = new xmldb_table('panopto_course_update_list');

        // Adding fields to table panopto_course_update_list.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table panopto_course_update_list.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table panopto_course_update_list.
        $table->add_index('courseid_key', XMLDB_INDEX_UNIQUE, array('courseid'));

        // Conditionally launch create table for panopto_course_update_list.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2013122001, 'panopto');
    }

    return true;
}

