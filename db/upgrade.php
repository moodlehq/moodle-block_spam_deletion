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

    if ($oldversion < 2015031701) {

        // Define table block_spam_deletion_akismet to be created.
        $table = new xmldb_table('block_spam_deletion_akismet');

        // Adding fields to table block_spam_deletion_akismet.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('original_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('is_spam', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('user_ip', XMLDB_TYPE_CHAR, '45', null, null, null, null);
        $table->add_field('user_agent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('comment_author', XMLDB_TYPE_CHAR, '500', null, null, null, null);
        $table->add_field('comment_author_email', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('comment_author_url', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('comment_content', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table block_spam_deletion_akismet.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_spam_deletion_akismet.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Spam_deletion savepoint reached.
        upgrade_block_savepoint(true, 2015031701, 'spam_deletion');
    }

    return true;
}
