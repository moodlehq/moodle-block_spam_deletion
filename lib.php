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
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot.'/tag/lib.php');

class spammerlib {

    /**
     * @var stdClass user whose account will be marked as spammer
     */
    private $user = null;

    /**
     * Constructor
     * @param int $userid user id for spammer
     */
    public function __construct($userid) {
        global $DB;

        if (!self::is_suspendable_user($userid)) {
            throw new moodle_exception('User passed is not suspendedable');
        }

        $this->user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
    }

    /**
     * Is the passed userid able to be suspended as a user?
     *
     * @param int $userid the userid of the user being checked
     * @return bool true if not a guest/admin/currnet user.
     */
    public static function is_suspendable_user($userid) {
        global $USER;

        if (empty($userid)) {
            // Userid of 0.
            return false;
        }

        if ($userid == $USER->id) {
            // Is current user.
            return false;
        }

        if (isguestuser($userid)) {
            return false;
        }

        if (is_siteadmin($userid)) {
            return false;
        }

        return true;
    }

    /**
     * returns true is user is active
     */
    public function is_active() {
        if (($this->user->deleted == 1) || ($this->user->suspended == 1)) {
            return false;
        }
        return true;
    }

    /**
     * returns true if user has first accessed account in last one month
     */
    public function is_recentuser() {
        $timegap = time() - (30 * 24 * 60 * 60);
        if ($this->user->firstaccess > $timegap) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Mark user as spammer, by deactivating account and setting description in profile
     */
    private function set_profile_as_spammer() {
        global $DB;


        // Remove profile picture files from users file area.
        $fs = get_file_storage();
        $context = context_user::instance($this->user->id, MUST_EXIST);
        $fs->delete_area_files($context->id, 'user', 'icon'); // drop all images in area

        $updateuser = new stdClass();
        $updateuser->id = $this->user->id;
        $updateuser->suspended = 1;
        $updateuser->picture = 0;
        $updateuser->imagealt = '';
        $updateuser->url = '';
        $updateuser->icq = '';
        $updateuser->skype = '';
        $updateuser->yahoo = '';
        $updateuser->aim = '';
        $updateuser->msn = '';
        $updateuser->phone1 = '';
        $updateuser->phone2 = '';
        $updateuser->department = '';
        $updateuser->institution = '';
        $updateuser->city = '-';
        $updateuser->description = get_string('spamdescription', 'block_spam_deletion', date('l jS F g:i A'));

        $DB->update_record('user', $updateuser);

        // Remove custom user profile fields.
        $DB->delete_records('user_info_data', array('userid' => $this->user->id));

        // Force logout.
        session_kill_user($this->user->id);
    }

    /**
     * Delete all user messages
     */
    private function delete_user_messages() {
        global $DB;
        $userid = $this->user->id;

        // Delete message workers..
        $sql = 'DELETE FROM {message_working}
            WHERE unreadmessageid IN
            (SELECT id FROM {message} WHERE useridfrom = ?)';
        $DB->execute($sql, array($userid));
        $DB->delete_records('message', array('useridfrom' => $userid));
        $DB->delete_records('message_read', array('useridfrom' => $userid));
    }

    /**
     * Replace user forum subject and message with spam string
     */
    private function delete_user_forum() {
        global $DB;

        // Get discussions started by the spammer.
        $rs = $DB->get_recordset('forum_posts', array('userid' => $this->user->id, 'parent' => 0));
        foreach ($rs as $post) {
            // This is really expensive, but it should be rare iterations and i'm lazy right now.
            $discussion = $DB->get_record('forum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
            $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);

            if ($forum->type == 'single') {
                // It's too complicated, skip.
                continue;
            }

            $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

            forum_delete_discussion($discussion, false, $course, $cm, $forum);
        }
        $rs->close();

        // Delete any remaining posts not discussions..
        $rs = $DB->get_recordset('forum_posts', array('userid' => $this->user->id));
        foreach ($rs as $post) {
            // This is really expensive, but it should be rare iterations and i'm lazy right now.
            $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
            if ($forum->type == 'single') {
                // It's too complicated, skip.
                continue;
            }
            $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

            // Recursively delete post and any children.
            forum_delete_post($post, true, $course, $cm, $forum);
        }
        $rs->close();
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
     * Delete all tags
     */
    private function delete_user_tags() {
        tag_set('user', $this->user->id, array());
    }

    /**
     * Delete user records and mark user as spammer, by doing following:
     * 1. Delete comment, message form this user
     * 2. Update forum post and blog post with spam message
     * 3. Suspend account and set profile description as spammer
     */
    public function set_spammer() {
        global $DB;
        //Make sure deletion should only happen for recently created account
        if ($this->is_active() && $this->is_recentuser()) {
            $transaction = $DB->start_delegated_transaction();
            try {
                $this->delete_user_comments();
                $this->delete_user_forum();
                $this->delete_user_messages();
                $this->delete_user_tags();
                $this->set_profile_as_spammer();
                $transaction->allow_commit();
            } catch (Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }
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
        $userdata[] = get_string('countforum', 'block_spam_deletion', (int)$DB->count_records('forum_posts', $params));
        $userdata[] = get_string('countcomment', 'block_spam_deletion', (int)$DB->count_records('comments', $params));
        $userdata[] = get_string('counttags', 'block_spam_deletion', (int)$DB->count_records('tag', $params));
        $htmlstr = html_writer::tag('div class="block_spam_bold block_spam_highlight"', get_string('totalcount', 'block_spam_deletion'));
        $htmlstr .= html_writer::alist($userdata);
        return $htmlstr;
    }

    public function get_user() {
        return $this->user;
    }
}

class forum_post_spam
{

    public $post;
    public $discussion;
    public $forum;
    public $course;
    public $cm;
    public $context;


    public function __construct($postid) {
        global $DB;

        // Get various records.
        if (!$this->post = forum_get_post_full($postid) ) {
            throw new moodle_exception('invalidpostid', 'forum', $postid);
        }

        $this->discussion = $DB->get_record('forum_discussions', array('id' => $this->post->discussion), '*', MUST_EXIST);
        $this->forum = $DB->get_record('forum', array('id' => $this->discussion->forum), '*', MUST_EXIST);
        $this->course = $DB->get_record('course', array('id' => $this->forum->course), '*', MUST_EXIST);
        $this->cm = get_coursemodule_from_instance('forum', $this->forum->id, $this->course->id, false, MUST_EXIST);
        $this->context = context_module::instance($this->cm->id);
    }

    public function post_html() {
        return forum_print_post($this->post, $this->discussion, $this->forum, $this->cm, $this->course, false, false, false, '', '', null, true, null, true);
    }

    private function get_vote_weighting($userid) {
        global $DB;

        $sql = 'SELECT count(id) FROM {forum_posts} WHERE userid = :userid AND created < :yesterday';
        $params = array('userid' => $userid, 'yesterday' => (time() - DAYSECS));
        $postcount = $DB->count_records_sql($sql, $params);

        if ($postcount < 5) {
            // You need to have posted at least 5 times to have your vote count.
            return 0;
        }

        // This is a failsafe, to avoid abuse against established posters.
        $spammerpostcount = $DB->count_records('forum_posts', array('userid' => $this->post->userid));
        if ($spammerpostcount > 50) {
            // We record the spammer vote, but don't allow 'automatic moderation'.
            return 0;
        }

        $weighting = 1;
        // Allow an additional vote weighting for every 50 posts.
        $weighting+= intval($postcount/50);

        return $weighting;
    }

    public function register_vote($userid) {
        global $DB;

        $record = new stdClass();
        $record->spammerid = $this->post->userid;
        $record->voterid = $userid;
        $record->weighting = $this->get_vote_weighting($userid);
        $record->postid = $this->post->id;
        $DB->insert_record('block_spam_deletion_votes', $record);
    }

    public function has_voted($userid) {
        global $DB;

        $params = array('voterid' => $userid, 'postid' => $this->post->id);
        return (bool) $DB->count_records('block_spam_deletion_votes', $params);
    }
}
