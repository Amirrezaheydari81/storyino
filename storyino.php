<?php

/**
 * Plugin Name: Storyino
 * Plugin URI: https://github.com/Amirrezaheydari81/storyino
 * Description: پلاگین استوری وردپرس با شورت‌کد [storyino] و انتخاب تصویر/ویدیو از کتابخانه رسانه
 * Version: 1.0.0
 * Author: AmirrezaHeydari
 * License: GPL-2.0-or-later
 * Text Domain: storyino
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('STORYINO_VERSION', '1.0.0');
define('STORYINO_PATH', plugin_dir_path(__FILE__));
define('STORYINO_URL', plugin_dir_url(__FILE__));
define('STORYINO_OPTION_IDS', 'storyino_story_ids');

require_once STORYINO_PATH . 'includes/helpers.php';
require_once STORYINO_PATH . 'includes/shortcode.php';

if (is_admin()) {
    require_once STORYINO_PATH . 'includes/admin.php';
}

add_action('wp_enqueue_scripts', function () {
    wp_register_style(
        'storyino',
        STORYINO_URL . 'assets/css/storyino.css',
        [],
        STORYINO_VERSION
    );

    wp_register_script(
        'storyino',
        STORYINO_URL . 'assets/js/storyino.js',
        [],
        STORYINO_VERSION,
        true
    );
});
