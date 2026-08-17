<?php
defined('ABSPATH') || exit;

/**
 * کش درخواست فعلی برای جلوگیری از تکرار get_option
 */
function storyino_memo($key, $value = null)
{
    static $store = [];

    if ($key === '__flush') {
        $store = [];
        return null;
    }

    if (func_num_args() === 2) {
        $store[$key] = $value;
        return $value;
    }

    return array_key_exists($key, $store) ? $store[$key] : null;
}

function storyino_flush_runtime_cache()
{
    storyino_memo('__flush');
}

/**
 * فقط لینک‌های http/https
 */
function storyino_sanitize_http_url($url)
{
    $url = esc_url_raw((string) $url, ['http', 'https']);

    if ($url === '') {
        return '';
    }

    $parts = wp_parse_url($url);

    if (empty($parts['scheme']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
        return '';
    }

    return $url;
}

/**
 * نوع رسانه پیوست استوری (image|video) یا خالی
 */
function storyino_attachment_media_type($id)
{
    $id = absint($id);

    if (! $id) {
        return '';
    }

    $post = get_post($id);

    if (! $post || $post->post_type !== 'attachment') {
        return '';
    }

    $status = get_post_status($id);

    if (! in_array($status, ['inherit', 'publish'], true)) {
        return '';
    }

    $mime = (string) get_post_mime_type($id);

    if (strpos($mime, 'image/') === 0) {
        return 'image';
    }

    if (strpos($mime, 'video/') === 0) {
        return 'video';
    }

    return '';
}

function storyino_sanitize_cover_id($id)
{
    $id = absint($id);

    return ($id && wp_attachment_is_image($id)) ? $id : 0;
}

function storyino_sanitize_story_ids($raw)
{
    $ids = [];

    foreach (storyino_get_ids_from_raw($raw) as $id) {
        if (storyino_attachment_media_type($id) !== '') {
            $ids[] = $id;
        }
    }

    return $ids;
}

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
 * تصویر پیش‌نمایش رسانه برای پنل ادمین (عکس یا پوستر ویدیو)
 */
function storyino_get_attachment_preview_url($id, $size = 'medium')
{
    $id = absint($id);

    if (! $id) {
        return '';
    }

    $url = wp_get_attachment_image_url($id, $size);

    if ($url) {
        return $url;
    }

    $url = wp_get_attachment_image_url($id, 'thumbnail');

    if ($url) {
        return $url;
    }

    $thumb_id = get_post_thumbnail_id($id);

    if ($thumb_id) {
        $url = wp_get_attachment_image_url($thumb_id, $size);

        if ($url) {
            return $url;
        }
    }

    return '';
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
    $cached = storyino_memo('links');

    if (is_array($cached)) {
        return $cached;
    }

    $links = get_option('storyino_story_links', []);

    if (! is_array($links)) {
        return storyino_memo('links', []);
    }

    $clean = [];

    foreach ($links as $id => $url) {
        $id  = absint($id);
        $url = storyino_sanitize_http_url($url);

        if ($id && $url) {
            $clean[$id] = $url;
        }
    }

    return storyino_memo('links', $clean);
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
            $url = storyino_sanitize_http_url($url);

            if ($id && $url) {
                $clean[$id] = $url;
            }
        }
    }

    update_option('storyino_story_links', $clean, false);
    storyino_flush_runtime_cache();
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

        $type = storyino_attachment_media_type($id);

        if ($type === '') {
            continue;
        }

        $url = wp_get_attachment_url($id);

        if (! $url) {
            continue;
        }

        $story = [
            'type' => $type,
            'src'  => $url,
        ];

        if (! empty($links[$id])) {
            $story['link'] = $links[$id];
        }

        $stories[] = $story;
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

        $src  = storyino_sanitize_http_url($story['src']);
        $link = isset($story['link']) ? storyino_sanitize_http_url($story['link']) : '';
        $duration = isset($story['duration']) ? absint($story['duration']) : 0;

        if ($src === '') {
            continue;
        }

        $item = [
            'type' => $type,
            'src'  => $src,
        ];

        if ($duration > 0) {
            $item['duration'] = $duration;
        }

        if ($link !== '') {
            $item['link'] = $link;
        }

        $clean[] = $item;
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

function storyino_get_vazir_ui()
{
    return (bool) get_option('storyino_vazir_ui', false);
}

function storyino_save_vazir_ui($value)
{
    update_option('storyino_vazir_ui', (bool) $value, false);
}

function storyino_get_show_title()
{
    $value = get_option('storyino_show_title', '1');

    return $value === '1' || $value === 1 || $value === true;
}

function storyino_save_show_title($value)
{
    update_option('storyino_show_title', $value ? '1' : '0', false);
}

function storyino_get_title_color()
{
    $color = get_option('storyino_title_color', 'black');

    return in_array($color, ['black', 'white'], true) ? $color : 'black';
}

function storyino_save_title_color($color)
{
    $color = in_array($color, ['black', 'white'], true) ? $color : 'black';

    update_option('storyino_title_color', $color, false);
}

function storyino_get_ring_size()
{
    $size = get_option('storyino_ring_size', 'medium');

    return in_array($size, ['small', 'medium', 'large'], true) ? $size : 'medium';
}

function storyino_save_ring_size($size)
{
    $size = in_array($size, ['small', 'medium', 'large'], true) ? $size : 'medium';

    update_option('storyino_ring_size', $size, false);
}

