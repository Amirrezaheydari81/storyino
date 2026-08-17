<?php
defined('ABSPATH') || exit;

function storyino_enqueue_frontend()
{
    wp_enqueue_style('storyino');
    wp_enqueue_script('storyino');

    static $font_css = false;

    if ($font_css) {
        return;
    }

    $font_css = true;
    $font_url = STORYINO_URL . 'assets/fonts/Vazirmatn-Medium.woff2';
    $src      = 'url("' . esc_url($font_url) . '") format("woff2")';

    wp_add_inline_style(
        'storyino',
        '@font-face{font-family:"Vazirmatn";font-style:normal;font-weight:400;font-display:swap;src:' . $src . '}'
        . '@font-face{font-family:"Vazirmatn";font-style:normal;font-weight:500;font-display:swap;src:' . $src . '}'
        . '@font-face{font-family:"Vazirmatn";font-style:normal;font-weight:600;font-display:swap;src:' . $src . '}'
    );
}

function storyino_render_shortcode($atts)
{
    $atts = shortcode_atts(
        [
            'label'    => '',
            'ids'      => '',
            'cat'      => '',
            'speed'    => 0,
            'duration' => 5000,
        ],
        $atts,
        'storyino'
    );

    storyino_enqueue_frontend();

    if (! empty($atts['ids'])) {
        $ids     = storyino_get_ids_from_raw($atts['ids']);
        $stories = storyino_get_stories_from_ids($ids);
        $config  = storyino_player_config($stories, $atts);

        if (! $config) {
            return '';
        }

        $label = $atts['label'] !== '' ? $atts['label'] : __('استوری', 'storyino');
        $cover = '';

        if (! empty($ids[0])) {
            $cover = (string) wp_get_attachment_image_url((int) $ids[0], 'thumbnail');
        }

        return storyino_render_tray([
            storyino_render_ring($config, $label, $cover),
        ]);
    }

    if (! empty($atts['cat'])) {
        $category = storyino_get_category_by_slug($atts['cat']);

        if (! $category) {
            return '';
        }

        return storyino_render_category_ring($category, $atts);
    }

    $rings = [];

    foreach (storyino_get_categories() as $category) {
        $ring = storyino_render_category_ring($category, $atts);

        if ($ring !== '') {
            $rings[] = $ring;
        }
    }

    if (! empty($rings)) {
        return storyino_render_tray($rings);
    }

    $stories = apply_filters('storyino_default_stories', storyino_get_default_stories(), $atts);
    $config  = storyino_player_config($stories, $atts);

    if (! $config) {
        return '';
    }

    $label = $atts['label'] !== '' ? $atts['label'] : __('استوری', 'storyino');
    $cover = ! empty($stories[0]['src']) ? $stories[0]['src'] : '';

    return storyino_render_tray([
        storyino_render_ring($config, $label, $cover),
    ]);
}

function storyino_render_category_ring($category, $atts)
{
    $stories = storyino_get_stories_from_ids($category['ids']);
    $config  = storyino_player_config($stories, $atts);

    if (! $config) {
        return '';
    }

    $label = $atts['label'] !== '' ? $atts['label'] : $category['title'];
    $cover = storyino_get_category_cover_url($category);

    return storyino_render_ring($config, $label, $cover);
}

function storyino_render_tray($rings)
{
    $rings = array_filter($rings);

    if (empty($rings)) {
        return '';
    }

    $classes = ['storyino-tray'];

    if (storyino_get_vazir_ui()) {
        $classes[] = 'storyino-use-vazir';
    }

    if (! storyino_get_show_title()) {
        $classes[] = 'storyino-hide-title';
    }

    $classes[] = 'storyino-title-' . storyino_get_title_color();
    $classes[] = 'storyino-size-' . storyino_get_ring_size();

    $prev_icon = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M9.3 5.3a1 1 0 0 1 1.4 0l6 6a1 1 0 0 1 0 1.4l-6 6a1 1 0 1 1-1.4-1.4L14.58 12 9.3 6.7a1 1 0 0 1 0-1.4z"/></svg>';
    $next_icon = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M14.7 5.3a1 1 0 0 1 0 1.4L9.42 12l5.28 5.3a1 1 0 1 1-1.4 1.4l-6-6a1 1 0 0 1 0-1.4l6-6a1 1 0 0 1 1.4 0z"/></svg>';

    return sprintf(
        '<div class="storyino-wrap" dir="rtl"><button type="button" class="storyino-tray-nav storyino-tray-prev" aria-label="%s">%s</button><div class="storyino-scroller"><div class="%s">%s</div></div><button type="button" class="storyino-tray-nav storyino-tray-next" aria-label="%s">%s</button></div>',
        esc_attr__('قبلی', 'storyino'),
        $prev_icon,
        esc_attr(implode(' ', $classes)),
        implode('', $rings),
        esc_attr__('بعدی', 'storyino'),
        $next_icon
    );
}

function storyino_render_ring($config, $label, $cover_url)
{
    $json = wp_json_encode(
        $config,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    $json = esc_attr((string) $json);
    $label = (string) $label;

    if ($cover_url) {
        $avatar = sprintf(
            '<img src="%s" alt="" loading="lazy" decoding="async">',
            esc_url($cover_url)
        );
    } else {
        $avatar = '<span class="storyino-ring-fallback" aria-hidden="true"></span>';
    }

    $title = storyino_get_show_title()
        ? '<span class="storyino-ring-label">' . esc_html($label) . '</span>'
        : '';

    return sprintf(
        '<button type="button" class="storyino-ring" data-storyino="%s" aria-label="%s"><span class="storyino-ring-avatar">%s</span>%s</button>',
        $json,
        esc_attr($label),
        $avatar,
        $title
    );
}

function storyino_register_category_shortcodes()
{
    foreach (storyino_get_categories() as $category) {
        $slug = isset($category['slug']) ? $category['slug'] : '';

        if ($slug === '' || ! preg_match('/^[a-z0-9\-]+$/', $slug)) {
            continue;
        }

        $tag = 'storyino-' . $slug;

        if (shortcode_exists($tag)) {
            continue;
        }

        add_shortcode($tag, function ($atts) use ($slug) {
            $atts = is_array($atts) ? $atts : [];
            $atts['cat'] = $slug;

            return storyino_render_shortcode($atts);
        });
    }
}

add_shortcode('storyino', 'storyino_render_shortcode');
add_action('init', 'storyino_register_category_shortcodes');
