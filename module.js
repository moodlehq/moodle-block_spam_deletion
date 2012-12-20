M.block_spam_deletion = {};

// This function adds the 'report as spam' link on every post.
M.block_spam_deletion.add_to_forum_posts = function(Y) {
    // Get all the 'command divs' on the page.
    var commanddivs = Y.all('#page-mod-forum-discuss #region-main div.forumpost div.commands');
    commanddivs.each(function (commanddiv) {

        var replyid = 0;

        commanddiv.all('a').some(function (link) {
            // Search the links in the div for a 'reply' link.
            var url = link.get('href');
            if (matches = url.match(/mod\/forum\/post\.php\?reply=(\d+)/)) {
                // If a reply link is found, record the id of the post
                replyid = matches[1];
                return true;
            }
        });

        if (replyid) {
            // Add the 'report as spam' link to the DOM :)
            var url = M.cfg.wwwroot + '/blocks/spam_deletion/reportspam.php?postid='+replyid;
            commanddiv.prepend('<a href="' + url + '">' + M.str.block_spam_deletion.reportasspam + '</a>&nbsp;|&nbsp;');
        }
    });
};

// This function adds the 'report as spam' link on every post.
M.block_spam_deletion.add_to_comments = function(Y) {
    // Get all the 'command divs' on the page.
    var commentslist = Y.all('ul.comments-loaded li');
    commentslist.each(function (li) {
        if (matches = li.get('id').match(/comment-(\d+)-.*/)) {
            var commentid = matches[1];

            // This is a really ugly hack because the comments API allows the
            // comment template to be override. But this works on the comments block
            // and plugins db.
            var commentdiv = li.one('div.no-overflow');

            if (commentdiv) {
                var url = M.cfg.wwwroot + '/blocks/spam_deletion/reportspam.php?commentid='+commentid;
                commentdiv.append('<span style="float:right"><a href="' + url + '">' + M.str.block_spam_deletion.reportasspam + '</a></span>');
            }
        }
    });
};
