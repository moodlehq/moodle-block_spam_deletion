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
$PAGE->set_title('Spam reports');
$PAGE->set_heading('Spam reports');

require_login();
require_capability('block/spam_deletion:viewspamreport', $PAGE->context);

echo $OUTPUT->header();

echo $OUTPUT->heading('Forum post spam reports');
$ft= new forum_spam_report_table('1');
$ft->define_baseurl($PAGE->url);
echo $ft->out(50, true);

echo $OUTPUT->heading('Improve akismet filtering');
$akismettable = new akismet_table('1');
$akismettable->define_baseurl($PAGE->url);
echo $akismettable->out(50, true);

echo $OUTPUT->heading('Forum post spam reports [deleted]');
$dft = new forum_deleted_spam_report_table('2');
$dft ->define_baseurl($PAGE->url);
echo $dft->out(50, true);

echo $OUTPUT->heading('Comments spam reports');
$ct = new comment_spam_report_table('3');
$ct->define_baseurl($PAGE->url);
echo $ct->out(50, true);

echo $OUTPUT->heading('Comments spam reports [deleted]');
$dct = new comment_deleted_spam_report_table('4');
$dct->define_baseurl($PAGE->url);
echo $dct->out(50, true);

echo $OUTPUT->heading('User profile spam reports');
$st = new user_profile_spam_table('5');
$st->define_baseurl($PAGE->url);
echo $st->out(50, true);

echo $OUTPUT->footer();
