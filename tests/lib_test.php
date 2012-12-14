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
 * Unit tests for spam deletion block
 *
 * @package    block_spam_deletion
 * @category   phpunit
 * @copyright  2012 onwards Dan Poltawski
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/spam_deletion/lib.php');


class block_spam_deletion_lib_testcase extends advanced_testcase {
    public function test_invalid_user() {
        $this->setExpectedException('moodle_exception');

        $doesnotexist = new spammerlib(23242342);
    }

    public function test_admin_user() {
        $admin = get_admin();

        $this->setExpectedException('moodle_exception');
        $lib = new spammerlib($admin->id);
    }

    public function test_guest_user() {
        $guest = guest_user();

        $this->setExpectedException('moodle_exception');
        $lib = new spammerlib($guest->id);
    }

    public function test_current_user() {
        global $USER;

        $this->setExpectedException('moodle_exception');
        $lib = new spammerlib($USER->id);
    }

    public function test_old_user() {
        $this->resetAfterTest(true);

        // Set lastaccess to 1 year ago..
        $firstaccess = time() - YEARSECS;
        $u = $this->getDataGenerator()->create_user(array('firstaccess' => $firstaccess));
        $lib = new spammerlib($u->id);

        $this->assertInstanceOf('spammerlib', $lib);

        // Expect exception because can't set an old user as a spammer.
        $this->setExpectedException('moodle_exception');
        $lib->set_spammer();
    }

    public function test_suspended_user() {
        $this->resetAfterTest(true);

        // Create a suspended user.
        $u = $this->getDataGenerator()->create_user(array('suspended' => 1));
        $lib = new spammerlib($u->id);

        $this->assertInstanceOf('spammerlib', $lib);

        // Expect exception because can't set an old user as a spammer.
        $this->setExpectedException('moodle_exception');
        $lib->set_spammer();
    }

    public function test_normal() {
        $this->resetAfterTest(true);

        $normaluser = $this->getDataGenerator()->create_user();
        $one = new spammerlib($normaluser->id);
    }

}
