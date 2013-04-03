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
 * Script to detect a spammy post and block it from being posted.
 *
 * @package    block_spam_deletion
 * @copyright  2013 Dan Poltawski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

block_spam_deletion_detect_post_spam();

function block_spam_deletion_message_is_spammy($message) {
    $regexp = "/(a href|https?)/";

    if (preg_match($regexp, $message)) {
        return true;
    }

    return false;
}

function block_spam_deletion_detect_post_spam() {
    global $DB, $USER, $OUTPUT;
    $postform = optional_param('_qf__mod_forum_post_form', 0, PARAM_BOOL);
    if (!$postform) {
        return;
    }

    $postcontent = optional_param_array('message', array(), PARAM_RAW);
    if (!isset($postcontent['text'])) {
        return;
    }

    if (!block_spam_deletion_message_is_spammy($postcontent['text'])) {
        return;
    }

    $sql = 'SELECT count(id) FROM {forum_posts} WHERE userid = :userid AND created < :yesterday';
    $params = array('userid' => $USER->id, 'yesterday' => (time() - DAYSECS));
    $postcount = $DB->count_records_sql($sql, $params);

    if ($postcount >= 1) {
        // Do nothing, they've got some non-spammy posts.
        return;
    }

    // OK - looks like a spammer. Lets stop the post from continuining and notify the user.

    // It sucks a bit that we die() becase the user can't easily edit their post if they are real, but
    // This seems to be the best way to make it clear.
    throw new moodle_exception('messageblocked', 'block_spam_deletion', '', $postcontent['text']);
}
