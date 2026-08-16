<?php

/**
 * Plugin Name: Storyino
 * Plugin URI: https://github.com/Amirrezaheydari81/storyino
 * Description: پلاگین استوری وردپرس با شورت‌کد [storyino] و انتخاب تصویر/ویدیو از کتابخانه رسانه
 * Version: 1.1.6
 * Author: AmirrezaHeydari
 * Author URI: https://github.com/Amirrezaheydari81
 * License: GPL-2.0-or-later
 * Text Domain: storyino
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('STORYINO_VERSION', '1.1.6');
define('STORYINO_PATH', plugin_dir_path(__FILE__));
define('STORYINO_URL', plugin_dir_url(__FILE__));
define('STORYINO_OPTION_IDS', 'storyino_story_ids');
define('STORYINO_OPTION_CATS', 'storyino_categories');

require_once STORYINO_PATH . 'includes/helpers.php';
require_once STORYINO_PATH . 'includes/shortcode.php';

if (is_admin()) {
    require_once STORYINO_PATH . 'includes/admin.php';
}

add_action('init', function () {
    load_plugin_textdomain('storyino', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('wp_enqueue_scripts', function () {
    $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

    wp_register_style(
        'storyino',
        STORYINO_URL . "assets/css/storyino{$suffix}.css",
        [],
        STORYINO_VERSION
    );

    wp_register_script(
        'storyino',
        STORYINO_URL . "assets/js/storyino{$suffix}.js",
        [],
        STORYINO_VERSION,
        true
    );

    wp_script_add_data('storyino', 'strategy', 'defer');

    wp_localize_script('storyino', 'storyinoI18n', storyino_player_strings());
    wp_localize_script('storyino', 'storyinoUi', [
        'useVazir' => storyino_get_vazir_ui() ? 1 : 0,
    ]);
});
