jQuery(function ($) {
    'use strict';

    const labels = window.storyinoAdmin || {};
    const $cats = $('#storyino-cats');
    let mediaFrame = null;
    let coverFrame = null;
    let activeCat = null;
    let nextIndex = $cats.children('.storyino-cat').length;

    const getIds = ($cat) => {
        const value = $cat.find('.storyino-story-ids').val();
        return value ? value.toString().split(',').map(Number).filter(Boolean) : [];
    };

    const setIds = ($cat, ids) => {
        $cat.find('.storyino-story-ids').val(ids.join(','));
        updateCount($cat);
        syncEmpty($cat);
    };

    const updateCount = ($cat) => {
        $cat.find('.storyino-count').text(getIds($cat).length + ' آیتم');
    };

    const syncEmpty = ($cat) => {
        $cat.find('.storyino-list').toggleClass('is-empty', $cat.find('.storyino-item').length === 0);
    };

    const updateShortcode = ($cat) => {
        const slug = ($cat.find('.storyino-cat-slug').val() || 'cat').toString();
        $cat.find('.storyino-cat-shortcode').text('[storyino cat="' + slug + '"]');
    };

    const escapeAttr = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const getAttachmentThumb = (att) => {
        return (att.sizes && att.sizes.medium && att.sizes.medium.url)
            || (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url)
            || (att.image && (att.image.src || att.image.url))
            || (att.thumb && att.thumb.src)
            || '';
    };

    const isWpMediaIcon = (url) => {
        return !url || /\/wp-includes\/images\/media\//.test(url);
    };

    const mediaPreviewHtml = (att) => {
        const isVideo = att.type === 'video';
        const thumb = getAttachmentThumb(att);
        const play = isVideo ? '<span class="storyino-play-mark" aria-hidden="true"></span>' : '';

        if (isVideo && att.url && isWpMediaIcon(thumb)) {
            return '<video src="' + escapeAttr(att.url) + '" class="storyino-media-preview" muted playsinline preload="metadata"></video>' + play;
        }

        const src = thumb || att.icon || '';

        return '<img src="' + escapeAttr(src) + '" alt="" class="storyino-media-preview">' + play;
    };

    const primeVideos = ($scope) => {
        $scope.find('video.storyino-media-preview').each(function () {
            const video = this;
            const seek = function () {
                try {
                    if (video.currentTime < 0.1) {
                        video.currentTime = 0.2;
                    }
                } catch (e) { }
            };

            if (video.readyState >= 2) {
                seek();
                return;
            }

            video.addEventListener('loadeddata', seek, { once: true });
            video.addEventListener('loadedmetadata', seek, { once: true });
        });
    };

    const setCoverPreview = ($cat, url) => {
        if (url) {
            $cat.find('.stn-cover-btn').html('<img src="' + escapeAttr(url) + '" alt="" class="storyino-cover-preview">');
            $cat.find('.storyino-clear-cover').prop('hidden', false);
        } else {
            $cat.find('.stn-cover-btn').html('<span class="storyino-cover-placeholder">+</span>');
            $cat.find('.storyino-cover-id').val('0');
            $cat.find('.storyino-clear-cover').prop('hidden', true);
        }
    };

    const addItem = ($cat, att) => {
        const isVideo = att.type === 'video';
        const typeLabel = isVideo ? (labels.videoLabel || 'ویدیو') : (labels.imageLabel || 'تصویر');
        const typeClass = isVideo ? 'stn:bg-indigo-600/85' : 'stn:bg-fuchsia-600/85';

        const $item = $(
            '<li class="storyino-item stn:group stn:overflow-hidden stn:rounded-lg stn:border stn:border-zinc-200 stn:bg-white" data-id="' + Number(att.id) + '" data-type="' + (isVideo ? 'video' : 'image') + '">' +
                '<div class="stn:relative stn:bg-zinc-900">' +
                    mediaPreviewHtml(att) +
                    '<span class="storyino-type stn:absolute stn:top-2 stn:end-2 stn:rounded-full ' + typeClass + ' stn:px-1.5 stn:py-0.5 stn:text-[10px] stn:font-medium stn:text-white">' + escapeAttr(typeLabel) + '</span>' +
                    '<button type="button" class="storyino-remove stn:absolute stn:top-2 stn:start-2 stn:flex stn:h-6 stn:w-6 stn:items-center stn:justify-center stn:rounded stn:bg-black/60 stn:text-sm stn:leading-none stn:text-white stn:opacity-0 stn:transition-opacity stn:group-hover:opacity-100 stn:hover:bg-red-600" aria-label="' + escapeAttr(labels.removeLabel || 'حذف') + '">×</button>' +
                '</div>' +
                '<input type="url" name="storyino_story_links[' + Number(att.id) + ']" ' +
                    'class="storyino-link-input stn:h-8 stn:w-full stn:border-t stn:border-zinc-100 stn:bg-white stn:px-2 stn:text-[11px] stn:text-zinc-700" ' +
                    'placeholder="' + escapeAttr(labels.linkPlaceholder || 'لینک مقصد') + '" value="" dir="ltr">' +
            '</li>'
        );

        $cat.find('.storyino-list').append($item);
        primeVideos($item);
    };

    const initListSortable = ($cat) => {
        $cat.find('.storyino-list').sortable({
            placeholder: 'storyino-placeholder',
            items: '.storyino-item',
            update: function () {
                const ids = $(this).children('.storyino-item').map(function () {
                    return Number($(this).data('id'));
                }).get();
                setIds($cat, ids);
            },
        });
    };

    const initAll = () => {
        $cats.children('.storyino-cat').each(function () {
            const $cat = $(this);
            initListSortable($cat);
            updateCount($cat);
            syncEmpty($cat);
            updateShortcode($cat);
            primeVideos($cat);
        });
    };

    $cats.sortable({
        handle: '.storyino-cat-handle',
        items: '> .storyino-cat',
        placeholder: 'storyino-cat-placeholder',
    });

    $('#storyino-add-cat').on('click', function () {
        const template = document.getElementById('storyino-cat-template');
        if (!template) return;

        const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
        const $cat = $(html);
        $cat.find('.storyino-cat-title').val(labels.newCategory || 'دسته جدید');
        $cat.find('.storyino-cat-slug').val('cat-' + nextIndex);
        $cats.append($cat);
        initListSortable($cat);
        updateCount($cat);
        syncEmpty($cat);
        updateShortcode($cat);
    });

    $cats.on('click', '.storyino-cat-remove', function () {
        if ($cats.children('.storyino-cat').length <= 1) {
            return;
        }

        if (window.confirm(labels.confirmDelete || 'این دسته حذف شود؟')) {
            $(this).closest('.storyino-cat').remove();
        }
    });

    $cats.on('input', '.storyino-cat-slug', function () {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');
        updateShortcode($(this).closest('.storyino-cat'));
    });

    $cats.on('click', '.storyino-open-media', function (e) {
        e.preventDefault();
        activeCat = $(this).closest('.storyino-cat');

        if (mediaFrame) {
            mediaFrame.state().get('selection').reset();
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: labels.frameTitle,
            button: { text: labels.frameButton },
            multiple: true,
            library: { type: ['image', 'video'] },
        });

        mediaFrame.on('select', function () {
            if (!activeCat || !activeCat.length) return;

            const selected = mediaFrame.state().get('selection').map((model) => model.toJSON());
            const ids = getIds(activeCat);

            selected.forEach((att) => {
                if (ids.indexOf(att.id) === -1) {
                    ids.push(att.id);
                    addItem(activeCat, att);
                }
            });

            setIds(activeCat, ids);
        });

        mediaFrame.open();
    });

    $cats.on('click', '.storyino-pick-cover', function (e) {
        e.preventDefault();
        activeCat = $(this).closest('.storyino-cat');

        if (coverFrame) {
            coverFrame.state().get('selection').reset();
            coverFrame.open();
            return;
        }

        coverFrame = wp.media({
            title: labels.coverTitle,
            button: { text: labels.coverButton },
            multiple: false,
            library: { type: ['image'] },
        });

        coverFrame.on('select', function () {
            if (!activeCat || !activeCat.length) return;

            const att = coverFrame.state().get('selection').first().toJSON();
            const thumb =
                (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ||
                att.url ||
                '';

            activeCat.find('.storyino-cover-id').val(att.id);
            setCoverPreview(activeCat, thumb);
            clearCoverWarning(activeCat);
        });

        coverFrame.open();
    });

    $cats.on('click', '.storyino-clear-cover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $cat = $(this).closest('.storyino-cat');
        setCoverPreview($cat, '');
    });

    $cats.on('click', '.storyino-remove', function () {
        const $cat = $(this).closest('.storyino-cat');
        const id = Number($(this).closest('.storyino-item').data('id'));
        $(this).closest('.storyino-item').remove();
        setIds($cat, getIds($cat).filter((item) => item !== id));
    });

    const clearCoverWarning = ($cat) => {
        if ($cat) {
            $cat.find('.stn-cover-btn').removeClass('is-warning');
        } else {
            $cats.find('.stn-cover-btn').removeClass('is-warning');
        }

        const warning = document.getElementById('storyino-cover-warning');
        if (warning && !$cats.find('.stn-cover-btn.is-warning').length) {
            warning.hidden = true;
            warning.textContent = '';
        }
    };

    const findCoverProblems = () => {
        const problems = [];

        $cats.children('.storyino-cat').each(function () {
            const $cat = $(this);
            const cover = Number($cat.find('.storyino-cover-id').val()) || 0;
            const firstType = $cat.find('.storyino-item').first().attr('data-type');

            if (!cover && firstType === 'video') {
                problems.push($cat);
            }
        });

        return problems;
    };

    $('#storyino-admin form').on('submit', function (e) {
        clearCoverWarning();

        const problems = findCoverProblems();
        if (!problems.length) {
            return;
        }

        e.preventDefault();

        const names = problems.map(($cat) => {
            $cat.find('.stn-cover-btn').addClass('is-warning');
            return ($cat.find('.storyino-cat-title').val() || labels.untitledCategory || 'بدون عنوان').toString().trim();
        });

        const warning = document.getElementById('storyino-cover-warning');
        const base = labels.coverRequired || 'اولین آیتم دسته «%s» ویدیو است. قبل از ذخیره، تصویر دایره استوری را انتخاب کن.';

        if (warning) {
            warning.innerHTML = '';
            names.forEach((name) => {
                const p = document.createElement('p');
                p.textContent = base.replace('%s', name);
                warning.appendChild(p);
            });
            warning.hidden = false;
            warning.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        problems[0].find('.stn-cover-btn')[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        problems[0].find('.storyino-pick-cover').trigger('focus');
    });

    initAll();
});

(function () {
    document.addEventListener('click', function (e) {
        const button = e.target.closest('.storyino-copy');
        if (!button) return;

        const targetId = button.getAttribute('data-copy');
        let text = '';

        if (targetId === 'self') {
            const code = button.parentElement ? button.parentElement.querySelector('code') : null;
            text = code ? code.textContent.trim() : '';
        } else {
            const codeElement = document.getElementById(targetId);
            text = codeElement ? codeElement.textContent.trim() : '';
        }

        if (!text) return;

        const originalText = button.textContent;

        const showCopied = function () {
            button.textContent = (typeof storyinoAdmin !== 'undefined' && storyinoAdmin.copiedText)
                ? storyinoAdmin.copiedText
                : 'کپی شد';
            button.classList.add('storyino-copied');

            setTimeout(function () {
                button.textContent = originalText;
                button.classList.remove('storyino-copied');
            }, 1500);
        };

        const fallbackCopy = function (value) {
            const textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
            } catch (err) { }
            textarea.remove();
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(showCopied).catch(function () {
                fallbackCopy(text);
                showCopied();
            });
        } else {
            fallbackCopy(text);
            showCopied();
        }
    });
})();
