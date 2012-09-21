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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/spam_deletion/lib.php');

/**
 * Spam deletion class
 *
 * Show block on user profile and allow admin to delete all contents for that
 * user, makinging user inactive and update profile with spammer.
 *
 * @package    block_spam_deletion
 * @category   spam_deletion
 * @copyright  2012 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_spam_deletion extends block_base {
    /**
     * Initalise member params
     */
    function init() {
        $this->title = get_string('pluginname', 'block_spam_deletion');
    }

    /**
     * Only allow one instance of this block.
     *
     * @return bool
     */
    function instance_allow_multiple() {
        return false;
    }

    /**
     * Defines on which pages, block can be visible.
     *
     * @return array
     */
    function applicable_formats() {
        return array('site-index' => true, 'user-profile' => true);
    }

    /**
     * updates data before anything else is done, just after instantiating.
     */
    function specialization() {
        // load userdefined title and make sure it's never empty
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname','block_spam_deletion');
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Return HTML attributes to the outer <div> of the block when it is output.
     *
     * @return string
     */
    public function html_attributes() {
        $attributes = parent::html_attributes(); // Get default values
        $attributes['class'] .= ' block_'. $this->name(); // Append our class to class attribute
        return $attributes;
    }

    /**
     * Returns contents of block
     *
     * @return stdClass
     */
    function get_content() {
        global $CFG, $USER, $OUTPUT;
        $this->content =  new stdClass;

        //Only user with spamdelete capablity should see this block
        if (!has_capability('block/spam_deletion:spamdelete', $this->context)) {
            return $this->content;
        }
        $params = $this->page->url->params();
        $spamlib = null;
        
        //Get spammer info, if id exists and Pagetype === user-profile and not a current user
        //Also make sure id is not guest or admin id.
        $admins = explode(',', $CFG->siteadmins);
        $guests = explode(',', $CFG->siteguest);
        if (!empty($params['id']) &&
                ($this->page->pagetype === 'user-profile') &&
                ($params['id'] != $USER->id) &&
                !(in_array($params['id'], $admins) || in_array($params['id'], $guests))) {
            $spamlib = new spammer($params['id']);
        }

        //If spammer info available then process, else do nothing.
        if ($spamlib) {
            //If deleted or suspended account, then don't do anything.
            if (!$spamlib->is_active()) {
                $this->content->text = get_string('cannotdelete', 'block_spam_deletion');
            } else if (!$spamlib->is_recentuser()) {
                //If user has first access in last one month then only allow spam deletion
                $this->content->text = get_string('notrecentlyaccessed', 'block_spam_deletion');
            } else {
                //Show spammer data count (blog post, messages, forum and comments)
                $this->content->text .= $spamlib->show_data_count();

                //Delete button
                $urlparams = array('userid' => $params['id']);
                $url = new moodle_url('/blocks/spam_deletion/confirmdelete.php', $urlparams);
                $buttontext = get_string('deletebutton', 'block_spam_deletion');
                $button = new single_button($url, $buttontext);
                $content = $OUTPUT->render($button);
                $this->content->footer = $content;
            }
        } else {
            //If no spammer info found then you can't do anything.
            $this->content->text = get_string('cannotdelete', 'block_spam_deletion');
        }
        return $this->content;
    }
}

