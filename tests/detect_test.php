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
 * Unit tests for the forum spam detection hook.
 *
 * @package     block_spam_deletion
 * @category    phpunit
 * @copyright   2013 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/spam_deletion/detect.php');

class block_spam_deletion_detect_testcase extends basic_testcase {

    public function test_message_is_spammy() {
        global $CFG;

        // Lipsum ham.
        $this->assertFalse(block_spam_deletion_message_is_spammy(
            '<h1>Lorem ipsum</h1> <p>Dolor sit amet, consectetur adipiscing elit.
            Phasellus aliquam mauris nisi, non rhoncus elit dignissim at.</p>'));

        // Two links are allowed.
        $this->assertFalse(block_spam_deletion_message_is_spammy(
            'This is <a href="http://casino.com/">still</a> valid as long as there are not
            too <a href=http://join.us/>links</a>.'));

        // Three makes a company!
        $this->assertTrue(block_spam_deletion_message_is_spammy(
            'This is <a title="Click me!" href="Http://casino.com/">not</a> any more because <a href="
            https://cheappills.com">there</a> are <img src="https://my.site/logo.png"> three links.'));

        // Embedded media are allowed.
        $this->assertFalse(block_spam_deletion_message_is_spammy(
            '<p>Hello! <a title="My site" href="http://my.moodle.site/">My Moodle</a> is running at
            <a href="https://download.moodle.org/download.php/stable25/moodle-latest-25.tgz">Moodle 2.5</a>
            and I have a problem with an error - see the screenshot:</p>
            <p><img alt="Screenshot" src="'.$CFG->httpswwwroot.'/draftfile.php/78/user/draft/53620397/shot.png" /></p>'));

        // Links to the site itself are allowed.
        $this->assertFalse(block_spam_deletion_message_is_spammy(
            'I have looked at other <a href="discuss.php?d=12345">thread</a> and also the one at
            <a href="'.$CFG->wwwroot.'/mod/forum/discuss.php?d=54321#p123">another forum</a> that is
            also mentioned <a href="'.$CFG->httpswwwroot.'/mod/forum/discuss.php?d=98765">here</a>. None
            of the solution explains why <a title="My site" href="http://my.moodle.si.te/">my site</a>
            running at http://my.moodle.si.te is vulnerable to this problem.'));

        // Just in case the spammer prefers Markdown or plaintext.
        $this->assertTrue(block_spam_deletion_message_is_spammy(
            'Check [my Moodle site][1] and get [courses for free](http://freemoodlecourses.com/land).
            ![Contact us][2]

            <!--
            [1]: http://viagra.com
            [2]: http://freemoodlecourses.com/logo.jpg
            -->'));

        // Check for spam words.
        $this->assertTrue(block_spam_deletion_message_is_spammy('<p>buy cheap viagra online</p>'));
        // Check case insensitivity.
        $this->assertTrue(block_spam_deletion_message_is_spammy('<p>buy cheap vIaGra online</p>'));
        // Check only whole words match.
        $this->assertTrue(block_spam_deletion_message_is_spammy('<p>watch it live!</p>'));
        $this->assertFalse(block_spam_deletion_message_is_spammy('<p>its alive!</p>'));
        $this->assertFalse(block_spam_deletion_message_is_spammy('<p>its lively!</p>'));
    }
}
