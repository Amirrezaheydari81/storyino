<?php
defined('ABSPATH') || exit;

add_action('admin_menu', 'storyino_admin_menu');
add_action('admin_enqueue_scripts', 'storyino_admin_assets');

function storyino_admin_menu()
{
    add_menu_page(
        __('Storyino', 'storyino'),
        __('Storyino', 'storyino'),
        'manage_options',
        'storyino-settings',
        'storyino_render_settings_page',
        'dashicons-format-gallery'
    );
}

function storyino_admin_assets($hook)
{
    if ('toplevel_page_storyino-settings' !== $hook) {
        return;
    }

    $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-sortable');

    wp_enqueue_style(
        'storyino-admin',
        STORYINO_URL . "assets/css/storyino-admin{$suffix}.css",
        [],
        STORYINO_VERSION
    );

    wp_enqueue_script(
        'storyino-admin',
        STORYINO_URL . "assets/js/storyino-admin{$suffix}.js",
        ['jquery', 'jquery-ui-sortable'],
        STORYINO_VERSION,
        true
    );

    wp_localize_script('storyino-admin', 'storyinoAdmin', [
        'frameTitle'      => __('انتخاب تصویر و ویدیو', 'storyino'),
        'frameButton'     => __('افزودن به استوری‌ها', 'storyino'),
        'coverTitle'      => __('تصویر دایره استوری', 'storyino'),
        'coverButton'     => __('انتخاب تصویر', 'storyino'),
        'copiedText'      => __('کپی شد', 'storyino'),
        'imageLabel'      => __('تصویر', 'storyino'),
        'videoLabel'      => __('ویدیو', 'storyino'),
        'removeLabel'     => __('حذف', 'storyino'),
        'linkPlaceholder' => __('لینک مقصد', 'storyino'),
        'confirmDelete'   => __('این دسته حذف شود؟', 'storyino'),
        'newCategory'     => __('دسته جدید', 'storyino'),
        'coverRequired'   => __('اولین آیتم دسته «%s» ویدیو است. قبل از ذخیره، تصویر دایره استوری را انتخاب کن.', 'storyino'),
        'untitledCategory'=> __('بدون عنوان', 'storyino'),
        'clearCover'      => __('حذف تصویر', 'storyino'),
    ]);
}

