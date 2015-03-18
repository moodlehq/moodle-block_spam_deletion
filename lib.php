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
require_once($CFG->libdir .'/tablelib.php');
require_once($CFG->dirroot . '/comment/lib.php');

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
        \core\session\manager::kill_user_sessions($this->user->id);
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
            $discussion = $DB->get_record('forum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
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
     * Delete any spam reports from this block..
     */
    private function delete_spam_votes() {
        global $DB;
        $DB->delete_records('block_spam_deletion_votes', array('spammerid' => $this->user->id));
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
        if ($this->is_active()) {
            $transaction = $DB->start_delegated_transaction();
            try {
                $this->delete_user_comments();
                $this->delete_user_forum();
                $this->delete_user_messages();
                $this->delete_user_tags();
                $this->set_profile_as_spammer();
                $this->delete_spam_votes();
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
        $userdata[] = get_string('countmessageunread', 'block_spam_deletion', (int)$DB->count_records('message', array('useridfrom' => $this->user->id)));
        $userdata[] = get_string('countmessageread', 'block_spam_deletion', (int)$DB->count_records('message_read', array('useridfrom' => $this->user->id)));
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

abstract class spam_report
{

    public $course;
    public $cm;
    public $context;

    abstract public function register_vote($userid);
    abstract public function has_voted($userid);
    abstract public function has_permission();
    abstract public function content_html();
    abstract public function return_url();

    protected function get_vote_weighting($userid) {
        // TODO: No yet implemented, will be looked at if we ever decide to
        // take automatic action based on the weights. A possible metric might
        // be the number of positive spam reports by the user in the past, or
        // so.
        return 1;
    }

    public static function notify_spam($spammerid, $voterid) {
        global $DB, $CFG;

        $spammer = $DB->get_record('user', array('id' => $spammerid));
        $voter = $DB->get_record('user', array('id' => $voterid));

        $a = new stdClass;
        $a->spammer = fullname($spammer);
        $a->url = $CFG->wwwroot.'/blocks/spam_deletion/viewvotes.php';
        $message = get_string('spamreportmessage', 'block_spam_deletion', $a);
        $title = get_string('spamreportmessagetitle', 'block_spam_deletion', $a);

        $eventdata = new stdClass();
        $eventdata->component         = 'block_spam_deletion';
        $eventdata->name              = 'spamreport';
        $eventdata->userfrom          = $voter;
        $eventdata->subject           = $title;
        $eventdata->fullmessage       = $message;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = $message;
        $eventdata->smallmessage      = $message;
        $eventdata->notification      = 1;

        $moderators = get_users_by_capability(context_system::instance(), 'block/spam_deletion:viewspamreport');
        foreach ($moderators as $m) {
            $eventdata->userto = $m;
            message_send($eventdata);
        }
    }
}

class forum_post_spam extends spam_report
{

    public $post;
    public $discussion;
    public $forum;

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

    public function return_url() {
        return new moodle_url('/mod/forum/discuss.php', array('d' => $this->discussion->id));
    }

    public function has_permission() {
        return has_capability('mod/forum:replypost', $this->context);
    }

    public function register_vote($userid) {
        global $DB;

        $record = new stdClass();
        $record->spammerid = $this->post->userid;
        $record->voterid = $userid;
        $record->weighting = $this->get_vote_weighting($userid);
        $record->postid = $this->post->id;
        $DB->insert_record('block_spam_deletion_votes', $record);

        $this->populate_akismet_spam_report();

        // Send message to notifiers.
        $this->notify_spam($record->spammerid, $record->voterid);
    }

    public function has_voted($userid) {
        global $DB;

        $params = array('voterid' => $userid, 'postid' => $this->post->id);
        return (bool) $DB->count_records('block_spam_deletion_votes', $params);
    }

    public function content_html() {
        // Wrap te post in a div with the appropriate class so forum post CSS works.
        $s = html_writer::start_tag('div', array('class' => 'path-mod-forum'));
        $s .= forum_print_post($this->post, $this->discussion, $this->forum, $this->cm, $this->course, false, false, false, '', '', null, true, null, true);
        $s .= html_writer::end_tag('div');
        return $s;
    }

    /**
     * Build up the data needed to submit a spam report to akismet and populate
     * our spam report queue with it.
     *
     * @return int id of akismet spam report.
     */
    public function populate_akismet_spam_report() {
        global $DB;

        $record = array();
        $record['original_id'] = $this->post->id;
        $record['is_spam'] = '1';

        if ($akismetrecord = $DB->get_record('block_spam_deletion_akismet', $record, 'id')) {
            // Already in the queue, dont add again.
            return $akismetrecord->id;
        }

        $spammer = $DB->get_record('user', array('id' => $this->post->userid));
        $postip = $this->get_ip_post_created_from();

        $record['user_ip'] = $postip ? $postip : $spammer->lastip;
        $record['user_agent'] = ''; // No chance, we dont log this.
        $record['comment_author'] = fullname($spammer);
        $record['comment_author_email'] = $spammer->email;
        $record['comment_author_url'] = $spammer->url;
        $record['comment_content'] = $this->post->subject."\n".$this->post->message;

        return $DB->insert_record('block_spam_deletion_akismet', $record);
    }

    /**
     * Attempts to get the original ip which a forum post was posted with.
     *
     * NOTE: This depends on the standard log store table being enabled
     * which is pretty hacky..
     * @return string|bool ip if found, false if not
     */
    protected function get_ip_post_created_from() {
        global $DB;
        $params = array();
        // NOTE: these fields are chosen carefuly to utilise existing index.
        $params['userid'] = $this->post->userid;
        $params['contextlevel'] = $this->context->contextlevel;
        $params['contextinstanceid'] = $this->context->instanceid;
        $params['crud'] = 'c';
        $params['objectid'] = $this->post->id;
        $params['eventname'] = '\mod_forum\event\post_created';
        return $DB->get_field('logstore_standard_log', 'ip', $params);
    }

}

class comment_spam extends spam_report
{

    private $comment;
    private $commentlib;

    public function __construct($commentid) {
        global $DB;
        $this->comment = $DB->get_record('comments', array('id' => $commentid), '*', MUST_EXIST);
        list($this->context, $this->course, $this->cm) = get_context_info_array($this->comment->contextid);

        if (empty($this->course) and $this->context->id == SYSCONTEXTID) {
            $this->course = get_site();
        }

        $options = new stdClass;
        $options->context = $this->context;
        $options->component = self::get_component_from_commentarea($this->comment->commentarea);
        $options->area = $this->comment->commentarea;
        $options->itemid = $this->comment->itemid;
        $options->cm = $this->cm;
        $options->course = $this->course;

        $this->commentlib = new comment($options);
    }

    public function return_url() {
        return $this->context->get_url();
    }

    public function has_permission() {
        return $this->commentlib->can_post();
    }

    public function register_vote($userid) {
        global $DB;

        $record = new stdClass();
        $record->spammerid = $this->comment->userid;
        $record->voterid = $userid;
        $record->weighting = $this->get_vote_weighting($userid);
        $record->commentid = $this->comment->id;
        $DB->insert_record('block_spam_deletion_votes', $record);

        // Send message to notifiers.
        $this->notify_spam($record->spammerid, $record->voterid);
    }

    public function has_voted($userid) {
        global $DB;

        $params = array('voterid' => $userid, 'commentid' => $this->comment->id);
        return (bool) $DB->count_records('block_spam_deletion_votes', $params);
    }

    public function content_html() {
        global $OUTPUT;

        // This is to get the correctly formated data...
        $comments = $this->commentlib->get_comments();
        $commentid = $this->comment->id;

        $filtered = array_filter($comments, function ($var) use ($commentid) {
            if ($var->id == $commentid) {
                return $var;
            }
        });

        $o = $OUTPUT->box_start('generalbox center');
        $o.= $this->commentlib->print_comment(array_shift($filtered));
        $o.= $OUTPUT->box_end();

        return $o;
    }

    private static function get_component_from_commentarea($commentarea) {
        $component = null;
        // TODO: this is an ugly hack because of MDL-37243, we don't store
        // the component of a comment in the database, so have to 'guess' the component.
        // Note that MDL-27548 has been fixed for 2.9 so we will want to
        // address that in a future version of this block.
        switch ($commentarea) {
        case 'plugin_general':
            $component = 'local_plugins';
            break;
        case 'page_comments':
            $component = 'block_comments';
            break;
        case 'database_entry':
            $component = 'data';
            break;
        case 'format_blog':
            $component = 'blog';
            break;
        case 'glossary_entry':
            $component = 'glossary';
            break;
        case 'wiki_page':
            $component = 'wiki';
            break;
        case 'amos_contribution':
            $component = 'local_amos';
            break;
        default:
            throw new moodle_exception('unknowncomponent', 'block_spam_deletion', '', $commentarea);
        }

        return $component;
    }


}

class spam_report_table extends table_sql
{

    public function col_spammer($row) {
        return html_writer::link(new moodle_url('/user/profile.php', array('id' => $row->spammerid)), fullname($row));
    }

    public function print_nothing_to_display() {
        echo 'No spam reports :)';
    }

}

class forum_spam_report_table extends spam_report_table
{

    private $query = '';
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $namefields = get_all_user_name_fields(true, 'u');
        $this->query = "SELECT v.postid, f.subject, f.message, f.discussion, v.spammerid,
            SUM(v.weighting) AS votes, COUNT(v.voterid) AS votecount, $namefields
            FROM {block_spam_deletion_votes} v
            JOIN {forum_posts} f ON f.id = v.postid
            JOIN {user} u ON u.id = v.spammerid
            WHERE v.postid IS NOT NULL
            GROUP BY v.spammerid, v.postid, f.subject, f.message, f.discussion, $namefields
            ORDER BY votes DESC, votecount DESC;";

        $this->define_columns(array('postlink', 'message', 'spammer', 'votes', 'votecount', 'actions'));
        $this->define_headers(array('Forum Post', 'Message', 'User', 'SPAM Score', 'Voters (weighting)', 'Actions'));
        $this->collapsible(false);
        $this->sortable(false);
    }

    public function query_db($pagesize, $useinitialsbar=true) {
        global $DB;
        $this->rawdata = $DB->get_records_sql($this->query, array(), $this->get_page_start(), $this->get_page_size());
    }

    public function col_votecount($row) {
        global $DB;

        $namefields = get_all_user_name_fields(true, 'u');
        $votersql = "SELECT u.id, v.weighting, $namefields
            FROM {block_spam_deletion_votes} v
            JOIN {user} u ON u.id = v.voterid
            WHERE v.postid = ?
            ORDER BY $namefields";
        $rs = $DB->get_recordset_sql($votersql, array($row->postid));
        $voters = array();
        foreach ($rs as $v) {
            $voters[] = html_writer::link(new moodle_url('/user/profile.php', array('id' => $v->id)), fullname($v))." ({$v->weighting})";
        }
        $rs->close();

        return implode('<br />', $voters);
    }

    public function col_postlink($row) {
        $postlink = new moodle_url('/mod/forum/discuss.php', array('d' => $row->discussion));
        $postlink->set_anchor('p'.$row->postid);
        return html_writer::link($postlink, format_text($row->subject));
    }

    public function col_actions($row) {
        global $OUTPUT;
        $notspamurl = new moodle_url('/blocks/spam_deletion/marknotspam.php', array('postid' => $row->postid));
        return $OUTPUT->single_button($notspamurl, 'Mark not spam');
    }
}

class forum_deleted_spam_report_table extends spam_report_table
{

    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $namefields = get_all_user_name_fields(true, 'u');
        $this->query = "SELECT v.postid, v.spammerid, SUM(v.weighting) AS votes,
                     COUNT(v.voterid) AS votecount, $namefields
                     FROM {block_spam_deletion_votes} v
                     JOIN {user} u ON u.id = v.spammerid
                     LEFT OUTER JOIN {forum_posts} f ON f.id = v.postid
                     WHERE f.id IS NULL AND v.postid IS NOT NULL
                     GROUP BY v.spammerid, v.postid, $namefields
                     ORDER BY votes DESC, votecount DESC";
        $this->define_columns(array('spammer', 'votes', 'votecount', 'actions'));
        $this->define_headers(array('User', 'SPAM Score', 'Voters (weighting)', 'Actions'));
        $this->collapsible(false);
        $this->sortable(false);
    }

    public function query_db($pagesize, $useinitialsbar=true) {
        global $DB;
        $this->rawdata = $DB->get_records_sql($this->query, array(), $this->get_page_start(), $this->get_page_size());
    }

    public function col_votecount($row) {
        global $DB;

        $namefields = get_all_user_name_fields(true, 'u');
        $votersql = "SELECT u.id, v.weighting, $namefields
            FROM {block_spam_deletion_votes} v
            JOIN {user} u ON u.id = v.voterid
            WHERE v.postid = ?
            ORDER BY $namefields";
        $rs = $DB->get_recordset_sql($votersql, array($row->postid));
        $voters = array();
        foreach ($rs as $v) {
            $voters[] = html_writer::link(new moodle_url('/user/profile.php', array('id' => $v->id)), fullname($v))." ({$v->weighting})";
        }
        $rs->close();

        return implode('<br />', $voters);
    }

    public function col_actions($row) {
        global $OUTPUT;
        $notspamurl = new moodle_url('/blocks/spam_deletion/marknotspam.php', array('postid' => $row->postid));
        return $OUTPUT->single_button($notspamurl, 'Remove spam report');
    }
}

class comment_spam_report_table extends spam_report_table
{

    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        $namefields = get_all_user_name_fields(true, 'u');
        $this->query = "SELECT v.commentid, c.content, v.spammerid, c.contextid,
            SUM(v.weighting) AS votes, COUNT(v.voterid) AS votecount,
            $namefields
            FROM {block_spam_deletion_votes} v
            JOIN {comments} c ON c.id = v.commentid
            JOIN {user} u ON u.id = v.spammerid
            WHERE v.commentid IS NOT NULL
            GROUP BY v.commentid, c.content, v.spammerid, c.contextid, $namefields
            ORDER BY votes DESC, votecount DESC";

        $this->define_columns(array('postlink', 'content', 'spammer', 'votes', 'votecount', 'actions'));
        $this->define_headers(array('Comment Location', 'Content', 'User', 'SPAM Score', 'Voters (weighting)', 'Actions'));
        $this->collapsible(false);
        $this->sortable(false);
    }

    public function query_db($pagesize, $useinitialsbar=true) {
        global $DB;
        $this->rawdata = $DB->get_records_sql($this->query, array(), $this->get_page_start(), $this->get_page_size());
    }

    public function col_votecount($row) {
        global $DB;

        $namefields = get_all_user_name_fields(true, 'u');
        $votersql = "SELECT u.id, v.weighting, $namefields
            FROM {block_spam_deletion_votes} v
            JOIN {user} u ON u.id = v.voterid
            WHERE v.commentid = ?
            ORDER BY $namefields";
        $rs = $DB->get_recordset_sql($votersql, array($row->commentid));
        $voters = array();
        foreach ($rs as $v) {
            $voters[] = html_writer::link(new moodle_url('/user/profile.php', array('id' => $v->id)), fullname($v))." ({$v->weighting})";
        }
        $rs->close();

        return implode('<br />', $voters);
    }

    public function col_postlink($row) {
        $context = context::instance_by_id($row->contextid);

        return html_writer::link($context->get_url(), $context->get_context_name());
    }

    public function col_actions($row) {
        global $OUTPUT;
        $notspamurl = new moodle_url('/blocks/spam_deletion/marknotspam.php', array('commentid' => $row->commentid));
        return $OUTPUT->single_button($notspamurl, 'Mark not spam');
    }
}

class comment_deleted_spam_report_table extends spam_report_table
{
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $namefields = get_all_user_name_fields(true, 'u');
        $this->query = "SELECT v.commentid, v.spammerid,
            SUM(v.weighting) AS votes, COUNT(v.voterid) AS votecount,
            $namefields
            FROM {block_spam_deletion_votes} v
            JOIN {user} u ON u.id = v.spammerid
            LEFT OUTER JOIN {comments} f ON f.id = v.commentid
            WHERE f.id IS NULL AND v.commentid IS NOT NULL
            GROUP BY v.commentid, v.spammerid, $namefields
            ORDER BY votes DESC, votecount DESC";
        $this->define_columns(array('spammer', 'votes', 'votecount', 'actions'));
        $this->define_headers(array('User', 'SPAM Score', 'Voters (weighting)', 'Actions'));
        $this->collapsible(false);
        $this->sortable(false);
    }

    public function col_votecount($row) {
        global $DB;

        $namefields = get_all_user_name_fields(true, 'u');
        $votersql = "SELECT u.id, v.weighting, $namefields
            FROM {block_spam_deletion_votes} v
            JOIN {user} u ON u.id = v.voterid
            WHERE v.commentid = ?
            ORDER BY $namefields";
        $rs = $DB->get_recordset_sql($votersql, array($row->commentid));
        $voters = array();
        foreach ($rs as $v) {
            $voters[] = html_writer::link(new moodle_url('/user/profile.php', array('id' => $v->id)), fullname($v))." ({$v->weighting})";
        }
        $rs->close();

        return implode('<br />', $voters);
    }

    public function query_db($pagesize, $useinitialsbar=true) {
        global $DB;
        $this->rawdata = $DB->get_records_sql($this->query, array(), $this->get_page_start(), $this->get_page_size());
    }

    public function col_actions($row) {
        global $OUTPUT;
        $notspamurl = new moodle_url('/blocks/spam_deletion/marknotspam.php', array('commentid' => $row->commentid));
        return $OUTPUT->single_button($notspamurl, 'Remove spam report');
    }
}

