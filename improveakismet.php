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

$reportid = required_param('id', PARAM_INT);
$ignore = optional_param('ignore', false, PARAM_BOOL);

$PAGE->set_url('/blocks/spam_deletion/improveakismet.php');
$PAGE->set_context(context_system::instance());

require_login();
require_capability('block/spam_deletion:viewspamreport', $PAGE->context);
require_sesskey();

$report = $DB->get_record('block_spam_deletion_akismet', array('id' => $reportid), '*', MUST_EXIST);

$redirectmessage = '';

if ($ignore) {
    $DB->delete_records('block_spam_deletion_akismet', array('id' => $reportid));
} else {
    $akismet = new block_spam_deletion\akismet($CFG->block_spam_deletion_akismet_key);
    if ($akismet->report_missed_spam($report)) {
        $DB->delete_records('block_spam_deletion_akismet', array('id' => $reportid));
    } else {
        $redirectmessage = 'Problem when submitting spam to akismet.';
    }
}

redirect(new moodle_url('/blocks/spam_deletion/viewvotes.php'), $redirectmessage);