function storyino_render_settings_page()
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $saved = false;
    $save_errors = [];

    if (isset($_POST['storyino_save']) && check_admin_referer('storyino_save_settings', 'storyino_nonce')) {
        $raw_cats = isset($_POST['storyino_cats']) ? (array) wp_unslash($_POST['storyino_cats']) : [];
        $cats     = [];

        foreach ($raw_cats as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cats[] = [
                'title' => isset($row['title']) ? sanitize_text_field($row['title']) : '',
                'slug'  => isset($row['slug']) ? sanitize_title($row['slug']) : '',
                'cover' => isset($row['cover']) ? storyino_sanitize_cover_id($row['cover']) : 0,
                'ids'   => isset($row['ids']) ? storyino_sanitize_story_ids($row['ids']) : [],
            ];
        }

        foreach ($cats as $cat) {
            if (! empty($cat['cover']) || empty($cat['ids'][0])) {
                continue;
            }

            $mime = (string) get_post_mime_type((int) $cat['ids'][0]);

            if (strpos($mime, 'video/') === 0) {
                $title = $cat['title'] !== '' ? $cat['title'] : __('بدون عنوان', 'storyino');
                $save_errors[] = sprintf(
                    /* translators: %s: category title */
                    __('اولین آیتم دسته «%s» ویدیو است. قبل از ذخیره، تصویر دایره استوری را انتخاب کن.', 'storyino'),
                    $title
                );
            }
        }

        if (empty($save_errors)) {
            storyino_save_categories($cats);

            if (isset($_POST['storyino_button_style'])) {
                storyino_save_button_style(sanitize_key($_POST['storyino_button_style']));
            }

            $raw_links = isset($_POST['storyino_story_links']) ? (array) $_POST['storyino_story_links'] : [];
            $links     = [];

            foreach ($raw_links as $id => $url) {
                $id  = absint($id);
                $url = storyino_sanitize_http_url(wp_unslash($url));

                if ($id && $url) {
                    $links[$id] = $url;
                }
            }

            storyino_save_option_links($links);

            if (isset($_POST['storyino_link_label'])) {
                $label = sanitize_text_field(wp_unslash($_POST['storyino_link_label']));
                storyino_save_link_label($label);
            }

            storyino_save_icon_animation(isset($_POST['storyino_icon_animation']));
            storyino_save_vazir_ui(isset($_POST['storyino_vazir_ui']));

            $saved = true;
        }
    }

    $categories     = storyino_get_categories();
    $link_label     = storyino_get_link_label();
    $button_style   = storyino_get_button_style();
    $icon_animation = storyino_get_icon_animation();
    $vazir_ui       = storyino_get_vazir_ui();

    if (empty($categories)) {
        $categories = [
            [
                'slug'  => 'cat',
                'title' => '',
                'cover' => 0,
                'ids'   => [],
            ],
        ];
    }
    ?>
    <div class="wrap">
        <div id="storyino-admin" class="storyino-admin stn:mx-auto stn:max-w-6xl stn:py-2" dir="rtl" lang="fa">
            <header class="stn-hero stn:mb-6 stn:flex stn:items-start stn:gap-3 stn:rounded-2xl stn:px-4 stn:py-4">
                <span class="stn-mark stn:mt-0.5 stn:flex stn:h-10 stn:w-10 stn:shrink-0 stn:items-center stn:justify-center stn:rounded-xl stn:text-white" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="5"></rect>
                        <circle cx="12" cy="12" r="3.5"></circle>
                    </svg>
                </span>
                <div>
                    <h1 class="stn:text-xl stn:font-semibold stn:tracking-tight stn:text-zinc-900"><?php esc_html_e('Storyino', 'storyino'); ?></h1>
                    <p class="stn:mt-1 stn:text-sm stn:text-fuchsia-800/70"><?php esc_html_e('دسته‌بندی بساز، برای هر دسته تصویر دایره بگذار و با شورت‌کد اینستاگرامی نمایش بده.', 'storyino'); ?></p>
                </div>
            </header>

            <?php if ($saved) : ?>
                <div class="stn:mb-5 stn:rounded-lg stn:border stn:border-emerald-200 stn:bg-emerald-50 stn:px-4 stn:py-3 stn:text-sm stn:text-emerald-800" role="status">
                    <?php esc_html_e('تغییرات ذخیره شد.', 'storyino'); ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($save_errors)) : ?>
                <div class="stn:mb-5 stn:rounded-lg stn:border stn:border-amber-200 stn:bg-amber-50 stn:px-4 stn:py-3 stn:text-sm stn:text-amber-900" role="alert">
                    <?php foreach ($save_errors as $error) : ?>
                        <p><?php echo esc_html($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div id="storyino-cover-warning" class="stn:mb-5 stn:rounded-lg stn:border stn:border-amber-200 stn:bg-amber-50 stn:px-4 stn:py-3 stn:text-sm stn:text-amber-900" role="alert" hidden></div>

            <form method="post">
                <?php wp_nonce_field('storyino_save_settings', 'storyino_nonce'); ?>

                <div class="stn:grid stn:grid-cols-1 stn:items-start stn:gap-5 stn:xl:grid-cols-[minmax(0,1.15fr)_minmax(20rem,0.85fr)]">
                    <div class="stn:grid stn:gap-5">
                        <div class="stn:flex stn:flex-wrap stn:items-center stn:justify-between stn:gap-3">
                            <h2 class="stn-heading stn:text-sm stn:font-semibold stn:text-zinc-900"><?php esc_html_e('دسته‌بندی استوری‌ها', 'storyino'); ?></h2>
                            <button type="button" id="storyino-add-cat" class="stn-btn-story stn:inline-flex stn:h-9 stn:items-center stn:gap-2 stn:rounded-full stn:px-3.5 stn:text-sm stn:font-semibold">
                                + <?php esc_html_e('افزودن دسته', 'storyino'); ?>
                            </button>
                        </div>

                        <div id="storyino-cats" class="stn:grid stn:gap-5">
                            <?php foreach ($categories as $index => $category) {
                                storyino_render_admin_category($category, (int) $index);
                            } ?>
                        </div>

                        <section class="stn-card stn:rounded-xl stn:bg-white stn:p-5">
                            <h2 class="stn-heading stn:text-sm stn:font-semibold stn:text-zinc-900"><?php esc_html_e('تنظیمات ظاهری', 'storyino'); ?></h2>

                            <div class="stn:mt-5 stn:grid stn:gap-5">
                                <div>
                                    <label for="storyino-link-label" class="stn:mb-1.5 stn:block stn:text-sm stn:font-medium stn:text-zinc-800">
                                        <?php esc_html_e('متن دکمه لینک استوری', 'storyino'); ?>
                                    </label>
                                    <input
                                        type="text"
                                        id="storyino-link-label"
                                        name="storyino_link_label"
                                        value="<?php echo esc_attr($link_label); ?>"
                                        placeholder="<?php esc_attr_e('مثال: مشاهده، خرید، اطلاعات بیشتر...', 'storyino'); ?>"
                                        class="stn:h-10 stn:w-full stn:max-w-sm stn:rounded-lg stn:border stn:border-zinc-200 stn:bg-white stn:px-3 stn:text-sm stn:text-zinc-900 stn:outline-none stn:focus:border-fuchsia-400 stn:focus:ring-2 stn:focus:ring-fuchsia-500/20">
                                    <p class="stn:mt-1.5 stn:text-xs stn:text-zinc-500">
                                        <?php esc_html_e('این متن روی دکمه پایین هر استوری نمایش داده می‌شود.', 'storyino'); ?>
                                    </p>
                                </div>

                                <div>
                                    <span class="stn:mb-1.5 stn:block stn:text-sm stn:font-medium stn:text-zinc-800">
                                        <?php esc_html_e('ظاهر دکمه استوری', 'storyino'); ?>
                                    </span>
                                    <div class="stn:inline-flex stn:rounded-full stn:border stn:border-fuchsia-100 stn:bg-fuchsia-50/70 stn:p-1">
                                        <label class="stn:m-0 stn:cursor-pointer">
                                            <input type="radio" name="storyino_button_style" value="text" class="stn:peer stn:sr-only" <?php checked($button_style, 'text'); ?>>
                                            <span class="stn:block stn:rounded-full stn:px-3.5 stn:py-1.5 stn:text-sm stn:font-medium stn:text-fuchsia-700/60 stn:transition-colors stn:peer-checked:bg-white stn:peer-checked:text-fuchsia-700 stn:peer-checked:shadow-sm">
                                                <?php esc_html_e('متنی', 'storyino'); ?>
                                            </span>
                                        </label>
                                        <label class="stn:m-0 stn:cursor-pointer">
                                            <input type="radio" name="storyino_button_style" value="icon" class="stn:peer stn:sr-only" <?php checked($button_style, 'icon'); ?>>
                                            <span class="stn:block stn:rounded-full stn:px-3.5 stn:py-1.5 stn:text-sm stn:font-medium stn:text-fuchsia-700/60 stn:transition-colors stn:peer-checked:bg-white stn:peer-checked:text-fuchsia-700 stn:peer-checked:shadow-sm">
                                                <?php esc_html_e('آیکونی', 'storyino'); ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="stn:inline-flex stn:cursor-pointer stn:items-center stn:gap-3">
                                        <input type="checkbox" name="storyino_icon_animation" value="1" class="stn:peer stn:sr-only" <?php checked($icon_animation); ?>>
                                        <span class="stn-toggle-track stn:relative stn:h-6 stn:w-10 stn:shrink-0 stn:rounded-full stn:transition-colors stn:after:absolute stn:after:top-0.5 stn:after:start-0.5 stn:after:h-5 stn:after:w-5 stn:after:rounded-full stn:after:bg-white stn:after:shadow-sm stn:after:transition-all stn:peer-checked:after:start-[1.125rem]"></span>
                                        <span class="stn:text-sm stn:font-medium stn:text-zinc-800"><?php esc_html_e('انیمیشن چرخش آیکون', 'storyino'); ?></span>
                                    </label>
                                </div>

                                <div>
                                    <label class="stn:inline-flex stn:cursor-pointer stn:items-center stn:gap-3">
                                        <input type="checkbox" name="storyino_vazir_ui" value="1" class="stn:peer stn:sr-only" <?php checked($vazir_ui); ?>>
                                        <span class="stn-toggle-track stn:relative stn:h-6 stn:w-10 stn:shrink-0 stn:rounded-full stn:transition-colors stn:after:absolute stn:after:top-0.5 stn:after:start-0.5 stn:after:h-5 stn:after:w-5 stn:after:rounded-full stn:after:bg-white stn:after:shadow-sm stn:after:transition-all stn:peer-checked:after:start-[1.125rem]"></span>
                                        <span class="stn:text-sm stn:font-medium stn:text-zinc-800"><?php esc_html_e('فونت وزیرمتن برای عنوان دایره و دکمه لینک', 'storyino'); ?></span>
                                    </label>
                                    <p class="stn:mt-1.5 stn:text-xs stn:text-zinc-500"><?php esc_html_e('اگر خاموش باشد، عنوان دایره استوری و دکمه لینک داخل پلیر از فونت قالب استفاده می‌کنند.', 'storyino'); ?></p>
                                </div>
                            </div>

                            <div class="stn:mt-6 stn:border-t stn:border-fuchsia-100 stn:pt-4">
                                <button type="submit" name="storyino_save" value="1" class="stn-btn-save stn:inline-flex stn:h-10 stn:items-center stn:rounded-full stn:px-5 stn:text-sm stn:font-semibold">
                                    <?php esc_html_e('ذخیره تغییرات', 'storyino'); ?>
                                </button>
                            </div>
                        </section>
                    </div>

                    <?php storyino_render_shortcodes_docs($categories); ?>
                </div>
            </form>

            <template id="storyino-cat-template">
                <?php
                storyino_render_admin_category(
                    [
                        'slug'  => 'cat',
                        'title' => '',
                        'cover' => 0,
                        'ids'   => [],
                    ],
                    '__INDEX__'
                );
                ?>
            </template>
        </div>
    </div>
    <?php
}

