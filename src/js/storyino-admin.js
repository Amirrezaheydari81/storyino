jQuery(function ($) {
    'use strict';

    const $idsInput = $('#storyino-story-ids');
    const $list = $('#storyino-list');
    const $count = $('.storyino-count');
    let frame = null;

    const getIds = () => {
        const value = $idsInput.val();
        return value ? value.toString().split(',').map(Number).filter(Boolean) : [];
    };

    const setIds = (ids) => {
        $idsInput.val(ids.join(','));
        updateCount();
    };

    const updateCount = () => {
        $count.text(getIds().length + ' آیتم');
    };

    const addItem = (att) => {
        const thumb =
            (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ||
            att.image ||
            att.icon ||
            '';

        const isVideo = att.type === 'video';

        const $item = $(
            '<li class="storyino-item" data-id="' + att.id + '">' +
            '<img src="' + thumb + '" alt="">' +
            '<span class="storyino-type">' + (isVideo ? 'ویدیو' : 'تصویر') + '</span>' +
            '<button type="button" class="storyino-remove" aria-label="حذف">×</button>' +
            '<input type="url" name="storyino_story_links[' + att.id + ']" ' +
            'class="storyino-link-input" placeholder="لینک مقصد" value="">' +
            '</li>'
        );

        $list.append($item);
    };

    $('#storyino-open-media').on('click', function (e) {
        e.preventDefault();

        if (frame) {
            frame.state().get('selection').reset();
            frame.open();
            return;
        }

        frame = wp.media({
            title: storyinoAdmin.frameTitle,
            button: { text: storyinoAdmin.frameButton },
            multiple: true,
            library: { type: ['image', 'video'] },
        });

        frame.on('select', function () {
            const selected = frame.state().get('selection').map((model) => model.toJSON());
            const ids = getIds();

            selected.forEach((att) => {
                if (ids.indexOf(att.id) === -1) {
                    ids.push(att.id);
                    addItem(att);
                }
            });

            setIds(ids);
        });

        frame.open();
    });

    $list.on('click', '.storyino-remove', function () {
        const id = Number($(this).closest('.storyino-item').data('id'));
        $(this).closest('.storyino-item').remove();
        setIds(getIds().filter((item) => item !== id));
    });

    $list.sortable({
        placeholder: 'storyino-placeholder',
        update: function () {
            const ids = $list.children('.storyino-item').map(function () {
                return Number($(this).data('id'));
            }).get();

            setIds(ids);
        },
    });

    updateCount();
});
(function () {
    const copyButtons = document.querySelectorAll('.storyino-copy');

    copyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = this.getAttribute('data-copy');
            const codeElement = document.getElementById(targetId);

            if (!codeElement) {
                return;
            }

            const text = codeElement.textContent.trim();
            const originalText = this.textContent;
            const buttonElement = this;

            const showCopied = function () {
                buttonElement.textContent = (typeof storyinoAdmin !== 'undefined' && storyinoAdmin.copiedText)
                    ? storyinoAdmin.copiedText
                    : 'کپی شد';
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
        } catch (e) { }

        textarea.remove();
    }
})();