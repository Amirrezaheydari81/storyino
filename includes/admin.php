<?php
defined('ABSPATH') || exit;

add_action('admin_menu', 'storyino_admin_menu');
add_action('admin_enqueue_scripts', 'storyino_admin_assets');

function storyino_admin_menu() {
    add_menu_page(
        __('Storyino', 'storyino'),
        __('Storyino', 'storyino'),
        'manage_options',
        'storyino-settings',
        'storyino_render_settings_page',
        'dashicons-format-gallery'
    );
}

function storyino_admin_assets($hook) {
    if ('toplevel_page_storyino-settings' !== $hook) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-sortable');

    wp_enqueue_style(
        'storyino-admin',
        STORYINO_URL . 'assets/css/storyino-admin.css',
        [],
        STORYINO_VERSION
    );

    wp_enqueue_script(
        'storyino-admin',
        STORYINO_URL . 'assets/js/storyino-admin.js',
        ['jquery', 'jquery-ui-sortable'],
        STORYINO_VERSION,
        true
    );

    wp_localize_script('storyino-admin', 'storyinoAdmin', [
        'frameTitle'  => __('انتخاب تصویر و ویدیو', 'storyino'),
        'frameButton' => __('افزودن به استوری‌ها', 'storyino'),
    ]);
}

function storyino_render_settings_page() {
    if (! current_user_can('manage_options')) {
        return;
    }

    $saved = false;

    if (isset($_POST['storyino_save']) && check_admin_referer('storyino_save_settings', 'storyino_nonce')) {
        $raw = isset($_POST['storyino_story_ids'])
            ? sanitize_text_field(wp_unslash($_POST['storyino_story_ids']))
            : '';

        $ids = storyino_get_ids_from_raw($raw);

        update_option(STORYINO_OPTION_IDS, $ids, false);

        $saved = true;
    }

    $ids = storyino_get_option_ids();
    ?>
    <div class="wrap storyino-admin">
        <h1><?php esc_html_e('Storyino', 'storyino'); ?></h1>

        <?php if ($saved) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('ذخیره شد.', 'storyino'); ?></p>
            </div>
        <?php endif; ?>

        <div class="storyino-layout">
            <div class="storyino-panel">
                <h2><?php esc_html_e('تنظیمات استوری', 'storyino'); ?></h2>

                <p>
                    <?php esc_html_e('تصویر یا ویدیو را از کتابخانه رسانه انتخاب کن. تعداد نامحدود است و با کشیدن می‌توانی ترتیب را عوض کنی.', 'storyino'); ?>
                </p>

                <form method="post">
                    <?php wp_nonce_field('storyino_save_settings', 'storyino_nonce'); ?>

                    <input
                        type="hidden"
                        name="storyino_story_ids"
                        id="storyino-story-ids"
                        value="<?php echo esc_attr(implode(',', $ids)); ?>"
                    >

                    <div class="storyino-toolbar">
                        <button type="button" id="storyino-open-media" class="button button-primary">
                            <?php esc_html_e('+ انتخاب از کتابخانه رسانه', 'storyino'); ?>
                        </button>

                        <span class="storyino-count"></span>
                    </div>

                    <ul id="storyino-list" class="storyino-list">
                        <?php
                        foreach ($ids as $id) {
                            storyino_render_admin_item($id);
                        }
                        ?>
                    </ul>

                    <p>
                        <button type="submit" name="storyino_save" value="1" class="button button-primary">
                            <?php esc_html_e('ذخیره تغییرات', 'storyino'); ?>
                        </button>
                    </p>
                </form>
            </div>
                                <!-- ۲) شورت‌کدها -->

                            <?php storyino_render_shortcodes_docs($ids); ?>
        </div>
    </div>

    <script>
        (function () {
            const copyButtons = document.querySelectorAll('.storyino-copy');

            copyButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-copy');
                    const codeElement = document.getElementById(targetId);

                    if (! codeElement) {
                        return;
                    }

                    const text = codeElement.textContent.trim();
                    const originalText = this.textContent;
                    const buttonElement = this;

                    const showCopied = function () {
                        buttonElement.textContent = 'کپی شد';
                        buttonElement.classList.add('storyino-copied');

                        setTimeout(function () {
                            buttonElement.textContent = originalText;
                            buttonElement.classList.remove('storyino-copied');
                        }, 1500);
                    };

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text)
                            .then(showCopied)
                            .catch(function () {
                                storyinoFallbackCopy(text);
                                showCopied();
                            });
                    } else {
                        storyinoFallbackCopy(text);
                        showCopied();
                    }
                });
            });

            function storyinoFallbackCopy(text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();

                try {
                    document.execCommand('copy');
                } catch (e) {}

                textarea.remove();
            }
        })();
    </script>
    <?php
}

