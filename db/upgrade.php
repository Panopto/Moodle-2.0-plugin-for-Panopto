<?php

/**
 * Panopto block update routine.
 *
 * @package     block_panopto
 * @copyright   Panopto 2009 - 2013 / With contributions from Spenser Jones (sjones@ambrose.edu)
 * @license     http://www.gnu.org/licenses/lgpl.html GNU LGPL
 */

function xmldb_block_panopto_upgrade($oldversion = 0) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2014121502) {
        //Add db fields for servername and application key per course

        if (isset($CFG->block_panopto_server_name)) {
            $oldServerName = $CFG->block_panopto_server_name;
        }
        if (isset($CFG->block_panopto_application_key)) {
            $oldAppKey = $CFG->block_panopto_application_key;
        }

        // Define field panopto_server to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('panopto_server', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'panopto_id');

        // Conditionally launch add field panopto_server.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            if (isset($oldServerName)) {
                $DB->set_field('block_panopto_foldermap', 'panopto_server', $oldServerName, null);
            }
        }


        // Define field panopto_app_key to be added to block_panopto_foldermap.
        $table = new xmldb_table('block_panopto_foldermap');
        $field = new xmldb_field('panopto_app_key', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'panopto_server');

        // Conditionally launch add field panopto_app_key.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            if (isset($oldAppKey)) {
                $DB->set_field('block_panopto_foldermap', 'panopto_app_key', $oldAppKey, null);
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

    if ($oldversion < 2015022500) {
        // Make block_panopto_server_number reflecting the actual number of servers.
        set_config('block_panopto_server_number', $CFG->block_panopto_server_number + 1);

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2015022500, 'panopto');
    }

    if ($oldversion < 2015030300) {
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

        // Move block_panopto_async_tasks
        if (isset($CFG->block_panopto_async_tasks)) {
            set_config('async_tasks', $CFG->block_panopto_async_tasks, 'block_panopto');
            unset_config('block_panopto_async_tasks');
        }

        // Panopto savepoint reached.
        upgrade_block_savepoint(true, 2015030300, 'panopto');
    }

    return true;
}