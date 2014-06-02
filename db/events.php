<?php
/**
 * Events observers
 *
 * @package    block_spam_deletion
 * @category   spam_deletion
 * @copyright  2014 Daniel Neis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$observers = array(

    array(
        'eventname'   => '\mod_forum\event\discussion_viewed',
        'callback'    => '\block_spam_deletion\forum_observers::discussion_viewed',
    ),
);
