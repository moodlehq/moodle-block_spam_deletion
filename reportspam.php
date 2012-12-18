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


$postid = required_param('postid', PARAM_INT);
$confirmvote = optional_param('confirmvote', false, PARAM_BOOL);

$url = new moodle_url('/blocks/spam_deletion/reportspam.php', array('postid'=>$postid));
$PAGE->set_url($url);

$lib = new forum_post_spam($postid);

$PAGE->set_cm($lib->cm, $lib->course, $lib->forum);
$PAGE->set_context($lib->context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('reportpostasspam', 'block_spam_deletion'));
$PAGE->set_heading($lib->course->fullname);

require_login($lib->course, false, $lib->cm);

$returnurl = new moodle_url('/mod/forum/discuss.php', array('d' => $lib->discussion->id));

$coursectx = $PAGE->context->get_course_context();
if (is_enrolled($coursectx)) {
    // Use a more helpful message if not enrolled.
    redirect($returnurl, get_string('youneedtoenrol'));
}

// This is 'abuse' of existing capability.
require_capability('mod/forum:replypost', $PAGE->context);

if ($lib->has_voted($USER->id)) {
    redirect($returnurl, get_string('alreadyreported', 'block_spam_deletion'));
}else if ($confirmvote) {
    require_sesskey();
    $lib->register_vote($USER->id);
    redirect($returnurl, get_string('thanksspamrecorded', 'block_spam_deletion'));
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('reportpostasspam', 'block_spam_deletion'));
    $yesurl = clone $PAGE->url;
    $yesurl->param('confirmvote', '1');
    $continuebutton = new single_button($yesurl, get_string('yes'));
    $cancelbutton = new single_button($returnurl, get_string('no'), 'get');
    echo $OUTPUT->confirm(get_string('confirmspamreportmsg', 'block_spam_deletion'), $continuebutton, $cancelbutton);
    echo $lib->post_html();
}

echo $OUTPUT->footer();
