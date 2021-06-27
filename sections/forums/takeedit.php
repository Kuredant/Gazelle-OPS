<?php
authorize();

if ($Viewer->disablePosting()) {
    error('Your posting privileges have been removed.');
}

$postId = (int)$_POST['post'];
$forum = (new Gazelle\Manager\Forum)->findByPostId($postId);
if (!$forum) {
    error(404, true);
}
if (!$Viewer->writeAccess($forum)) {
    error('You lack the permission to edit this post.', true);
}

$forumPost = $forum->postInfo($postId);
if ($forumPost['is-locked'] && !check_perms('site_moderate_forums')) {
    error('You cannot edit a locked post.', true);
}
if ($Viewer->id() != $forumPost['user-id']) {
    if (!check_perms('site_moderate_forums')) {
        error(403, true);
    }
    if ($_POST['pm'] ?? 0) {
        (new Gazelle\Manager\User)->sendPM($forumPost['user-id'], 0,
            "Your post #$postId has been edited",
            sprintf('One of your posts has been edited by [url=%s]%s[/url]: [url]%s[/url]',
                "user.php?id={$LoggedUser['ID']}",
                $Viewer->username(),
                SITE_URL . "/forums.php?action=viewthread&postid=$postId#post$postId"
            )
        );
    }
}

$forum->editPost($Viewer->id(), $postId, $_POST['body']);

// This gets sent to the browser, which echoes it in place of the old body
echo Text::full_format($forum->postBody($postId));
?>
<br /><br /><span class="last_edited">Last edited by <a href="user.php?id=<?=$Viewer->id()?>"><?=$Viewer->username()?></a> Just now</span>
