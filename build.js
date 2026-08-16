const esbuild = require('esbuild');
const CleanCSS = require('clean-css');
const fs = require('fs');
const path = require('path');
const chokidar = require('chokidar');

const isWatch = process.argv.includes('--watch');

const SRC = path.join(__dirname, 'src');
const OUT = path.join(__dirname, 'assets');

const FRONTEND_CSS = { src: 'css/storyino.css', out: 'css/storyino.min.css' };
const ADMIN_CSS_SRC = path.join(SRC, 'css/storyino-admin.css');
const ADMIN_CSS_OUT = path.join(OUT, 'css/storyino-admin.css');
const ADMIN_CSS_MIN = path.join(OUT, 'css/storyino-admin.min.css');

const JS_FILES = [
    { src: 'js/storyino.js', out: 'js/storyino.min.js' },
    { src: 'js/storyino-admin.js', out: 'js/storyino-admin.min.js' },
];

function isolateAdminCss(css) {
    return css.replace(
        '*,:before,:after,::backdrop{',
        '#storyino-admin,#storyino-admin *,#storyino-admin :before,#storyino-admin :after{'
    );
}

function ensureDir(filePath) {
    const dir = path.dirname(filePath);
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

async function buildJS() {
    console.log('[js] building...');
    await esbuild.build({
        entryPoints: JS_FILES.map(f => path.join(SRC, f.src)),
        outdir: OUT,
        bundle: false,
        minify: true,
        target: ['es2020'],
        outbase: SRC,
        entryNames: '[dir]/[name].min',
        logLevel: 'info',
    });
}

async function buildFrontendCSS() {
    const cleanCss = new CleanCSS({
        level: 2,
        compatibility: '*',
        format: 'keep-breaks',
    });

    const inputPath = path.join(SRC, FRONTEND_CSS.src);
    const outputPath = path.join(OUT, FRONTEND_CSS.out);

    if (!fs.existsSync(inputPath)) {
        console.warn(`[css] skip: ${FRONTEND_CSS.src}`);
        return;
    }

    const src = fs.readFileSync(inputPath, 'utf8');
    const result = cleanCss.minify(src);

    if (result.errors.length) {
        console.error(`[css] error ${FRONTEND_CSS.src}:`, result.errors);
        return;
    }

    if (result.warnings.length) {
        console.warn(`[css] warn ${FRONTEND_CSS.src}:`, result.warnings);
    }

    ensureDir(outputPath);
    fs.writeFileSync(outputPath, result.styles, 'utf8');
    console.log(`[css] ${FRONTEND_CSS.src} → ${FRONTEND_CSS.out} (${result.stats.minifiedSize}B)`);
}

async function buildAdminCSS() {
    const postcss = require('postcss');
    const tailwindcss = (await import('@tailwindcss/postcss')).default;

    if (!fs.existsSync(ADMIN_CSS_SRC)) {
        console.warn('[css] skip: css/storyino-admin.css');
        return;
    }

    const css = fs.readFileSync(ADMIN_CSS_SRC, 'utf8');
    const result = await postcss([
        tailwindcss(),
    ]).process(css, {
        from: ADMIN_CSS_SRC,
        to: ADMIN_CSS_OUT,
    });

    const isolated = isolateAdminCss(result.css);
    const isolatedMin = isolateAdminCss(
        (await postcss([
            tailwindcss({ optimize: { minify: true } }),
        ]).process(css, {
            from: ADMIN_CSS_SRC,
            to: ADMIN_CSS_MIN,
        })).css
    );

    ensureDir(ADMIN_CSS_OUT);
    fs.writeFileSync(ADMIN_CSS_OUT, isolated, 'utf8');
    fs.writeFileSync(ADMIN_CSS_MIN, isolatedMin, 'utf8');
    console.log(`[css] css/storyino-admin.css → css/storyino-admin.min.css (${Buffer.byteLength(isolatedMin)}B)`);
}

async function buildAll() {
    const start = Date.now();
    await buildJS();
    await buildFrontendCSS();
    await buildAdminCSS();
    console.log(`[done] ${Date.now() - start}ms`);
}

(async () => {
    await buildAll();

    if (isWatch) {
        console.log('[watch] watching src/ ...');
        chokidar.watch(SRC, { ignoreInitial: true })
            .on('all', async (event, filePath) => {
                console.log(`[watch] ${event}: ${path.relative(__dirname, filePath)}`);
                try {
                    await buildAll();
                } catch (e) {
                    console.error('[watch] error:', e);
                }
            });
    }
})();
