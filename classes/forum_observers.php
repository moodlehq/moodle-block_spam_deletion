<?php

/**
 * Forum observers
 *
 * @package    block_spam_deletion
 * @copyright  2014 Daniel Neis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_spam_deletion;
defined('MOODLE_INTERNAL') || die();

class forum_observers {
    /**
     * A new discussion was viewed
     *
     * @param \mod_forum\event\discussion_viewed $event The event.
     * @return void
     */
    public static function discussion_viewed(\mod_forum\event\discussion_viewed $event) {
        if (isloggedin() && !isguestuser()) {
            global $PAGE;
            $PAGE->requires->strings_for_js(array('reportasspam'), 'block_spam_deletion');
            $PAGE->requires->js_init_call('M.block_spam_deletion.add_to_comments');
            $PAGE->requires->js_init_call('M.block_spam_deletion.add_to_forum_posts');
        }
    }
}