function storyino_render_admin_category($category, $index)
{
    $title     = isset($category['title']) ? $category['title'] : '';
    $slug      = isset($category['slug']) ? $category['slug'] : 'cat';
    $cover     = isset($category['cover']) ? absint($category['cover']) : 0;
    $ids       = isset($category['ids']) ? (array) $category['ids'] : [];
    $cover_url = $cover ? wp_get_attachment_image_url($cover, 'thumbnail') : '';
    $empty     = empty($ids) ? ' is-empty' : '';
    $shortcode = '[storyino cat="' . $slug . '"]';
    $links     = storyino_get_option_links();
    ?>
    <section class="storyino-cat stn-card stn:rounded-xl stn:bg-white stn:p-5" data-index="<?php echo esc_attr((string) $index); ?>">
        <div class="stn:mb-4 stn:flex stn:flex-wrap stn:items-start stn:justify-between stn:gap-3">
            <div class="stn:flex stn:items-center stn:gap-3">
                <button type="button" class="storyino-cat-handle stn:flex stn:h-8 stn:w-8 stn:cursor-grab stn:items-center stn:justify-center stn:rounded-lg stn:bg-fuchsia-50 stn:text-fuchsia-500" aria-label="<?php esc_attr_e('جابجایی', 'storyino'); ?>">⋮⋮</button>
                <h3 class="stn:text-sm stn:font-semibold stn:text-zinc-900"><?php esc_html_e('دسته استوری', 'storyino'); ?></h3>
            </div>
            <button type="button" class="storyino-cat-remove stn:rounded-full stn:border stn:border-red-100 stn:bg-red-50 stn:px-3 stn:py-1 stn:text-xs stn:font-medium stn:text-red-600">
                <?php esc_html_e('حذف دسته', 'storyino'); ?>
            </button>
        </div>

        <div class="stn:mb-4 stn:flex stn:flex-wrap stn:items-center stn:gap-4">
            <div class="stn-cover-wrap">
                <button type="button" class="storyino-pick-cover stn-cover-btn" title="<?php esc_attr_e('تصویر دایره استوری', 'storyino'); ?>">
                    <?php if ($cover_url) : ?>
                        <img src="<?php echo esc_url($cover_url); ?>" alt="" class="storyino-cover-preview">
                    <?php else : ?>
                        <span class="storyino-cover-placeholder">+</span>
                    <?php endif; ?>
                </button>
                <button type="button" class="storyino-clear-cover storyino-clear-cover-fab" aria-label="<?php esc_attr_e('حذف تصویر', 'storyino'); ?>" <?php echo $cover ? '' : 'hidden'; ?>>×</button>
            </div>
            <input type="hidden" class="storyino-cover-id" name="storyino_cats[<?php echo esc_attr((string) $index); ?>][cover]" value="<?php echo esc_attr((string) $cover); ?>">
            <div>
                <div class="stn:text-sm stn:font-medium stn:text-zinc-800"><?php esc_html_e('تصویر دایره استوری', 'storyino'); ?></div>
                <p class="stn:mt-0.5 stn:text-xs stn:text-zinc-500"><?php esc_html_e('این عکس داخل حلقه گرادیانی اینستاگرام نمایش داده می‌شود.', 'storyino'); ?></p>
                <div class="stn:mt-2 stn:flex stn:flex-wrap stn:gap-2">
                    <button type="button" class="storyino-pick-cover stn:inline-flex stn:h-8 stn:items-center stn:rounded-full stn:border stn:border-fuchsia-200 stn:bg-white stn:px-3 stn:text-xs stn:font-medium stn:text-fuchsia-700">
                        <?php esc_html_e('انتخاب تصویر', 'storyino'); ?>
                    </button>
                    <button type="button" class="storyino-clear-cover stn:inline-flex stn:h-8 stn:items-center stn:rounded-full stn:border stn:border-red-200 stn:bg-red-50 stn:px-3 stn:text-xs stn:font-medium stn:text-red-600" <?php echo $cover ? '' : 'hidden'; ?>>
                        <?php esc_html_e('حذف تصویر', 'storyino'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="stn:mb-4 stn:grid stn:gap-3 stn:sm:grid-cols-2">
            <div>
                <label class="stn:mb-1.5 stn:block stn:text-sm stn:font-medium stn:text-zinc-800"><?php esc_html_e('نام دسته', 'storyino'); ?></label>
                <input type="text" class="storyino-cat-title stn:h-10 stn:w-full stn:rounded-lg stn:border stn:border-zinc-200 stn:bg-white stn:px-3 stn:text-sm" name="storyino_cats[<?php echo esc_attr((string) $index); ?>][title]" value="<?php echo esc_attr($title); ?>" placeholder="<?php esc_attr_e('مثلاً محصولات', 'storyino'); ?>">
            </div>
            <div>
                <label class="stn:mb-1.5 stn:block stn:text-sm stn:font-medium stn:text-zinc-800"><?php esc_html_e('شناسه شورت‌کد', 'storyino'); ?></label>
                <input type="text" class="storyino-cat-slug stn:h-10 stn:w-full stn:rounded-lg stn:border stn:border-zinc-200 stn:bg-white stn:px-3 stn:text-sm" name="storyino_cats[<?php echo esc_attr((string) $index); ?>][slug]" value="<?php echo esc_attr($slug); ?>" dir="ltr" placeholder="products">
            </div>
        </div>

        <div class="stn:mb-4 stn:flex stn:items-center stn:gap-2">
            <code class="storyino-cat-shortcode stn-code stn:min-w-0 stn:flex-1 stn:overflow-x-auto stn:rounded-md stn:px-2.5 stn:py-2 stn:text-xs" dir="ltr"><?php echo esc_html($shortcode); ?></code>
            <button type="button" class="storyino-copy stn:h-8 stn:shrink-0 stn:rounded-full stn:border stn:border-fuchsia-100 stn:bg-white stn:px-2.5 stn:text-xs stn:font-medium stn:text-zinc-700" data-copy="self"><?php esc_html_e('کپی', 'storyino'); ?></button>
        </div>

        <input type="hidden" class="storyino-story-ids" name="storyino_cats[<?php echo esc_attr((string) $index); ?>][ids]" value="<?php echo esc_attr(implode(',', $ids)); ?>">

        <div class="stn:mb-3 stn:flex stn:flex-wrap stn:items-center stn:gap-3">
            <button type="button" class="storyino-open-media stn-btn-story stn:inline-flex stn:h-9 stn:items-center stn:gap-2 stn:rounded-full stn:px-3.5 stn:text-sm stn:font-semibold">
                + <?php esc_html_e('انتخاب از کتابخانه رسانه', 'storyino'); ?>
            </button>
            <span class="storyino-count stn-count stn:rounded-full stn:px-2.5 stn:py-1 stn:text-xs stn:font-semibold"></span>
        </div>

        <ul class="storyino-list<?php echo esc_attr($empty); ?> stn:grid stn:grid-cols-2 stn:gap-3 stn:sm:grid-cols-3 stn:lg:grid-cols-4"><?php
            foreach ($ids as $id) {
                storyino_render_admin_item($id, $links);
            }
        ?></ul>
    </section>
    <?php
}

function storyino_render_shortcodes_docs($categories)
{
    $cat_examples = [];

    foreach ((array) $categories as $i => $category) {
        $slug = isset($category['slug']) ? $category['slug'] : '';
        $title = isset($category['title']) && $category['title'] !== '' ? $category['title'] : __('بدون عنوان', 'storyino');

        if ($slug === '') {
            continue;
        }

        $cat_examples[] = [
            'id'    => 'storyino-sc-cat-' . $i,
            'title' => $title,
            'desc'  => __('فقط استوری‌های همین دسته را به‌صورت یک دایره اینستاگرامی نشان می‌دهد.', 'storyino'),
            'code'  => '[storyino cat="' . $slug . '"]',
        ];
    }

    $examples = array_merge(
        [
            [
                'id'    => 'storyino-sc-basic',
                'title' => __('همه دسته‌ها', 'storyino'),
                'desc'  => __('لیست دایره‌ای همه دسته‌بندی‌ها، مثل استوری اینستاگرام.', 'storyino'),
                'code'  => '[storyino]',
            ],
        ],
        $cat_examples,
        [
            [
                'id'    => 'storyino-sc-speed',
                'title' => __('شبیه‌سازی سرعت دانلود', 'storyino'),
                'desc'  => __('فقط برای دمو. مقدار بر حسب KB/s است. پیش‌فرض صفر است و فایل با سرعت اینترنت کاربر دانلود می‌شود.', 'storyino'),
                'code'  => '[storyino speed="250"]',
            ],
            [
                'id'    => 'storyino-sc-duration',
                'title' => __('زمان نمایش عکس‌ها', 'storyino'),
                'desc'  => __('مدت نمایش هر عکس بر حسب میلی‌ثانیه است.', 'storyino'),
                'code'  => '[storyino duration="6000"]',
            ],
        ]
    );
    ?>
    <aside class="stn-card stn:rounded-xl stn:bg-white stn:p-5">
        <h2 class="stn-heading stn:text-sm stn:font-semibold stn:text-zinc-900"><?php esc_html_e('شورت‌کدها', 'storyino'); ?></h2>
        <p class="stn:mt-1 stn:text-sm stn:text-zinc-500">
            <?php esc_html_e('شورت‌کد اصلی همه دسته‌ها را نشان می‌دهد. هر دسته هم شورت‌کد اختصاصی خودش را دارد.', 'storyino'); ?>
        </p>

        <h3 class="stn:mt-5 stn:mb-2 stn:text-xs stn:font-semibold stn:uppercase stn:tracking-wide stn:text-fuchsia-600/70"><?php esc_html_e('پارامترها', 'storyino'); ?></h3>

        <div class="stn:overflow-hidden stn:rounded-lg stn:border stn:border-fuchsia-100">
            <table class="storyino-table stn:w-full stn:border-collapse stn:text-sm">
                <thead>
                    <tr class="stn:bg-fuchsia-50/70 stn:text-right">
                        <th class="stn:px-3 stn:py-2 stn:font-medium stn:text-zinc-600"><?php esc_html_e('پارامتر', 'storyino'); ?></th>
                        <th class="stn:px-3 stn:py-2 stn:font-medium stn:text-zinc-600"><?php esc_html_e('توضیح', 'storyino'); ?></th>
                        <th class="stn:px-3 stn:py-2 stn:font-medium stn:text-zinc-600"><?php esc_html_e('مثال', 'storyino'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="stn:border-t stn:border-fuchsia-50">
                        <td class="stn:px-3 stn:py-2"><code class="stn-chip stn:rounded stn:px-1.5 stn:py-0.5 stn:text-xs">cat</code></td>
                        <td class="stn:px-3 stn:py-2 stn:text-zinc-600"><?php esc_html_e('شناسه دسته برای شورت‌کد اختصاصی', 'storyino'); ?></td>
                        <td class="stn:px-3 stn:py-2"><code class="stn-chip stn:rounded stn:px-1.5 stn:py-0.5 stn:text-xs" dir="ltr">cat="products"</code></td>
                    </tr>
                    <tr class="stn:border-t stn:border-fuchsia-50">
                        <td class="stn:px-3 stn:py-2"><code class="stn-chip stn:rounded stn:px-1.5 stn:py-0.5 stn:text-xs">ids</code></td>
                        <td class="stn:px-3 stn:py-2 stn:text-zinc-600"><?php esc_html_e('آی‌دی فایل‌های رسانه، جداشده با کاما', 'storyino'); ?></td>
                        <td class="stn:px-3 stn:py-2"><code class="stn-chip stn:rounded stn:px-1.5 stn:py-0.5 stn:text-xs" dir="ltr">ids="12,18,25"</code></td>
                    </tr>
                    <tr class="stn:border-t stn:border-fuchsia-50">
                        <td class="stn:px-3 stn:py-2"><code class="stn-chip stn:rounded stn:px-1.5 stn:py-0.5 stn:text-xs">speed</code></td>
                        <td class="stn:px-3 stn:py-2 stn:text-zinc-600"><?php esc_html_e('شبیه‌سازی سرعت دانلود بر حسب KB/s؛ صفر یعنی سرعت واقعی کاربر', 'storyino'); ?></td>
                        <td class="stn:px-3 stn:py-2"><code class="stn-chip stn:rounded stn:px-1.5 stn:py-0.5 stn:text-xs" dir="ltr">speed="0"</code></td>
                    </tr>
                    <tr class="stn:border-t stn:border-fuchsia-50">
                        <td class="stn:px-3 stn:py-2"><code class="stn-chip stn:rounded stn:px-1.5 stn:py-0.5 stn:text-xs">duration</code></td>
                        <td class="stn:px-3 stn:py-2 stn:text-zinc-600"><?php esc_html_e('مدت نمایش هر عکس بر حسب میلی‌ثانیه', 'storyino'); ?></td>
                        <td class="stn:px-3 stn:py-2"><code class="stn-chip stn:rounded stn:px-1.5 stn:py-0.5 stn:text-xs" dir="ltr">duration="6000"</code></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h3 class="stn:mt-5 stn:mb-2 stn:text-xs stn:font-semibold stn:uppercase stn:tracking-wide stn:text-fuchsia-600/70"><?php esc_html_e('نمونه‌های آماده', 'storyino'); ?></h3>

        <div class="stn:grid stn:gap-2.5">
            <?php foreach ($examples as $example) : ?>
                <div class="stn:rounded-lg stn:border stn:border-fuchsia-100 stn:bg-fuchsia-50/40 stn:p-3">
                    <strong class="stn:block stn:text-sm stn:font-medium stn:text-zinc-900"><?php echo esc_html($example['title']); ?></strong>
                    <p class="stn:mt-0.5 stn:mb-2.5 stn:text-xs stn:leading-5 stn:text-zinc-500"><?php echo esc_html($example['desc']); ?></p>
                    <div class="stn:flex stn:items-center stn:gap-2">
                        <code id="<?php echo esc_attr($example['id']); ?>" class="stn-code stn:min-w-0 stn:flex-1 stn:overflow-x-auto stn:rounded-md stn:px-2.5 stn:py-2 stn:text-xs stn:whitespace-nowrap" dir="ltr"><?php echo esc_html($example['code']); ?></code>
                        <button type="button" class="storyino-copy stn:h-8 stn:shrink-0 stn:rounded-full stn:border stn:border-fuchsia-100 stn:bg-white stn:px-2.5 stn:text-xs stn:font-medium stn:text-zinc-700" data-copy="<?php echo esc_attr($example['id']); ?>">
                            <?php esc_html_e('کپی', 'storyino'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="stn-note stn:mt-4 stn:rounded-lg stn:px-3 stn:py-2.5 stn:text-xs stn:leading-5">
            <?php esc_html_e('اگر از صفحه‌سازهایی مثل المنتور استفاده می‌کنی، شورت‌کد را داخل ویجت Shortcode یا HTML قرار بده.', 'storyino'); ?>
        </p>
    </aside>
    <?php
}

function storyino_render_admin_item($id, $links = null)
{
    $mime     = (string) get_post_mime_type($id);
    $is_video = strpos($mime, 'video/') === 0;
    $preview  = storyino_get_attachment_preview_url($id);
    $has_poster = $preview && strpos($preview, '/wp-includes/images/media/') === false;
    $video_url = ($is_video && ! $has_poster) ? wp_get_attachment_url($id) : '';

    if (! is_array($links)) {
        $links = storyino_get_option_links();
    }
    $current_link = isset($links[$id]) ? $links[$id] : '';
    $type_class = $is_video
        ? 'stn:bg-indigo-600/85'
        : 'stn:bg-fuchsia-600/85';
    ?>
    <li class="storyino-item stn:group stn:overflow-hidden stn:rounded-lg stn:border stn:border-zinc-200 stn:bg-white" data-id="<?php echo esc_attr($id); ?>" data-type="<?php echo $is_video ? 'video' : 'image'; ?>">
        <div class="stn:relative stn:bg-zinc-900">
            <?php if ($is_video && $video_url) : ?>
                <video src="<?php echo esc_url($video_url); ?>" class="storyino-media-preview" muted playsinline preload="metadata"></video>
            <?php elseif ($preview) : ?>
                <img src="<?php echo esc_url($preview); ?>" alt="" class="storyino-media-preview">
            <?php else : ?>
                <img src="<?php echo esc_url(wp_mime_type_icon($id)); ?>" alt="" class="storyino-media-preview is-icon">
            <?php endif; ?>
            <?php if ($is_video) : ?>
                <span class="storyino-play-mark" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="storyino-type stn:absolute stn:top-2 stn:end-2 stn:rounded-full <?php echo esc_attr($type_class); ?> stn:px-1.5 stn:py-0.5 stn:text-[10px] stn:font-medium stn:text-white">
                <?php echo $is_video ? esc_html__('ویدیو', 'storyino') : esc_html__('تصویر', 'storyino'); ?>
            </span>
            <button type="button" class="storyino-remove stn:absolute stn:top-2 stn:start-2 stn:flex stn:h-6 stn:w-6 stn:items-center stn:justify-center stn:rounded stn:bg-black/60 stn:text-sm stn:leading-none stn:text-white stn:opacity-0 stn:transition-opacity stn:group-hover:opacity-100 stn:hover:bg-red-600" aria-label="<?php esc_attr_e('حذف', 'storyino'); ?>">×</button>
        </div>
        <input
            type="url"
            name="storyino_story_links[<?php echo esc_attr($id); ?>]"
            class="storyino-link-input stn:h-8 stn:w-full stn:border-t stn:border-zinc-100 stn:bg-white stn:px-2 stn:text-[11px] stn:text-zinc-700"
            placeholder="<?php esc_attr_e('لینک مقصد', 'storyino'); ?>"
            value="<?php echo esc_attr($current_link); ?>"
            dir="ltr">
    </li>
    <?php
}
