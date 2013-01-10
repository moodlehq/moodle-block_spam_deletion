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

function xmldb_block_spam_deletion_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2012122000) {

        // Define field id to be added to block_spam_deletion_votes
        $table = new xmldb_table('block_spam_deletion_votes');

        $field = new xmldb_field('commentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'postid');
        // Conditionally launch add field commentid
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('idx-unique-voterid-commentid', XMLDB_INDEX_UNIQUE, array('voterid', 'commentid'));
        // Conditionally launch add index idx-unique-voterid-commentid
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $field = new xmldb_field('messageid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'commentid');
        // Conditionally launch add field messageid
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('idx-unique-voterid-messageid', XMLDB_INDEX_UNIQUE, array('voterid', 'messageid'));
        // Conditionally launch add index idx-unique-voterid-messageid
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // spam_deletion savepoint reached
        upgrade_block_savepoint(true, 2012122000, 'spam_deletion');
    }


    return true;
}
