(() => {
    'use strict';

    const sleep = (ms, signal) => new Promise((resolve, reject) => {
        if (signal?.aborted) {
            return reject(new DOMException('Aborted', 'AbortError'));
        }

        let timer;

        const onAbort = () => {
            clearTimeout(timer);
            reject(new DOMException('Aborted', 'AbortError'));
        };

        timer = setTimeout(() => {
            signal?.removeEventListener('abort', onAbort);
            resolve();
        }, ms);

        signal?.addEventListener('abort', onAbort, { once: true });
    });

    const i18n = window.storyinoI18n || {};

    const isSafeUrl = (value, extraProtocols) => {
        if (typeof value !== 'string' || value === '') {
            return '';
        }

        try {
            const parsed = new URL(value, window.location.href);
            const allowed = extraProtocols || ['http:', 'https:'];

            if (allowed.indexOf(parsed.protocol) === -1) {
                return '';
            }

            return parsed.href;
        } catch (e) {
            return '';
        }
    };

    class StoryinoPlayer {
        constructor(config, trigger) {
            if (window.storyinoActivePlayer) {
                window.storyinoActivePlayer.close();
            }

            this.trigger = trigger || null;

            this.stories = Array.isArray(config.stories)
                ? config.stories.filter((item) => item && isSafeUrl(item.src))
                : [];

            this.simulateSpeed = Number(config.simulateSpeed) || 0;
            this.imageDuration = Number(config.imageDuration) || 5000;
            this.fallbackVideoDuration = Number(config.fallbackVideoDuration) || 10000;

            this.strings = Object.assign(
                {
                    close: 'بستن',
                    previous: 'قبلی',
                    next: 'بعدی',
                    error: 'خطا در بارگذاری',
                    noStories: 'استوری پیدا نشد',
                    link: 'مشاهده',
                },
                i18n,
                config.strings || {}
            );

            this.current = 0;
            this.session = 0;
            this.aborter = null;
            this.raf = null;
            this.videoEl = null;
            this.objectUrl = null;
            this.fills = [];
            this.consecutiveErrors = 0;
            this.closed = false;
            this.prefetched = new Set();
            this.prefetchNodes = [];

            this.build();
            this.bindEvents();

            document.body.appendChild(this.overlay);
            document.body.classList.add('storyino-no-scroll');

            window.storyinoActivePlayer = this;

            this.show(0);
        }

        build() {
            this.overlay = document.createElement('div');
            this.overlay.className = 'storyino-overlay';

            const ui = window.storyinoUi || {};
            const useVazir = ui.useVazir === true || ui.useVazir === 1 || ui.useVazir === '1';

            if (useVazir || (this.trigger && this.trigger.closest('.storyino-use-vazir'))) {
                this.overlay.classList.add('storyino-use-vazir');
            }

            this.overlay.innerHTML = `
        <div class="storyino-story">
          <div class="storyino-segments"></div>
          <div class="storyino-media"></div>

          <div class="storyino-loader">
            <div class="storyino-loader-box">
              <div class="storyino-spinner"></div>
              <div class="storyino-percent"></div>
            </div>
          </div>

          <button type="button" class="storyino-close">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </button>
          <button type="button" class="storyino-nav storyino-prev" data-action="prev"></button>
          <button type="button" class="storyino-nav storyino-next" data-action="next"></button>
        </div>
      `;

            this.storyEl = this.overlay.querySelector('.storyino-story');
            this.mediaEl = this.overlay.querySelector('.storyino-media');
            this.loaderEl = this.overlay.querySelector('.storyino-loader');
            this.percentEl = this.overlay.querySelector('.storyino-percent');

            this.overlay.querySelector('.storyino-close').setAttribute('aria-label', this.strings.close);
            this.overlay.querySelector('.storyino-prev').setAttribute('aria-label', this.strings.previous);
            this.overlay.querySelector('.storyino-next').setAttribute('aria-label', this.strings.next);

            const segmentsEl = this.overlay.querySelector('.storyino-segments');

            this.stories.forEach(() => {
                const segment = document.createElement('i');
                const fill = document.createElement('b');
                segment.appendChild(fill);
                segmentsEl.appendChild(segment);
                this.fills.push(fill);
            });
        }

        bindEvents() {
            this.onKeyDown = (e) => {
                if (e.key === 'Escape') {
                    this.close();
                    return;
                }

                const isRTL = document.documentElement.dir === 'rtl';

                if (e.key === 'ArrowRight') {
                    isRTL ? this.prev() : this.next();
                }

                if (e.key === 'ArrowLeft') {
                    isRTL ? this.next() : this.prev();
                }
            };

            document.addEventListener('keydown', this.onKeyDown);

            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) {
                    this.close();
                    return;
                }

                if (e.target.closest('.storyino-close')) {
                    this.close();
                    return;
                }

                const actionButton = e.target.closest('[data-action]');
                if (!actionButton) return;

                if (actionButton.dataset.action === 'next') {
                    this.next();
                }

                if (actionButton.dataset.action === 'prev') {
                    this.prev();
                }
            });

            let touchStartX = 0;
            let touchStartY = 0;

            this.storyEl.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            }, { passive: true });

            this.storyEl.addEventListener('touchend', (e) => {
                const dx = e.changedTouches[0].clientX - touchStartX;
                const dy = e.changedTouches[0].clientY - touchStartY;

                if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
                    if (dx < 0) {
                        this.next();
                    } else {
                        this.prev();
                    }
                }
            }, { passive: true });
        }

        close() {
            if (this.closed) return;

            this.closed = true;
            this.session++;

            if (this.aborter) {
                this.aborter.abort();
            }

            this.cancelTimer();
            this.cleanupMedia();
            this.cleanupPrefetch();

            document.removeEventListener('keydown', this.onKeyDown);

            this.overlay.remove();
            document.body.classList.remove('storyino-no-scroll');

            if (window.storyinoActivePlayer === this) {
                window.storyinoActivePlayer = null;
            }
        }

        cancelTimer() {
            cancelAnimationFrame(this.raf);
            this.raf = null;
        }

        cleanupMedia() {
            if (this.videoEl) {
                this.videoEl.pause();
                this.videoEl.removeAttribute('src');
                this.videoEl.load();
                this.videoEl = null;
            }

            if (this.mediaEl) {
                this.mediaEl.innerHTML = '';
            }

            if (this.storyEl) {
                const linkBtn = this.storyEl.querySelector('.storyino-link-btn');
                if (linkBtn) {
                    linkBtn.remove();
                }
            }

            if (this.objectUrl) {
                const url = this.objectUrl;
                this.objectUrl = null;

                setTimeout(() => {
                    URL.revokeObjectURL(url);
                }, 500);
            }
        }

        cleanupPrefetch() {
            this.prefetchNodes.forEach((el) => {
                if (el.tagName === 'VIDEO') {
                    el.pause();
                    el.removeAttribute('src');
                    el.load();
                } else {
                    el.src = '';
                }
            });

            this.prefetchNodes = [];
            this.prefetched.clear();
        }

        prefetchNext(index) {
            if (!this.stories.length) return;

            const next = this.stories[((index + 1) % this.stories.length + this.stories.length) % this.stories.length];
            const src = isSafeUrl(next?.src);

            if (!src || this.prefetched.has(src)) return;

            this.prefetched.add(src);

            if (next.type === 'video') {
                const video = document.createElement('video');
                video.muted = true;
                video.playsInline = true;
                video.preload = 'auto';
                video.src = src;
                this.prefetchNodes.push(video);
                return;
            }

            const img = new Image();
            img.decoding = 'async';
            img.src = src;
            this.prefetchNodes.push(img);
        }

        renderLinkButton(link) {
            const href = isSafeUrl(link);

            if (!href) return;

            const a = document.createElement('a');
            a.href = href;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.className = 'storyino-link-btn';
            a.textContent = this.strings.link;

            this.storyEl.appendChild(a);
        }

        renderSegments(progress = 0) {
            this.fills.forEach((fill, index) => {
                if (index < this.current) {
                    fill.style.transform = 'scaleX(1)';
                } else if (index > this.current) {
                    fill.style.transform = 'scaleX(0)';
                } else {
                    const p = Math.max(0, Math.min(1, progress));
                    fill.style.transform = `scaleX(${p})`;
                }
            });
        }

        setLoading(show, ratio = null) {
            if (!this.loaderEl) return;

            this.loaderEl.classList.toggle('storyino-hidden', !show);

            if (!show) return;

            this.percentEl.textContent = ratio == null
                ? ''
                : `${Math.round(ratio * 100)}%`;
        }

        async downloadFully(url, signal, onProgress) {
            const res = await fetch(url, {
                cache: 'force-cache',
                credentials: 'same-origin',
                signal,
            });

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            const total = Number(res.headers.get('content-length')) || 0;
            const mime = res.headers.get('content-type') || '';

            if (this.simulateSpeed <= 0 || !res.body) {
                const blob = await res.blob();
                onProgress?.(1, blob.size, blob.size);
                return blob;
            }

            const reader = res.body.getReader();
            const chunks = [];
            let received = 0;
            const PIECE = 32 * 1024;

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                for (let offset = 0; offset < value.length; offset += PIECE) {
                    const part = value.subarray(offset, Math.min(offset + PIECE, value.length));

                    chunks.push(part);
                    received += part.length;

                    onProgress?.(total ? received / total : null, received, total);

                    const ms = (part.length / (this.simulateSpeed * 1024)) * 1000;
                    await sleep(ms, signal);
                }
            }

            return new Blob(chunks, mime ? { type: mime } : {});
        }

        async renderImage(url, session) {
            const img = document.createElement('img');
            img.alt = '';
            img.decoding = 'async';
            img.draggable = false;
            img.loading = 'eager';
            img.fetchPriority = 'high';

            const loaded = new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = () => reject(new Error('image load failed'));
            });

            img.src = url;
            await loaded;

            if (session !== this.session || this.closed) return;

            try {
                await img.decode?.();
            } catch { }

            if (session !== this.session || this.closed) return;

            this.mediaEl.appendChild(img);

            requestAnimationFrame(() => {
                img.classList.add('storyino-show');
            });
        }

        async renderVideo(url, session) {
            const video = document.createElement('video');

            video.muted = true;
            video.playsInline = true;
            video.preload = 'auto';
            video.setAttribute('muted', '');
            video.setAttribute('playsinline', '');
            video.setAttribute('fetchpriority', 'high');

            const ready = new Promise((resolve, reject) => {
                const ok = () => resolve();
                video.addEventListener('canplay', ok, { once: true });
                video.addEventListener('error', () => reject(new Error('video load failed')), { once: true });
                video.src = url;

                if (video.readyState >= 3) {
                    ok();
                }
            });

            await ready;

            if (session !== this.session || this.closed) return;

            this.mediaEl.appendChild(video);
            this.videoEl = video;

            requestAnimationFrame(() => {
                video.classList.add('storyino-show');
            });

            await video.play().catch(() => { });
        }

        startImageTimer(durationMs) {
            const duration = durationMs > 0 ? durationMs : this.imageDuration;
            const start = performance.now();

            const tick = (now) => {
                if (this.closed) return;

                const progress = (now - start) / duration;

                this.renderSegments(progress);

                if (progress >= 1) {
                    this.next();
                    return;
                }

                this.raf = requestAnimationFrame(tick);
            };

            this.raf = requestAnimationFrame(tick);
        }

        startVideoTimer() {
            const tick = () => {
                if (this.closed || !this.videoEl) return;

                const durationSec = Number.isFinite(this.videoEl.duration) && this.videoEl.duration > 0
                    ? this.videoEl.duration
                    : this.fallbackVideoDuration / 1000;

                const progress = this.videoEl.currentTime / durationSec;

                this.renderSegments(progress);

                if (this.videoEl.ended || progress >= 1) {
                    this.next();
                    return;
                }

                this.raf = requestAnimationFrame(tick);
            };

            this.raf = requestAnimationFrame(tick);
        }

        async playItem(url, item, session) {
            if (item.type === 'video') {
                await this.renderVideo(url, session);

                if (session !== this.session || this.closed) return;

                this.setLoading(false);
                this.consecutiveErrors = 0;
                this.renderLinkButton(item.link);
                this.startVideoTimer();
            } else {
                await this.renderImage(url, session);

                if (session !== this.session || this.closed) return;

                this.setLoading(false);
                this.consecutiveErrors = 0;
                this.renderLinkButton(item.link);
                this.startImageTimer(item.duration || this.imageDuration);
            }

            this.prefetchNext(this.current);
        }

        async show(index) {
            if (this.closed) return;

            if (!this.stories.length) {
                this.setLoading(true);
                this.percentEl.textContent = this.strings.noStories;
                return;
            }

            if (this.aborter) {
                this.aborter.abort();
            }

            this.cancelTimer();
            this.cleanupMedia();

            this.aborter = new AbortController();

            const session = ++this.session;

            this.current = ((index % this.stories.length) + this.stories.length) % this.stories.length;

            this.renderSegments(0);
            this.setLoading(true, this.simulateSpeed > 0 ? 0 : null);

            const item = this.stories[this.current];

            try {
                if (this.simulateSpeed > 0) {
                    const blob = await this.downloadFully(item.src, this.aborter.signal, (ratio) => {
                        if (session === this.session && !this.closed) {
                            this.setLoading(true, ratio);
                        }
                    });

                    if (session !== this.session || this.closed) return;

                    this.objectUrl = URL.createObjectURL(blob);
                    await this.playItem(this.objectUrl, item, session);
                    return;
                }

                await this.playItem(item.src, item, session);
            } catch (error) {
                if (error?.name === 'AbortError' || session !== this.session || this.closed) {
                    return;
                }

                this.consecutiveErrors++;

                this.setLoading(true);
                this.percentEl.textContent = this.strings.error;

                if (this.consecutiveErrors >= this.stories.length) {
                    this.percentEl.textContent = this.strings.noStories;
                    return;
                }

                setTimeout(() => {
                    if (session === this.session && !this.closed) {
                        this.next();
                    }
                }, 1200);
            }
        }

        next() {
            if (this.current >= this.stories.length - 1) {
                this.close();
                return;
            }

            this.show(this.current + 1);
        }

        prev() {
            if (this.current <= 0) {
                return;
            }

            this.show(this.current - 1);
        }
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.storyino-button, .storyino-ring');
        if (!trigger) return;

        let config = {};

        try {
            config = JSON.parse(trigger.getAttribute('data-storyino') || '{}');
        } catch {
            config = {};
        }

        new StoryinoPlayer(config, trigger);
    });
})();