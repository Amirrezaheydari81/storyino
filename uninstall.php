<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('storyino_story_ids');
