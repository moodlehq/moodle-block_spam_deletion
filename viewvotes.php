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

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/spam_deletion/lib.php');
require_once($CFG->libdir .'/tablelib.php');

$PAGE->set_url('/blocks/spam_deletion/viewvotes.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('View Votes');
$PAGE->set_heading('View votes');

require_login();
require_capability('block/spam_deletion:viewspamreport', $PAGE->context);

$sql = 'SELECT v.postid, f.subject, f.message, f.discussion,
        v.spammerid, u.firstname, u.lastname,
        SUM(v.weighting) AS votes, COUNT(v.voterid) AS votecount
        FROM {block_spam_deletion_votes} v
        JOIN {forum_posts} f ON f.id = v.postid
        JOIN {user} u ON u.id = v.spammerid
        GROUP BY v.spammerid, v.postid, f.subject, f.message, f.discussion, u.firstname, u.lastname
        ORDER BY votes DESC, votecount DESC';
$rs = $DB->get_recordset_sql($sql);

$table = new html_table();
$table->head  = array('Forum Post', 'Message', 'Post Author', 'SPAM Score', 'Voters (weighting)');
$table->data  = array();
foreach ($rs as $r) {
    $votersql = 'SELECT u.id, u.firstname, u.lastname, v.weighting
        FROM {block_spam_deletion_votes} v
        JOIN {user} u ON u.id = v.voterid
        WHERE v.postid = ?
        ORDER BY u.firstname, u.lastname';
    $voterrecords = $DB->get_records_sql($votersql, array($r->postid));
    $voters = array();
    foreach ($voterrecords as $v) {
        $voters[] = fullname($v)." ({$v->weighting})";
    }

    $postlink = new moodle_url('/mod/forum/discuss.php', array('d' => $r->discussion));
    $postlink->set_anchor('p'.$r->postid);
    $table->data[] = array(
        html_writer::link($postlink, format_text($r->subject)),
        format_text($r->message),
        html_writer::link(new moodle_url('/user/profile.php', array('id' => $r->spammerid)), fullname($r)),
        $r->votes,
        implode('<br />', $voters)
    );
}

$rs->close();

echo $OUTPUT->header();
echo html_writer::table($table);
echo $OUTPUT->footer();
