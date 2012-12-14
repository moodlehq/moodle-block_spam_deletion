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
 * Set of functions to delete user records, making them inactive and updating
 * there record to reflect spammer.
 *
 * @package    block_spam_deletion
 * @category   spam_deletion
 * @copyright  2012 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/lib.php');

class spammer {

    /**
     * @var stdClass user whose account will be marked as spammer
     */
    public $user;

    /**
     * Constructor
     * @param int $spammmerid user id for spammer
     */
    public function __construct($spammmerid) {
        global $DB, $CFG;
        $admins = explode(',', $CFG->siteadmins);
        $guests = explode(',', $CFG->siteguest);
        //If spammerid is guest or admin or there is no id then throw exception.
        if (!empty($spammmerid) &&
                !(in_array($spammmerid, $admins) || in_array($spammmerid, $guests))) {
            $user = user_get_users_by_id(array($spammmerid));
            $this->user = $user[$spammmerid];
        } else {
            throw new moodle_exception('invalidarguments');
        }
    }

    /**
     * returns true is user is active
     */
    public function is_active() {
        $retval = false;
        if (!empty($this->user) && ($this->user->deleted == 0) && ($this->user->suspended == 0)) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * returns true if user has first accessed account in last one month
     */
    public function is_recentuser() {
        $retval = false;
        $timegap = time() - (30 * 24 * 60 * 60);
        if ($this->user->firstaccess > $timegap) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * Mark user as spammer, by deactivating account and setting description in profile
     */
    private function set_profile_as_spammer() {
        global $DB;
        $updateuser = new stdClass();
        $updateuser->id = $this->user->id;
        $updateuser->suspended = 1;
        $updateuser->description = get_string('spamdescription', 'block_spam_deletion', date('l jS F g:i A'));
        $DB->update_record('user', $updateuser);
        //user_delete_user($user);
    }

    /**
     * Delete all user messages
     */
    private function delete_user_messages() {
        global $DB;
        $userid = $this->user->id;
        $DB->delete_records('message', array('useridfrom' => $userid));
        $DB->delete_records('message_read', array('useridfrom' => $userid));
    }

    /**
     * Replace user blogs subject and summary with spam string
     */
    private function delete_user_blog() {
        global $DB;
        $spamstr = get_string('contentremoved', 'block_spam_deletion', date('l jS F g:i A'));
        $params = array('userid' => $this->user->id,
            'blogsub' => $spamstr,
            'blogsummary' => $spamstr);
        $DB->execute('UPDATE {post} SET subject = :blogsub, summary = :blogsummary', $params);
    }

    /**
     * Replace user forum subject and message with spam string
     */
    private function delete_user_forum() {
        global $DB;
        $userid = $this->user->id;
        $spamstr = get_string('contentremoved', 'block_spam_deletion', date('l jS F g:i A'));
        $params = array('userid' => $userid,
            'forumsub' => $spamstr,
            'forummsg' => $spamstr);
        $DB->execute('UPDATE {forum_posts} SET subject = :forumsub, message = :forummsg WHERE userid = :userid', $params);
    }

    /**
     * Delete all user comments
     */
    private function delete_user_comments() {
        global $DB;
        $userid = $this->user->id;
        $DB->delete_records('comments', array('userid' => $userid));
    }

    /**
     * Delete user records and mark user as spammer, by doing following:
     * 1. Delete comment, message form this user
     * 2. Update forum post and blog post with spam message
     * 3. Suspend account and set profile description as spammer
     */
    public function set_spammer() {
        //Make sure deletion should only happen for recently created account
        if ($this->is_active() && $this->is_recentuser()) {
            $this->delete_user_comments();
            $this->delete_user_forum();
            $this->delete_user_blog();
            $this->delete_user_messages();
            $this->set_profile_as_spammer();
        } else {
            throw new moodle_exception('cannotdelete', 'block_spam_deletion');
        }
    }

    /**
     * Return html to show data stats for spammer
     *
     * @return string html showing data count for spammer
     */
    public function show_data_count() {
        global $DB;
        $htmlstr = '';
        $params = array('userid' => $this->user->id);
        $userdata[] = get_string('countmessage', 'block_spam_deletion', (int)$DB->count_records('message', array('useridfrom' => $this->user->id)));
        $userdata[] = get_string('countblog', 'block_spam_deletion', (int)$DB->count_records('post', $params));
        $userdata[] = get_string('countforum', 'block_spam_deletion', (int)$DB->count_records('forum_posts', $params));
        $userdata[] = get_string('countcomment', 'block_spam_deletion', (int)$DB->count_records('comments', $params));
        $htmlstr = html_writer::tag('div class="block_spam_bold block_spam_highlight"', get_string('totalcount', 'block_spam_deletion'));
        $htmlstr .= html_writer::alist($userdata);
        return $htmlstr;
    }
}
?>
