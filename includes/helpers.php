<?php
defined('ABSPATH') || exit;

/**
 * تبدیل رشته/آرایه به آیدی‌های معتبر
 */
function storyino_get_ids_from_raw($raw)
{
    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }

    if (! is_array($raw)) {
        return [];
    }

    $ids = array_map('absint', $raw);
    $ids = array_filter($ids);
    $ids = array_unique($ids);

    return array_values($ids);
}

/**
 * استوری‌های پیش‌فرض از assets/media
 */
function storyino_get_default_stories()
{
    $items = [
        [
            'type'     => 'image',
            'file'     => '1.jpg',
            'duration' => 5000,
        ],
        [
            'type'     => 'image',
            'file'     => '2.jpg',
            'duration' => 5000,
        ],
        [
            'type'     => 'video',
            'file'     => '3.mp4',
            'duration' => 0,
        ],
        [
            'type'     => 'image',
            'file'     => '4.jpg',
            'duration' => 5000,
        ],
        [
            'type'     => 'video',
            'file'     => '5.mp4',
            'duration' => 0,
        ],
    ];

    $stories = [];

    foreach ($items as $item) {
        $file_path = STORYINO_PATH . 'assets/media/' . $item['file'];

        if (file_exists($file_path)) {
            $stories[] = [
                'type'     => $item['type'],
                'src'      => STORYINO_URL . 'assets/media/' . $item['file'],
                'duration' => isset($item['duration']) ? absint($item['duration']) : 0,
            ];
        }
    }

    return $stories;
}
/**
 * گرفتن لینک‌های ذخیره‌شده
 */
function storyino_get_option_links()
{
    $links = get_option('storyino_story_links', []);

    if (! is_array($links)) {
        return [];
    }

    $clean = [];

    foreach ($links as $id => $url) {
        $id  = absint($id);
        $url = esc_url_raw($url);

        if ($id && $url) {
            $clean[$id] = $url;
        }
    }

    return $clean;
}

/**
 * ذخیره لینک‌ها
 */
function storyino_save_option_links($links)
{
    $clean = [];

    if (is_array($links)) {
        foreach ($links as $id => $url) {
            $id  = absint($id);
            $url = esc_url_raw($url);

            if ($id && $url) {
                $clean[$id] = $url;
            }
        }
    }

    update_option('storyino_story_links', $clean, false);
}

/**
 * گرفتن استوری‌ها از آیدی‌های Media Library
 */
function storyino_get_stories_from_ids($attachment_ids)
{
    $stories = [];
    $links   = storyino_get_option_links();

    foreach ((array) $attachment_ids as $id) {
        $id = absint($id);

        if (! $id) {
            continue;
        }

        $url = wp_get_attachment_url($id);

        if (! $url) {
            continue;
        }

        $mime = (string) get_post_mime_type($id);
        $type = (strpos($mime, 'video/') === 0) ? 'video' : 'image';

        $stories[] = [
            'type'     => $type,
            'src'      => $url,
            'duration' => 0,
            'link'     => isset($links[$id]) ? $links[$id] : '',
        ];
    }

    return $stories;
}

/**
 * آیدی‌های ذخیره‌شده در تنظیمات
 */
function storyino_get_option_ids()
{
    $ids = get_option(STORYINO_OPTION_IDS, []);

    return storyino_get_ids_from_raw($ids);
}

/**
 * استوری‌های ذخیره‌شده در تنظیمات
 */
function storyino_get_option_stories()
{
    return storyino_get_stories_from_ids(storyino_get_option_ids());
}

/**
 * نرمال‌سازی استوری‌ها
 */
function storyino_normalize_stories($stories)
{
    $clean = [];

    if (! is_array($stories)) {
        return $clean;
    }

    foreach ($stories as $story) {
        if (empty($story['src'])) {
            continue;
        }

        $type = isset($story['type']) && in_array($story['type'], ['image', 'video'], true)
            ? $story['type']
            : 'image';

        $clean[] = [
            'type'     => $type,
            'src'      => esc_url_raw($story['src']),
            'duration' => isset($story['duration']) ? absint($story['duration']) : 0,
            'link'     => isset($story['link']) ? esc_url_raw($story['link']) : '',
        ];
    }

    return $clean;
}
/**
 * گرفتن متن دکمه لینک
 */
function storyino_get_link_label()
{
    $label = get_option('storyino_link_label', '');

    $label = is_string($label) ? trim($label) : '';

    return $label !== '' ? $label : __('مشاهده', 'storyino');
}

/**
 * ذخیره متن دکمه لینک
 */
function storyino_save_link_label($label)
{
    $label = is_string($label) ? sanitize_text_field(wp_unslash($label)) : '';
    update_option('storyino_link_label', $label, false);
}
/**
 * گرفتن حالت دکمه (text یا icon)
 */
function storyino_get_button_style()
{
    $style = get_option('storyino_button_style', 'text');

    return in_array($style, ['text', 'icon'], true) ? $style : 'text';
}

/**
 * ذخیره حالت دکمه
 */
function storyino_save_button_style($style)
{
    $style = in_array($style, ['text', 'icon'], true) ? $style : 'text';

    update_option('storyino_button_style', $style, false);
}
/**
 * وضعیت انیمیشن آیکون
 */
function storyino_get_icon_animation()
{
    return (bool) get_option('storyino_icon_animation', false);
}

/**
 * ذخیره وضعیت انیمیشن آیکون
 */
function storyino_save_icon_animation($value)
{
    update_option('storyino_icon_animation', (bool) $value, false);
}
