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
    global $DB, $USER, $CFG;

    $postform = optional_param('_qf__mod_forum_post_form', 0, PARAM_BOOL);
    if (!$postform) {
        return;
    }

    $postcontent = optional_param_array('message', array(), PARAM_RAW);
    if (!isset($postcontent['text'])) {
        return;
    }

    $courseid = optional_param('course', SITEID, PARAM_INT);
    $course = $DB->get_record('course', array('id' => $courseid));
    $lang = isset($course->lang) ? $course->lang : $CFG->lang;

    $postsubject = optional_param('subject', null, PARAM_RAW);
    block_spam_deletion_run_akismet_filtering($postsubject."\n".$postcontent['text'], $lang);

    $errorcode = 'triggerwords';
    if (!block_spam_deletion_message_is_spammy($postcontent['text'])
        && !block_spam_deletion_message_is_spammy($postsubject)) {

        // Message doesn't look spammy, but is the user over their 'new post threshold'?
        if (!block_spam_deletion_user_over_post_threshold()) {
            return;
        } else {
            $errorcode = 'postlimit';
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
    block_spam_deletion_block_post_and_die($postcontent['text'], $errorcode);
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
 * @param text $submittedcontent the content which was blocked from posting.yy
 * @param text $errorcode for debugging
 */
function block_spam_deletion_block_post_and_die($submittedcontent, $errorcode) {
    global $PAGE, $OUTPUT, $SITE, $CFG;
    // It sucks a bit that we die() becase the user can't easily edit their post if they are real, but
    // this seems to be the best way to make it clear.

    // Record count of blocked posts and suspend account if necessary..
    $accountsuspended = block_spam_deletion_record_blocked_post();

    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/');
    $PAGE->set_title(get_string('error'));
    $PAGE->set_heading($SITE->fullname);

    echo $OUTPUT->header();
    if ($accountsuspended) {
        echo $OUTPUT->heading(get_string('accountsuspendedtitle', 'block_spam_deletion'));
        echo $OUTPUT->box(get_string('accountsuspended', 'block_spam_deletion'));
    } else {
        echo $OUTPUT->heading(get_string('messageblockedtitle', 'block_spam_deletion'));
        echo $OUTPUT->box(get_string('messageblocked', 'block_spam_deletion'));
    }
    echo $OUTPUT->box(html_writer::tag('pre', s($submittedcontent), array('class' => 'notifytiny')));
    if (!empty($CFG->debugdeveloper)) {
        echo $OUTPUT->box("Error code: $errorcode");
    }
    echo $OUTPUT->footer();
    die;
}

/**
 * Runs akismet filtering checks and blocks post if necessary.
 * @param string $content the text content
 * @param string $language the language code of content
 */
function block_spam_deletion_run_akismet_filtering($content, $language) {
    global $CFG, $USER;

    if (empty($CFG->block_spam_deletion_akismet_key) ||
        empty($CFG->block_spam_deletion_akismet_account_age)) {
        return;
    }

    if ($USER->firstaccess < (time() - $CFG->block_spam_deletion_akismet_account_age)) {
        return;
    }
    block_spam_deletion_run_characterset_filtering($content, $language);

    // Do akismet detection of new users post content..
    $akismet = new block_spam_deletion\akismet($CFG->block_spam_deletion_akismet_key);
    if ($akismet->is_user_posting_spam($content, $language)) {
        block_spam_deletion_block_post_and_die($content, 'akismet');
    }
}

/**
 * Run filtering on content dependent on the expected language being posted in.
 *
 * Works by converting from utf8 to the 'oldcharset' set in the langconfig and back again
 * and counting the percentage of unrecognised characters. Should for example, stop excessive
 * korean from being used in spanish course.
 *
 * @param text $content the text being posted
 * @param text $language the language code post should be in
 */
function block_spam_deletion_run_characterset_filtering($content, $language) {
    global $CFG;

    if (empty($CFG->block_spam_deletion_invalidchars_percentage)) {
        // Handle config not set.
        return;
    }

    $oldcharset = get_string_manager()->get_string('oldcharset', 'langconfig', null, $language);

    // Remove existing ? and 'space' from content so we can count without them at end.
    $text = preg_replace('(\?+|\s+)', '', $content);
    $intermediary = core_text::convert($text, 'UTF-8', $oldcharset);
    $output = core_text::convert($intermediary, $oldcharset, 'UTF-8');

    // Count unknown characters.
    $missingcharscount = substr_count($output, '?');
    $percentagemissing = round(($missingcharscount / core_text::strlen($text)) * 100);
    //debugging("Input: $text \nOutput (via $oldcharset): $output \nSummary: $percentagemissing %)");

    if ($percentagemissing > $CFG->block_spam_deletion_invalidchars_percentage) {
        block_spam_deletion_block_post_and_die($content, "$percentagemissing% invalid chars");
    }
}

/**
 * Record a user has had their post blocked - and suspend them if necessary.
 *
 * @return bool true if user is suspended (on final chance)
 */
function block_spam_deletion_record_blocked_post() {
    global $USER, $DB;

    $blockcount = get_user_preferences('block_spam_deletion_blocked_posts_count', 0);
    $blockcount++;

    // On fourth blocked post, we suspend their account.
    if ($blockcount > 3) {
        // Remove blockcount preference in case they get un-suspended
        set_user_preference('block_spam_deletion_blocked_posts_count', null);

        // Suspend the user and update their profile description.
        $updateuser = new stdClass();
        $updateuser->id = $USER->id;
        $updateuser->suspended = 1;
        $updateuser->description = get_string('blockedspamdescription', 'block_spam_deletion', date('l jS F g:i A'));
        $DB->update_record('user', $updateuser);

        // Kill the users session.
        \core\session\manager::kill_user_sessions($USER->id);

        return true;
    } else {
        set_user_preference('block_spam_deletion_blocked_posts_count', $blockcount);
        return false;
    }
}
