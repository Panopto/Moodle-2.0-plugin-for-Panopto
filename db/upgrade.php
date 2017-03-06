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
 * Scripts used for upgrading database when upgrading block from an older version
 *
 * @package block_panopto
 * @copyright  Panopto 2009 - 2016 with contributions from Spenser Jones (sjones@ambrose.edu)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrades panopto for xmldb
 *
 * @param int $oldversion the previous version panopto is being upgraded from
 */
function xmldb_block_panopto_upgrade($oldversion = 0) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2014121502) {

        // Add db fields for servername and application key per course.
        if (isset($CFG->block_panopto_server_name)) {
            $oldservername = $CFG->block_panopto_server_name;
        }
        if (isset($CFG->block_panopto_application_key)) {
            $oldappkey = $CFG->block_panopto_application_key;
        }

        // Define field panopto_server to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('panopto_server', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'panopto_id');

        // Conditionally launch add field panopto_server.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            if (isset($oldservername)) {
                $DB->set_field('block_panopto_foldermap', 'panopto_server', $oldservername, null);
            }
        }

        // Define field panopto_app_key to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('panopto_app_key', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'panopto_server');

        // Conditionally launch add field panopto_app_key.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            if (isset($oldappkey)) {
                $DB->set_field('block_panopto_foldermap', 'panopto_app_key', $oldappkey, null);
            }
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2014121502, 'panopto');
    }

    if ($oldversion < 2015012901) {

        // Define field publisher_mapping to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('publisher_mapping', XMLDB_TYPE_CHAR, '20', null, null, null, '1', 'panopto_app_key');

        // Conditionally launch add field publisher_mapping.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field creator_mapping to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('creator_mapping', XMLDB_TYPE_CHAR, '20', null, null, null, '3,4', 'publisher_mapping');

        // Conditionally launch add field creator_mapping.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2015012901, 'panopto');
    }

    if ($oldversion <= 2016101227) {
        // Move block global settings to <prefix>_config_plugin table.
        // First, move each server configuration. We are not relying here on
        // block_panopto_server_number to determine number of servers, as there
        // could be more. Moving all that we will find in order not to leave
        // any abandoned config values in global configuration.
        for ($x = 1; $x <= 10; $x++) {
            if (isset($CFG->{'block_panopto_server_name' . $x})) {
                set_config('server_name' . $x, $CFG->{'block_panopto_server_name' . $x}, 'block_panopto');
                unset_config('block_panopto_server_name' . $x);
            }
            if (isset($CFG->{'block_panopto_application_key' . $x})) {
                set_config('application_key' . $x, $CFG->{'block_panopto_application_key' . $x}, 'block_panopto');
                unset_config('block_panopto_application_key' . $x);
            }
        }
        // Now move block_panopto_server_number setting value.
        if (isset($CFG->block_panopto_server_number)) {
            set_config('server_number', $CFG->block_panopto_server_number, 'block_panopto');
            unset_config('block_panopto_server_number');
        }
        // Move block_panopto_instance_name.
        if (isset($CFG->block_panopto_instance_name)) {
            set_config('instance_name', $CFG->block_panopto_instance_name, 'block_panopto');
            unset_config('block_panopto_instance_name');
        }
        // Move block_panopto_async_tasks.
        if (isset($CFG->block_panopto_async_tasks)) {
            set_config('async_tasks', $CFG->block_panopto_async_tasks, 'block_panopto');
            unset_config('block_panopto_async_tasks');
        }
        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2016101227, 'panopto');
    }

    if ($oldversion < 2016102709) {
        // Define table importmap where we will place all of our imports.
        $table = new xmldb_table('block_panopto_importmap');

        if (!$dbman->table_exists($table)) {
            $importfields = array();
            $importfields[] = new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, true);
            $importfields[] = new xmldb_field('target_moodle_id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);
            $importfields[] = new xmldb_field('import_moodle_id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL);

            $importkey = new xmldb_key('primary', XMLDB_KEY_PRIMARY, array('id'), null, null);

            foreach ($importfields as $importfield) {
                // Conditionally launch add field import_moodle_id.
                $table->addField($importfield);
            }

            $table->addKey($importkey);

            $dbman->create_table($table);
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2016102709, 'panopto');
    }
    
    if ($oldversion < 2017020101) {
        // Increase size of publisher_mapping and creator_mapping to allow for
        // more roles to be selected.
        $table = new xmldb_table('block_panopto_foldermap');
        $fields = array();
        $fields[] = new xmldb_field('publisher_mapping', XMLDB_TYPE_CHAR, '255', null, null, null, '1', 'panopto_app_key');
        $fields[] = new xmldb_field('creator_mapping', XMLDB_TYPE_CHAR, '255', null, null, null, '3,4', 'publisher_mapping');
        
        foreach ($fields as $field) {
            $dbman->change_field_precision($table, $field);            
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2017020101, 'panopto');        
    }

    return true;
}
