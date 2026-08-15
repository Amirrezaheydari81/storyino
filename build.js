const esbuild = require('esbuild');
const CleanCSS = require('clean-css');
const fs = require('fs');
const path = require('path');
const chokidar = require('chokidar');

const isWatch = process.argv.includes('--watch');

const SRC = path.join(__dirname, 'src');
const OUT = path.join(__dirname, 'assets');

const CSS_FILES = [
    { src: 'css/storyino.css', out: 'css/storyino.min.css' },
    { src: 'css/storyino-admin.css', out: 'css/storyino-admin.min.css' },
];

const JS_FILES = [
    { src: 'js/storyino.js', out: 'js/storyino.min.js' },
    { src: 'js/storyino-admin.js', out: 'js/storyino-admin.min.js' },
];

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

async function buildCSS() {
    const cleanCss = new CleanCSS({
        level: 2,
        compatibility: '*',
        format: 'keep-breaks',
    });

    for (const file of CSS_FILES) {
        const inputPath = path.join(SRC, file.src);
        const outputPath = path.join(OUT, file.out);

        if (!fs.existsSync(inputPath)) {
            console.warn(`[css] skip: ${file.src}`);
            continue;
        }

        const src = fs.readFileSync(inputPath, 'utf8');
        const result = cleanCss.minify(src);

        if (result.errors.length) {
            console.error(`[css] error ${file.src}:`, result.errors);
            continue;
        }

        if (result.warnings.length) {
            console.warn(`[css] warn ${file.src}:`, result.warnings);
        }

        ensureDir(outputPath);
        fs.writeFileSync(outputPath, result.styles, 'utf8');
        console.log(`[css] ${file.src} → ${file.out} (${result.stats.minifiedSize}B)`);
    }
}

async function buildAll() {
    const start = Date.now();
    await buildJS();
    await buildCSS();
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