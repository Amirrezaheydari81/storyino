<?php
defined('ABSPATH') || exit;

function storyino_render_shortcode($atts) {
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
        'stories'                => $stories,
        'simulateSpeed'          => max(0, absint($atts['speed'])),
        'imageDuration'          => max(1000, absint($atts['duration'])),
        'fallbackVideoDuration'  => 10000,
        'strings'                => [
            'close'     => __('بستن', 'storyino'),
            'previous'  => __('قبلی', 'storyino'),
            'next'      => __('بعدی', 'storyino'),
            'error'     => __('خطا در بارگذاری', 'storyino'),
            'noStories' => __('استوری پیدا نشد', 'storyino'),
        ],
    ];

    wp_enqueue_style('storyino');
    wp_enqueue_script('storyino');

    static $instance = 0;
    $instance++;

    $button_id = 'storyino-btn-' . $instance;

return sprintf(
    '<button type="button" id="%s" class="storyino-button" data-storyino="%s">%s</button>',
    esc_attr($button_id),
    esc_attr(wp_json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    esc_html($atts['label'])
);
}
add_shortcode('storyino', 'storyino_render_shortcode');