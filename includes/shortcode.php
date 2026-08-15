<?php
defined('ABSPATH') || exit;

function storyino_render_shortcode($atts)
{
    $atts = shortcode_atts(
        [
            'label'    => __('استوری', 'storyino'),
            'ids'      => '',
            'speed'    => 250,
            'duration' => 5000,
        ],
        $atts,
        'storyino'
    );

    $stories = [];

    // 1) اگر داخل شورت‌کد ids داده شده بود
    if (! empty($atts['ids'])) {
        $ids     = storyino_get_ids_from_raw($atts['ids']);
        $stories = storyino_get_stories_from_ids($ids);
    }

    // 2) لیست انتخاب‌شده از تنظیمات Storyino
    if (empty($stories)) {
        $stories = storyino_get_option_stories();
    }

    // 3) فیلتر و فایل‌های پیش‌فرض
    if (empty($stories)) {
        $stories = apply_filters('storyino_default_stories', storyino_get_default_stories(), $atts);
    }

    $stories = storyino_normalize_stories($stories);

    // اگر هیچ عکس یا ویدیویی انتخاب نشده بود، هیچ چیزی رندر نکن
    if (empty($stories)) {
        return '';
    }

    $options = [
        'stories'               => $stories,
        'simulateSpeed'         => max(0, absint($atts['speed'])),
        'imageDuration'         => max(1000, absint($atts['duration'])),
        'fallbackVideoDuration' => 10000,
        'strings'               => [
            'close'     => __('بستن', 'storyino'),
            'previous'  => __('قبلی', 'storyino'),
            'next'      => __('بعدی', 'storyino'),
            'error'     => __('خطا در بارگذاری', 'storyino'),
            'noStories' => __('استوری پیدا نشد', 'storyino'),
            'link'      => storyino_get_link_label(),
        ],
    ];

    wp_enqueue_style('storyino');
    wp_enqueue_script('storyino');

    static $instance = 0;
    $instance++;

    $button_id    = 'storyino-btn-' . $instance;
    $button_style = storyino_get_button_style();
    $json         = esc_attr(wp_json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    // حالت آیکونی
    // حالت آیکونی
    $animation_class = storyino_get_icon_animation() ? ' storyino-animated' : '';
    $spin_style      = storyino_get_icon_animation() ? ' style="animation:storyino-icon-spin 5s linear infinite !important"' : '';

    // keyframes رو مستقیم توی صفحه تزریق می‌کنیم تا به فایل CSS وابسته نباشه
    if (storyino_get_icon_animation()) {
        wp_add_inline_style('storyino', '@keyframes storyino-icon-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}');
    }

    if ('icon' === $button_style) {
        return sprintf(
            '<button type="button" id="%s" class="storyino-button storyino-button-icon%s" data-storyino="%s" aria-label="%s" title="%s"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"%s><path d="M16.42 7.95C18.86 10.39 18.86 14.35 16.42 16.79C13.98 19.23 10.02 19.23 7.58 16.79C5.14 14.35 5.14 10.39 7.58 7.95C10.02 5.51 13.98 5.51 16.42 7.95Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.25 21.64C6.25 20.84 4.5 19.39 3.34 17.38C2.2 15.41 1.82 13.22 2.09 11.13" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.85 4.48C7.55 3.15 9.68 2.36 12 2.36C14.27 2.36 16.36 3.13 18.04 4.41" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M15.75 21.64C17.75 20.84 19.5 19.39 20.66 17.38C21.8 15.41 22.18 13.22 21.91 11.13" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>',
            esc_attr($button_id),
            $animation_class,
            $json,
            esc_attr($atts['label']),
            esc_attr($atts['label']),
            $spin_style
        );
    }

    // حالت متنی
    return sprintf(
        '<button type="button" id="%s" class="storyino-button" data-storyino="%s">%s</button>',
        esc_attr($button_id),
        $json,
        esc_html($atts['label'])
    );
}
add_shortcode('storyino', 'storyino_render_shortcode');
