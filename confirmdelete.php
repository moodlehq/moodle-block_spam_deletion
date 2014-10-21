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
 * Show block on user profile and allow admin to delete all contents for that
 * user, makinging user inactive and update profile with spammer.
 *
 * @package    block_spam_deletion
 * @copyright  2012 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/spam_deletion/lib.php');

/** @var int user id for spammer.  */
$userid = required_param('userid', PARAM_INT);
/** @var bool spammer delete confirmation */
$confirmdelete = optional_param('confirmdelete', 0, PARAM_BOOL);

// Set page before processing.
$url = new moodle_url('/blocks/spam_deletion/confirmdelete.php');
$url->param('id', $userid);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('confirmdelete', 'block_spam_deletion'));
$PAGE->set_heading(get_string('confirmdelete', 'block_spam_deletion'));

// Make sure user has enough capability to process deletion.
require_login();
require_sesskey();
require_capability('block/spam_deletion:spamdelete', $PAGE->context);

/**
 * @var moodle_url Return url for profile
 */
$returnurl = new moodle_url('/user/profile.php', array('id' => $userid));

// Get spammer information
$spamlib = new spammerlib($userid);

// Process spammer deletion request.
if ($confirmdelete) {
    $spamlib->set_spammer();
    $user = $spamlib->get_user();
    $event = \block_spam_deletion\event\spammer_deleted::create(
            array(
                'objectid' => $user->id,
                'relateduserid' => $user->id,
                'context' => context_system::instance(),
                'other' => array(
                    'username' => $user->username,
                    'email' => $user->email
                )
            )
        );
    $event->add_record_snapshot('user', $user);
    $event->trigger();
    redirect($returnurl);
} else {
    echo $OUTPUT->header();
    $urlyes = new moodle_url('/blocks/spam_deletion/confirmdelete.php', array('userid' => $userid, 'confirmdelete' => '1'));
    $continuebutton = new single_button($urlyes, get_string('yes'));
    $cancelbutton = new single_button($returnurl, get_string('no'), 'get');
    echo $OUTPUT->confirm(get_string('confirmdeletemsg', 'block_spam_deletion', $spamlib->get_user()), $continuebutton, $cancelbutton);
    echo $OUTPUT->footer();
}