function storyino_sanitize_slug($slug)
{
    $slug = sanitize_title((string) $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', (string) $slug);

    return is_string($slug) ? $slug : '';
}

/**
 * ساخت اسلاگ یکتا برای دسته
 */
function storyino_unique_category_slug($slug, $used = [])
{
    $slug = storyino_sanitize_slug($slug);

    if ($slug === '') {
        $slug = 'cat';
    }

    $base = $slug;
    $i    = 2;

    while (in_array($slug, $used, true)) {
        $slug = $base . '-' . $i;
        $i++;
    }

    return $slug;
}

/**
 * نرمال‌سازی یک دسته
 */
function storyino_normalize_category($row)
{
    if (! is_array($row)) {
        return null;
    }

    $title = isset($row['title']) ? sanitize_text_field($row['title']) : '';
    $slug  = isset($row['slug']) ? (string) $row['slug'] : '';
    $cover = isset($row['cover']) ? storyino_sanitize_cover_id($row['cover']) : 0;
    $ids   = storyino_sanitize_story_ids(isset($row['ids']) ? $row['ids'] : []);

    if ($title === '') {
        $title = __('بدون عنوان', 'storyino');
    }

    return [
        'slug'  => $slug,
        'title' => $title,
        'cover' => $cover,
        'ids'   => $ids,
    ];
}

/**
 * گرفتن دسته‌بندی‌ها (با مهاجرت از لیست قدیمی)
 */
function storyino_get_categories()
{
    $cached = storyino_memo('cats');

    if (is_array($cached)) {
        return $cached;
    }

    $stored = get_option(STORYINO_OPTION_CATS, null);

    if (is_array($stored)) {
        $used = [];
        $cats = [];

        foreach ($stored as $row) {
            $cat = storyino_normalize_category($row);

            if (! $cat) {
                continue;
            }

            $cat['slug'] = storyino_unique_category_slug($cat['slug'], $used);
            $used[]      = $cat['slug'];
            $cats[]      = $cat;
        }

        return storyino_memo('cats', $cats);
    }

    $ids = storyino_get_option_ids();

    if (empty($ids)) {
        return storyino_memo('cats', []);
    }

    return storyino_memo('cats', [
        [
            'slug'  => 'default',
            'title' => __('استوری‌ها', 'storyino'),
            'cover' => 0,
            'ids'   => $ids,
        ],
    ]);
}

/**
 * ذخیره دسته‌بندی‌ها
 */
function storyino_save_categories($cats)
{
    $clean    = [];
    $used     = [];
    $all_ids  = [];

    if (is_array($cats)) {
        foreach ($cats as $row) {
            $cat = storyino_normalize_category($row);

            if (! $cat) {
                continue;
            }

            $raw_title = isset($row['title']) ? sanitize_text_field($row['title']) : '';

            if ($raw_title === '' && empty($cat['ids']) && empty($cat['cover'])) {
                continue;
            }

            $cat['slug'] = storyino_unique_category_slug($cat['slug'] !== '' ? $cat['slug'] : $cat['title'], $used);
            $used[]      = $cat['slug'];
            $all_ids     = array_merge($all_ids, $cat['ids']);
            $clean[]     = $cat;
        }
    }

    update_option(STORYINO_OPTION_CATS, $clean, false);
    update_option(STORYINO_OPTION_IDS, array_values(array_unique($all_ids)), false);
    storyino_flush_runtime_cache();

    return $clean;
}

/**
 * پیدا کردن دسته با اسلاگ
 */
function storyino_get_category_by_slug($slug)
{
    $slug = storyino_sanitize_slug($slug);

    foreach (storyino_get_categories() as $cat) {
        if ($cat['slug'] === $slug) {
            return $cat;
        }
    }

    return null;
}

/**
 * آدرس تصویر دایره دسته
 */
function storyino_get_category_cover_url($category)
{
    if (! is_array($category)) {
        return '';
    }

    if (! empty($category['cover'])) {
        $url = wp_get_attachment_image_url((int) $category['cover'], 'thumbnail');

        if ($url) {
            return $url;
        }
    }

    if (! empty($category['ids'][0])) {
        $url = wp_get_attachment_image_url((int) $category['ids'][0], 'thumbnail');

        if ($url) {
            return $url;
        }
    }

    return '';
}

/**
 * تنظیمات پلیر برای چند استوری
 */
function storyino_player_config($stories, $atts = [])
{
    $atts = is_array($atts) ? $atts : [];
    $stories = storyino_normalize_stories($stories);

    if (empty($stories)) {
        return null;
    }

    $config = [
        'stories' => $stories,
    ];

    $speed = isset($atts['speed']) ? max(0, absint($atts['speed'])) : 0;
    $duration = isset($atts['duration']) ? max(1000, absint($atts['duration'])) : 5000;

    if ($speed > 0) {
        $config['simulateSpeed'] = $speed;
    }

    if ($duration !== 5000) {
        $config['imageDuration'] = $duration;
    }

    return $config;
}

function storyino_player_strings()
{
    return [
        'close'     => __('بستن', 'storyino'),
        'previous'  => __('قبلی', 'storyino'),
        'next'      => __('بعدی', 'storyino'),
        'error'     => __('خطا در بارگذاری', 'storyino'),
        'noStories' => __('استوری پیدا نشد', 'storyino'),
        'link'      => storyino_get_link_label(),
    ];
}
