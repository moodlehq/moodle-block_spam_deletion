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

$PAGE->set_url('/blocks/spam_deletion/viewvotes.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('View Votes');
$PAGE->set_heading('View votes');

require_login();
require_capability('block/spam_deletion:viewspamreport', $PAGE->context);

$table = new spam_report_table('block-spam-deltion-viewspam');
$table->define_baseurl($PAGE->url);

$deletedtable = new spam_report_post_deleted_table('block-spam-deltion-deleted');
$deletedtable->define_baseurl($PAGE->url);

echo $OUTPUT->header();
echo $OUTPUT->heading('Forum post spam reports');
echo $table->out(50, true);

echo $OUTPUT->heading('Spam reports about forum posts which have been deleted');
echo $deletedtable->out(50, true);
echo $OUTPUT->footer();
