Spam deletion block for Moodle
==============================

The spam deletion block is used for detecting and deleting spam. The block adds "Report spam" action link into the forum pages and
comments. It lets the operator easily suspend spammer accounts and delete their contents.


Post-installation setup
-----------------------

* Add the block to the front page
* Edit the block settings
* Change 'Page contexts' to 'Display through the entire site'

To let the block automatically detect and block spam-like forum posts, configure the setting `block_spam_deletion_badwords` and
append the following into your config.php file _after_ the setup.php inclusion:

    if ($SCRIPT === '/mod/forum/post.php') {
        @include_once("$CFG->dirroot/blocks/spam_deletion/detect.php");
    }

Please check the code of that `detect.php` script to understand what criteria it uses to block forum posts. The current behaviour
has been tuned up to work well at moodle.org.

See the discussion [Spam reporting and removal](https://moodle.org/mod/forum/discuss.php?d=218297) for further information and
tracker links.


Maintainer
----------

The block has been written and is currently maintained by Dan Poltawski. See [the block
page](https://moodle.org/plugins/view/block_spam_deletion) at Moodle Plugins directory for the full list of contributors.
