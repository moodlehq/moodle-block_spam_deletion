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
 * Show block on user profile and allow admin to delete all contents for that
 * user, makinging user inactive and update profile with spammer.
 *
 * @package    block_spam_deletion
 * @copyright  2012 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/spam_deletion/lib.php');

/**
 * Spam deletion class
 *
 * Show block on user profile and allow admin to delete all contents for that
 * user, makinging user inactive and update profile with spammer.
 *
 * @package    block_spam_deletion
 * @category   spam_deletion
 * @copyright  2012 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_spam_deletion extends block_base {
    /**
     * Block initialisation
     *
     * @return  void
     */
    function init() {
        $this->title = get_string('pluginname', 'block_spam_deletion');
    }

    /**
     * Only allow one instance of this block.
     *
     * @return bool
     */
    function instance_allow_multiple() {
        return false;
    }

    /**
     * There are settings in settings.php
     */
    function has_config() {
        return true;
    }

    /**
     * Defines on which pages, block can be visible.
     *
     * @return array
     */
    function applicable_formats() {
        return array('site-index' => true, 'user-profile' => true);
    }

    /**
     * Returns contents of block
     *
     * @return   StdClass    containing the block's content
     */
    function get_content() {
        global $CFG, $USER, $OUTPUT, $DB;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';

        if (isloggedin() && !isguestuser()) {
            $this->page->requires->strings_for_js(array('reportasspam'), 'block_spam_deletion');
            $this->page->requires->js_init_call('M.block_spam_deletion.add_to_comments');

            if ($this->page->pagetype == 'mod-forum-discuss') {
                // Add the JS to put the 'Report as spam' link.
                $this->page->requires->js_init_call('M.block_spam_deletion.add_to_forum_posts');
            }
        }

        if (has_capability('block/spam_deletion:viewspamreport', $this->context)) {
            // Display link to spam votes if on non-profile page.
            $votecount = $DB->count_records('block_spam_deletion_votes');

            if ($votecount) {
                $this->content->text = html_writer::tag('p', html_writer::link(
                    new moodle_url('/blocks/spam_deletion/viewvotes.php'),
                    get_string('spamreports', 'block_spam_deletion', $votecount)));
            }
        }

        if ($this->page->pagetype != 'user-profile') {
            return $this->content;
        }

        // Only user with spamdelete capablity should see this block.
        if (!has_capability('block/spam_deletion:spamdelete', $this->context)) {
            return $this->context;
        }


        $params = $this->page->url->params();
        if (!spammerlib::is_suspendable_user($params['id'])) {
            $this->content->text.= get_string('cannotdelete', 'block_spam_deletion');
            return $this->content;
        }

        $spamlib = new spammerlib($params['id']);

        if (!$spamlib->is_active()) {
            // If deleted or suspended account, then don't do anything.
            $this->content->text.= get_string('cannotdelete', 'block_spam_deletion');

        } else {
            if (!$spamlib->is_recentuser()) {
                // Display a warning.
                $this->content->text .= html_writer::div(
                    get_string('notrecentlyaccessed', 'block_spam_deletion'),
                    'notrecentlyaccessed'
                );
            }

            // Show spammer data count (blog post, messages, forum and comments).
            $this->content->text .= $spamlib->show_data_count();

            // Add delete button.
            $urlparams = array('userid' => $params['id']);
            $url = new moodle_url('/blocks/spam_deletion/confirmdelete.php', $urlparams);
            $buttontext = get_string('deletebutton', 'block_spam_deletion');
            $button = new single_button($url, $buttontext);
            $content = $OUTPUT->render($button);
            $this->content->footer = $content;
        }
    }

    function cron() {
        global $DB;

        $regexp = '/<img|fuck|casino|porn|xxx|cialis|viagra|poker|warcraft|ejaculation|pharmaceuticals|locksmith|ugg boots/i';

        $rs = $DB->get_recordset_select('user', 'firstaccess > ?', array(time() - DAYSECS));
        $spammers = array();
        foreach ($rs as $u) {
            if (preg_match($regexp, $u->description)) {
                $spammers[] = $u;
            }
        }
        $rs->close();

        foreach ($spammers as $spammer) {
            $record = new stdClass();
            $record->spammerid = $spammer->id;
            $record->weighting = 0;
            $DB->insert_record('block_spam_deletion_votes', $record);
        }
        return true;
    }
}