class user_profile_spam_table extends spam_report_table
{

    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $namefields = get_all_user_name_fields(true, 'u');
        $this->query = "SELECT v.spammerid, u.description, SUM(v.weighting) AS votes,
            COUNT(v.voterid) AS votecount, $namefields
            FROM {block_spam_deletion_votes} v
            JOIN {user} u ON u.id = v.spammerid
            WHERE v.postid IS NULL AND v.commentid IS NULL
            GROUP BY v.spammerid, u.description, $namefields
            ORDER BY votes DESC, votecount DESC";


        $this->define_columns(array('spammer', 'description', 'votecount', 'actions'));
        $this->define_headers(array('User', 'Profile description', 'Votes', 'Actions'));
        $this->collapsible(false);
        $this->sortable(false);
    }

    public function col_votecount($row) {
        global $DB;

        $namefields = get_all_user_name_fields(true, 'u');

        $votersql = "SELECT v.id, u.id AS userid, v.weighting, $namefields
            FROM {block_spam_deletion_votes} v
            LEFT OUTER JOIN {user} u ON u.id = v.voterid
            WHERE v.postid IS NULL AND v.commentid IS NULL AND
            v.spammerid = ?
            ORDER BY $namefields";
        $rs = $DB->get_recordset_sql($votersql, array($row->spammerid));
        $voters = array();
        foreach ($rs as $v) {
            if (empty($v->userid)) {
                $voters[] = "SYSTEM ({$v->weighting})";
            } else {
                $voters[] = html_writer::link(new moodle_url('/user/profile.php', array('id' => $v->id)), fullname($v))." ({$v->weighting})";
            }
        }
        $rs->close();

        return implode('<br />', $voters);
    }

    public function query_db($pagesize, $useinitialsbar=true) {
        global $DB;
        $this->rawdata = $DB->get_records_sql($this->query, array(), $this->get_page_start(), $this->get_page_size());
    }

    public function col_actions($row) {
        global $OUTPUT;
        $notspamurl = new moodle_url('/blocks/spam_deletion/marknotspam.php', array('spammerid' => $row->spammerid));
        return $OUTPUT->single_button($notspamurl, 'Remove spam report');
    }
}

class akismet_table extends table_sql
{

    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $this->set_sql('id, comment_author, comment_content', '{block_spam_deletion_akismet}', 'is_spam = ?', array('1'));

        $this->define_columns(array('comment_author', 'comment_content', 'actions'));
        $this->define_headers(array('Users Name', 'Spam content', 'Actions'));
        $this->collapsible(false);
        $this->sortable(false);
    }

    public function col_actions($row) {
        global $OUTPUT;
        $reporturl = new moodle_url('/blocks/spam_deletion/improveakismet.php', array('id' => $row->id));
        $ignoreurl = clone $reporturl;
        $ignoreurl->param('ignore', true);

        return $OUTPUT->single_button($reporturl, 'Report to akismet') .
            $OUTPUT->single_button($ignoreurl, 'Ignore (not spam)');
    }

    public function print_nothing_to_display() {
        echo 'Nothing to send to akismet';
    }

}
