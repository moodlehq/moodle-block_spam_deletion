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
$string['alreadyreported'] = 'You\'ve already reported this content as spam.';
$string['badwords'] = 'diet,viagra,football,soccer,live,match';
$string['badwordslist'] = 'Custom spam words list';
$string['badwordslistdesc'] = 'A comma separated list of words to use to identify spam.';
$string['cannotdelete'] = 'Cannot delete content for this suspended account.';
$string['confirmdeletemsg'] = 'Are you sure, you want to mark <strong>{$a->firstname} {$a->lastname} ({$a->username})</strong> as spammer? Data belonging to this user will be blanked out or removed.';
$string['confirmdelete'] = 'Delete spammer';
$string['confirmspamreportmsg'] = 'Are you sure you wish to report this content as spam?';
$string['countmessageunread'] = 'Unread messages: {$a}';
$string['countmessageread'] = 'Read messages: {$a}';
$string['countforum'] = 'Forum posts: {$a}';
$string['countcomment'] = 'Comments: {$a}';
$string['counttags'] = 'Unique tags: {$a}';
$string['deletebutton'] = 'Delete spammer';
$string['eventspammerdeleted'] = 'Spammer deleted';
$string['notrecentlyaccessed'] = 'Beware! The first access date of this account is more than 1 month ago. Make double sure it is really a spammer.';
$string['messageprovider:spamreport'] = 'Spam report';
$string['messageblocked'] = 'Your post has been blocked, as our spam prevention system has flagged it as possibly containing spam. If this is not the case, please see \'My post has been incorrectly flagged as containing spam\' in <a href="http://docs.moodle.org/en/Moodle.org_FAQ#My_post_has_been_incorrectly_flagged_as_containing_spam">http://docs.moodle.org/en/Moodle.org_FAQ</a>. Your message is below if you need to copy and paste it.';
$string['messageblockedtitle'] = 'Potential spam detected!';
$string['pluginname'] = 'Spam deletion';
$string['reportasspam'] = 'Report as spam';
$string['reportcontentasspam'] = 'Report content as spam';
$string['spamreportmessage'] = '{$a->spammer} may be a spammer.
View spam reports at {$a->url}';
$string['spamreportmessagetitle'] = '{$a->spammer} may be a spammer.';
$string['spam_deletion:addinstance'] = 'Add delete spammer block';
$string['spam_deletion:spamdelete'] = 'Delete Spam';
$string['spam_deletion:viewspamreport'] = 'View spam reports';
$string['spamdescription'] = 'Spammer - spam deleted and account blocked {$a}';
$string['spamreports'] = 'Spam reports: {$a}';
$string['thanksspamrecorded'] = 'Thanks, your spam report has been recorded.';
$string['totalcount'] = 'Total records';
