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

$postid = optional_param('postid', 0, PARAM_INT);
$commentid = optional_param('commentid', 0, PARAM_INT);
$spammerid = optional_param('spammerid', 0, PARAM_INT);

$PAGE->set_url('/blocks/spam_deletion/marknotspam.php');
$PAGE->set_context(context_system::instance());

require_login();
require_capability('block/spam_deletion:viewspamreport', $PAGE->context);
require_sesskey();

if ($postid) {
    $DB->delete_records('block_spam_deletion_votes', array('postid' => $postid));
} else if ($commentid) {
    $DB->delete_records('block_spam_deletion_votes', array('commentid' => $commentid));
} else if ($spammerid) {
    $DB->delete_records('block_spam_deletion_votes', array('spammerid' => $spammerid));
} else {
    print_error('missingparam', 'error', '', 'postid, commentid, spammerid');
}


redirect(new moodle_url('/blocks/spam_deletion/viewvotes.php'));