function storyino_render_shortcodes_docs($ids) {
    $ids_string = implode(',', $ids);

    $dynamic_ids_shortcode = $ids_string
        ? '[storyino ids="' . $ids_string . '"]'
        : '[storyino ids="12,18,25"]';

    $examples = [
        [
            'id'    => 'storyino-sc-basic',
            'title' => __('نمایش ساده', 'storyino'),
            'desc'  => __('از استوری‌های انتخاب‌شده در همین صفحه استفاده می‌کند.', 'storyino'),
            'code'  => '[storyino]',
        ],
        [
            'id'    => 'storyino-sc-label',
            'title' => __('تغییر متن دکمه', 'storyino'),
            'desc'  => __('متن دکمه استوری را تغییر می‌دهد.', 'storyino'),
            'code'  => '[storyino label="مشاهده استوری‌ها"]',
        ],
        [
            'id'    => 'storyino-sc-speed',
            'title' => __('غیرفعال کردن شبیه‌سازی سرعت', 'storyino'),
            'desc'  => __('برای سایت اصلی بهتر است سرعت شبیه‌سازی خاموش باشد.', 'storyino'),
            'code'  => '[storyino speed="0"]',
        ],
        [
            'id'    => 'storyino-sc-duration',
            'title' => __('زمان نمایش عکس‌ها', 'storyino'),
            'desc'  => __('مدت نمایش هر عکس بر حسب میلی‌ثانیه است.', 'storyino'),
            'code'  => '[storyino duration="6000"]',
        ],
        [
            'id'    => 'storyino-sc-combined',
            'title' => __('ترکیبی', 'storyino'),
            'desc'  => __('چند تنظیم همزمان: متن دکمه، سرعت واقعی و زمان نمایش.', 'storyino'),
            'code'  => '[storyino label="استوری محصولات" speed="0" duration="6000"]',
        ],
        [
            'id'    => 'storyino-sc-ids',
            'title' => __('استفاده از آی‌دی‌های خاص', 'storyino'),
            'desc'  => __('فقط رسانه‌هایی که آی‌دی آن‌ها وارد شود نمایش داده می‌شوند.', 'storyino'),
            'code'  => $dynamic_ids_shortcode,
        ],
    ];
    ?>
    <div class="storyino-panel">
        <h2><?php esc_html_e('شورت‌کدها', 'storyino'); ?></h2>

        <p>
            <?php esc_html_e('هر کدام از شورت‌کدهای زیر را می‌توانی داخل برگه، نوشته یا ویجت استفاده کنی.', 'storyino'); ?>
        </p>

        <h3><?php esc_html_e('پارامترها', 'storyino'); ?></h3>

        <table class="storyino-table">
            <tr>
                <th><?php esc_html_e('پارامتر', 'storyino'); ?></th>
                <th><?php esc_html_e('توضیح', 'storyino'); ?></th>
                <th><?php esc_html_e('مثال', 'storyino'); ?></th>
            </tr>

            <tr>
                <td><code>label</code></td>
                <td><?php esc_html_e('متن دکمه استوری', 'storyino'); ?></td>
                <td><code>label="مشاهده استوری"</code></td>
            </tr>

            <tr>
                <td><code>ids</code></td>
                <td><?php esc_html_e('آی‌دی فایل‌های رسانه، جداشده با کاما', 'storyino'); ?></td>
                <td><code>ids="12,18,25"</code></td>
            </tr>

            <tr>
                <td><code>speed</code></td>
                <td><?php esc_html_e('شبیه‌سازی سرعت دانلود بر حسب KB/s', 'storyino'); ?></td>
                <td><code>speed="0"</code></td>
            </tr>

            <tr>
                <td><code>duration</code></td>
                <td><?php esc_html_e('مدت نمایش هر عکس بر حسب میلی‌ثانیه', 'storyino'); ?></td>
                <td><code>duration="6000"</code></td>
            </tr>
        </table>

        <h3 style="margin-top:18px;"><?php esc_html_e('نمونه‌های آماده', 'storyino'); ?></h3>

        <?php foreach ($examples as $example) : ?>
            <div class="storyino-shortcode-item">
                <strong><?php echo esc_html($example['title']); ?></strong>
                <p><?php echo esc_html($example['desc']); ?></p>

                <div class="storyino-shortcode-row">
                    <code id="<?php echo esc_attr($example['id']); ?>"><?php echo esc_html($example['code']); ?></code>
                    <button type="button" class="button button-secondary storyino-copy" data-copy="<?php echo esc_attr($example['id']); ?>">
                        <?php esc_html_e('کپی', 'storyino'); ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="storyino-note">
            <?php esc_html_e('اگر از صفحه‌سازهایی مثل المنتور استفاده می‌کنی، شورت‌کد را داخل ویجت Shortcode یا HTML قرار بده.', 'storyino'); ?>
        </div>
    </div>
    <?php
}

function storyino_render_admin_item($id) {
    $mime     = (string) get_post_mime_type($id);
    $is_video = strpos($mime, 'video/') === 0;
    $thumb    = wp_get_attachment_image_url($id, 'thumbnail');

    if (! $thumb) {
        $thumb = wp_mime_type_icon($id);
    }
    ?>
    <li class="storyino-item" data-id="<?php echo esc_attr($id); ?>">
        <img src="<?php echo esc_url($thumb); ?>" alt="">
        <span class="storyino-type">
            <?php echo $is_video ? esc_html__('ویدیو', 'storyino') : esc_html__('تصویر', 'storyino'); ?>
        </span>
        <button type="button" class="storyino-remove" aria-label="<?php esc_attr_e('حذف', 'storyino'); ?>">×</button>
    </li>
    <?php
}