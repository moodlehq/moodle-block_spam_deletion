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
 * Strings for component 'block_deletespammer', language 'en'
 *
 * @package   block_spam_deletion
 * @category  spam_deletion
 * @copyright 2012 Rajesh Taneja
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['cannotdelete'] = 'Cannot delete content for this account';
$string['confirmdeletemsg'] = 'Are you sure, you want to mark <strong>{$a}</strong> as spammer? Following user data will be deleted or modified:<br />
    1. User comments and messages will be deleted.<br />
    2. Forum post and blog post will be replaced with spam msg.<br />
    3. Account will be suspended and profile description will be replaced with spam msg.';
$string['confirmdelete'] = 'Delete spammer';
$string['contentremoved'] = 'Content removed by moderator at {$a}';
$string['countmessage'] = 'Messages: {$a}';
$string['countblog'] = 'Blog posts: {$a}';
$string['countforum'] = 'Forum posts: {$a}';
$string['countcomment'] = 'Comments: {$a}';
$string['deletebutton'] = 'Delete spammer';
$string['notrecentlyaccessed'] = 'The first access date of this account is more than 1 month ago and so the content from this user cannot be deleted.';
$string['pluginname'] = 'Spam deletion';
$string['spam_deletion:spamdelete'] = 'Delete Spam';
$string['spamdescription'] = 'Spammer - spam deleted and account blocked {$a}';
$string['totalcount'] = 'Total records';