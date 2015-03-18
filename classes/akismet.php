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
 * akismet helper class
 *
 * @package    block_spam_deletion
 * @copyright  2015 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_spam_deletion;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/filelib.php');

use curl;

class akismet {

    const VERIFY_KEY_URL = 'https://rest.akismet.com/1.1/verify-key';
    protected $commentverifyurl = "https://{KEY_PLACEHOLDER}.rest.akismet.com/1.1/comment-check";
    protected $submitspamurl = "https://{KEY_PLACEHOLDER}.rest.akismet.com/1.1/submit-spam";
    protected $testingstr = '';
    protected $apikey = '';
    protected $apikeyvalid = false;

    /**
     * Constructor.
     *
     * @param string $apikey to access akismet
     */
    public function __construct($apikey) {
        global $CFG;

        if ($CFG->debugdeveloper) {
            $this->testingstr = '&is_test=1';
        }
        $this->apikey = $apikey;
        $this->commentverifyurl = str_replace('{KEY_PLACEHOLDER}', $this->apikey, $this->commentverifyurl);
        $this->submitspamurl = str_replace('{KEY_PLACEHOLDER}', $this->apikey, $this->submitspamurl);
        $this->validate_key();
    }

    /**
     * Validates if API key is valid for site.
     */
    protected function validate_key() {
        global $CFG;

        $curl = new curl();
        $params = "key=".urlencode($this->apikey)."&blog=".urlencode($CFG->wwwroot).$this->testingstr;
        $response = $curl->post(self::VERIFY_KEY_URL, $params);
        if ($curl->errno == 0) {
            if ($response === 'valid') {
                $this->apikeyvalid = true;
            }
        }
    }

    /**
     * Determines if posted content is SPAM.
     * NOTE: This must be called by the user who posted the content, it
     * uses $USER.
     *
     * @param text $postedcontent The text content the user posted.
     * @param string $lang the language code of the content
     * @return bool true if akismet thinks its spam.
     */
    public function is_user_posting_spam($postedcontent, $lang) {
        global $USER, $CFG, $COURSE;

        if (!$this->apikeyvalid) {
            // Naughty use of error_log..
            error_log('block_spam_deletion: akami api key not valid. Could not check post content');
            return false;
        }

        $referrer = '';
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        }
        $ua = '';
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        $params = 'blog='.urlencode($CFG->wwwroot).
                '&user_ip='.urlencode(getremoteaddr()).
                '&user_agent='.urlencode($ua).
                '&referrer='.urlencode($referrer).
                '&comment_type=comment'.
                '&comment_author='.urlencode(fullname($USER)).
                '&comment_author_email='.urlencode($USER->email).
                '&comment_author_url='.urlencode($USER->url).
                '&comment_content='.urlencode($postedcontent).
                '&blog_lang='.urlencode($lang).
                '&blog_charset=UTF-8'.
                $this->testingstr;

        $curl = new curl();
        $response = $curl->post($this->commentverifyurl, $params);
        if ($curl->errno == 0) {
            if ($response === 'true') {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports SPAM which hasn't been detected by akismet
     *
     * @param stdClass $record from block_spam_deletion_akismet table
     * @return bool true if akismet acknowleded the spam report.
     */
    public function report_missed_spam($record) {
        global $USER, $CFG;

        if (!$this->apikeyvalid) {
            // Naughty use of error_log..
            error_log('block_spam_deletion: akami api key not valid. Could not report missed spam');
            return false;
        }

        $params = 'blog='.urlencode($CFG->wwwroot).
                '&user_ip='.urlencode($record->user_ip).
                '&user_agent='.urlencode($record->user_agent).
                '&comment_type=comment'.
                '&comment_author='.urlencode($record->comment_author).
                '&comment_author_email='.urlencode($record->comment_author_email).
                '&comment_content='.urlencode($record->comment_content).
                '&blog_charset=UTF-8'.
                $this->testingstr;

        $curl = new curl();
        $response = $curl->post($this->submitspamurl, $params);
        if ($curl->info['http_code'] == 200) {
            return true;
        }

        return false;
    }

}


