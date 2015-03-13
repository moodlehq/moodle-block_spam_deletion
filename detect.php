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

/**
 * Check if the given message should be considered as a spam.
 */
function block_spam_deletion_message_is_spammy($message) {
    global $CFG;

    // Work with a copy of the passed value (in case we will need it yet later).
    $text = $message;

    // Firstly, ignore all links to our draftfile.php as those are probably attached media.
    $urldraftfile = "$CFG->httpswwwroot/draftfile.php";
    $text = str_ireplace($urldraftfile, '', $text);

    // Also, ignore all links to our site itself.
    $text = str_ireplace($CFG->httpswwwroot, '', $text);
    $text = str_ireplace($CFG->wwwroot, '', $text);

    // How many URLs are left now? We do not rely on href="..." or similar HTML
    // syntax as the spammer can use Markdown or even just plain URLs in the text.
    $found = preg_match_all("~(http://|https://|ftp://)~i", $text, $matches);

    // A post with three or more URLs is considered spammy for our purposes.
    if ($found >= 3) {
        return true;
    }

    // Look for words that may indicate spam.
    if (!empty($CFG->block_spam_deletion_badwords)) {
        $badwords = explode(',', $CFG->block_spam_deletion_badwords);

        $pattern = '/';
        $divider = '';
        foreach ($badwords as $badword) {
            $badword = trim($badword);
            $pattern .= $divider . '\b' . preg_quote($badword) . '\b';
            $divider = '|';
        }
        $pattern .= '/i';
        if (preg_match($pattern, $text)) {
            return true;
        }
    }

    return false;
}

/**
 * Make sure the submitted forum post form does not contain a spam.
 */
function block_spam_deletion_detect_post_spam() {
    global $DB, $USER;

    $postform = optional_param('_qf__mod_forum_post_form', 0, PARAM_BOOL);
    if (!$postform) {
        return;
    }

    $postcontent = optional_param_array('message', array(), PARAM_RAW);
    if (!isset($postcontent['text'])) {
        return;
    }

    block_spam_deletion_run_akismet_filtering($postcontent['text']);

    $postsubject = optional_param('subject', null, PARAM_RAW);
    if (!block_spam_deletion_message_is_spammy($postcontent['text'])
        && !block_spam_deletion_message_is_spammy($postsubject)) {

        // Message doesn't look spammy, but is the user over their 'new post threshold'?
        if (!block_spam_deletion_user_over_post_threshold()) {
            return;
        }
    }

    $sql = 'SELECT count(id) FROM {forum_posts} WHERE userid = :userid AND created < :yesterday';
    $params = array('userid' => $USER->id, 'yesterday' => (time() - DAYSECS));
    $postcount = $DB->count_records_sql($sql, $params);

    if ($postcount >= 1) {
        // Do nothing, they've got some non-spammy posts.
        return;
    }

    // OK - we should block the post..
    block_spam_deletion_block_post_and_die($postcontent['text']);
}

/**
 * Detect if user is over 'post threshold' for period. NOTE: this
 * does not consider if the user is 'new' - that is hanlded elsewhere.
 *
 * @return bool true if user if over post threshold.
 */
function block_spam_deletion_user_over_post_threshold() {
    global $CFG, $USER, $DB;

    if (empty($CFG->block_spam_deletion_throttle_postcount) ||
        empty($CFG->block_spam_deletion_throttle_duration)) {
            // Handle config not set.
            return false;
    }

    $params = array('userid' => $USER->id, 'timestamp' => (time() - $CFG->block_spam_deletion_throttle_duration));
    $postcount = $DB->count_records_select('forum_posts', 'userid = :userid AND created > :timestamp', $params);

    return ($postcount >= $CFG->block_spam_deletion_throttle_postcount);
}

/**
 * Print a 'friendly' error message informing the user their post has been
 * blocked and die.
 * @param text $submittedcontent the content which was blocked from posting.
 */
function block_spam_deletion_block_post_and_die($submittedcontent) {
    global $PAGE, $OUTPUT, $SITE;
    // It sucks a bit that we die() becase the user can't easily edit their post if they are real, but
    // this seems to be the best way to make it clear.

    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/');
    $PAGE->set_title(get_string('error'));
    $PAGE->set_heading($SITE->fullname);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('messageblockedtitle', 'block_spam_deletion'));
    echo $OUTPUT->box(get_string('messageblocked', 'block_spam_deletion'));
    echo $OUTPUT->box(html_writer::tag('pre', s($submittedcontent), array('class' => 'notifytiny')));
    echo $OUTPUT->footer();
}

/**
 * Runs akismet filtering checks and blocks post if necessary.
 */
function block_spam_deletion_run_akismet_filtering($content) {
    global $CFG, $USER;

    if (empty($CFG->block_spam_deletion_akismet_key) ||
        empty($CFG->block_spam_deletion_akismet_account_age)) {
        return;
    }

    if ($USER->firstaccess < (time() - $CFG->block_spam_deletion_akismet_account_age)) {
        return;
    }

    // Do akismet detection of new users post content..
    $akismet = new block_spam_deletion\akismet($CFG->block_spam_deletion_akismet_key);
    if ($akismet->is_user_posting_spam($content)) {
        block_spam_deletion_block_post_and_die($content);
    }
}
