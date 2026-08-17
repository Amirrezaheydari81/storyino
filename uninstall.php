<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('storyino_story_ids');
delete_option('storyino_categories');
delete_option('storyino_story_links');
delete_option('storyino_link_label');
delete_option('storyino_button_style');
delete_option('storyino_icon_animation');
delete_option('storyino_vazir_ui');
delete_option('storyino_show_title');
delete_option('storyino_title_color');
delete_option('storyino_ring_size');
