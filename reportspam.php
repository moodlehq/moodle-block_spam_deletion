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

$postid = optional_param('postid', 0, PARAM_INT);
$commentid = optional_param('commentid', 0, PARAM_INT);

$confirmvote = optional_param('confirmvote', false, PARAM_BOOL);
$deleteuser = optional_param('deleteuser', false, PARAM_BOOL);

$url = new moodle_url('/blocks/spam_deletion/reportspam.php');
if ($postid) {
    $url->param('postid', $postid);
} else if ($commentid) {
    $url->param('commentid', $commentid);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');


if ($postid) {
    $lib = new forum_post_spam($postid);
} else if ($commentid) {
    $lib = new comment_spam($commentid);
} else {
    print_error('missingparam', 'error', '', 'postid, commentid');
}

require_course_login($lib->course, true, $lib->cm);

$PAGE->set_context($lib->context);
$PAGE->set_title(get_string('reportcontentasspam', 'block_spam_deletion'));
$PAGE->set_heading($lib->course->fullname);

$returnurl = $lib->return_url();
$coursectx = $PAGE->context->get_course_context(false);

if (isguestuser()) {
    redirect($returnurl, get_string('youneedtoenrol'));
}

if (!$lib->has_permission()) {
    // Use a more helpful message if not enrolled.
    if ($coursectx && !is_enrolled($coursectx)) {
        redirect($returnurl, get_string('youneedtoenrol'));
    }

    print_error('nopermissions');
}

if ($lib->has_voted($USER->id)) {
    redirect($returnurl, get_string('alreadyreported', 'block_spam_deletion'));
}else if ($confirmvote) {
    require_sesskey();
    $lib->register_vote($USER->id);
    redirect($returnurl, get_string('thanksspamrecorded', 'block_spam_deletion'));
}else if ($deleteuser && !empty($postid)) {
    // Special shortcut for 'admins' deleting - only implemented for forum posts.
    require_sesskey();
    require_capability('block/spam_deletion:spamdelete', context_system::instance());

    // Create akismet spam report.
    $lib->populate_akismet_spam_report();

    $spamlib = new spammerlib($lib->post->userid);
    $spamlib->set_spammer();
    redirect(new moodle_url('/mod/forum/view.php', array('f' => $lib->post->forum)), 'Spammer deleted');
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('reportcontentasspam', 'block_spam_deletion'));
    $yesurl = clone $PAGE->url;
    $yesurl->param('confirmvote', '1');
    $continuebutton = new single_button($yesurl, get_string('yes'));
    $cancelbutton = new single_button($returnurl, get_string('no'), 'get');
    echo $OUTPUT->confirm(get_string('confirmspamreportmsg', 'block_spam_deletion'), $continuebutton, $cancelbutton);
    if (!empty($postid) && has_capability('block/spam_deletion:spamdelete', context_system::instance())) {
        // Special shortcut for 'admins' deleting - only implemented for forum posts.
        $deleteurl = clone $PAGE->url;
        $deleteurl->param('deleteuser', true);
        echo $OUTPUT->single_button($deleteurl, get_string('deleteandreporttoakismet', 'block_spam_deletion'), 'post', array('class' => 'mdl-align'));
    }
    echo $lib->content_html();
}
echo $OUTPUT->footer();
