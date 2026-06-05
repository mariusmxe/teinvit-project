/**
 * TeInvit – PDF Service (Puppeteer Core)
 * CANONIC – FINAL (WP decides filename, Node only renders)
 */

const express = require('express');
const crypto = require('crypto');
const fs = require('fs');
const net = require('net');
const path = require('path');
const puppeteer = require('puppeteer-core');
const sharp = require('sharp');

const app = express();
app.use(express.json({ limit: '2mb' }));

/* ================= CONFIG ================= */
const PORT = 3000;
const PDF_BASE_URL = 'https://www.teinvit.com';
const OUTPUT_DIR = path.join(__dirname, 'pdf');
const CHROME_PATH = '/usr/bin/google-chrome';
const NODE_SHARED_SECRET = (process.env.TEINVIT_NODE_SHARED_SECRET || '').trim();
const GALLERY_TMP_DIR = path.join(__dirname, 'gallery-tmp');
const GALLERY_ALLOWED_HOSTNAME = (process.env.TEINVIT_GALLERY_ALLOWED_HOSTNAME || 'www.teinvit.com').trim().toLowerCase();
const GALLERY_TMP_TTL_MS = 2 * 60 * 60 * 1000;
const GALLERY_DOWNLOAD_TTL_SECONDS = 15 * 60;
const GALLERY_CAPTURE_SELECTOR = '[data-teinvit-gallery-capture="1"]';
const GALLERY_WEBP_QUALITY = 88;

/* ================= ENSURE DIR ================= */
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}
if (!fs.existsSync(GALLERY_TMP_DIR)) {
    fs.mkdirSync(GALLERY_TMP_DIR, { recursive: true });
}

/* ================= STATIC ================= */
app.use('/pdf', express.static(OUTPUT_DIR));

function isAuthorizedDelete(req) {
    if (!NODE_SHARED_SECRET) return false;
    const provided = String(req.header('X-TeInvit-Secret') || '').trim();
    return provided !== '' && provided === NODE_SHARED_SECRET;
}

function isAuthorizedNodeRequest(req) {
    if (!NODE_SHARED_SECRET) return false;
    const provided = String(req.header('X-TeInvit-Secret') || '').trim();
    return provided !== '' && provided === NODE_SHARED_SECRET;
}

function isSafeFilename(fileName) {
    const raw = String(fileName || '').trim();
    const safe = path.basename(raw);
    return safe !== '' && safe === raw && ['render.png', 'render.webp', 'metadata.json', 'debug.png'].includes(safe);
}

function galleryJobDir(jobId) {
    const safeJobId = String(jobId || '').trim();
    if (!/^[a-zA-Z0-9._-]+$/.test(safeJobId)) {
        throw new Error('INVALID_JOB_ID');
    }
    const base = path.resolve(GALLERY_TMP_DIR);
    const dir = path.resolve(path.join(base, safeJobId));
    if (dir !== base && !dir.startsWith(base + path.sep)) {
        throw new Error('INVALID_JOB_PATH');
    }
    return dir;
}

function signGalleryDownload(jobId, fileName, exp) {
    const payload = `${jobId}|${fileName}|${exp}`;
    return crypto.createHmac('sha256', NODE_SHARED_SECRET).update(payload).digest('hex');
}

function verifyGalleryDownload(jobId, fileName, exp, sig) {
    const parsedExp = parseInt(exp, 10);
    if (!NODE_SHARED_SECRET || !Number.isFinite(parsedExp) || parsedExp <= Math.floor(Date.now() / 1000)) {
        return false;
    }
    const expected = signGalleryDownload(jobId, fileName, parsedExp);
    const provided = String(sig || '').trim();
    if (!provided || expected.length !== provided.length) return false;
    try {
        return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(provided));
    } catch {
        return false;
    }
}

function galleryDownloadPath(jobId, fileName) {
    const exp = Math.floor(Date.now() / 1000) + GALLERY_DOWNLOAD_TTL_SECONDS;
    const sig = signGalleryDownload(jobId, fileName, exp);
    return `/api/gallery/download/${encodeURIComponent(jobId)}/${encodeURIComponent(fileName)}?exp=${encodeURIComponent(exp)}&sig=${encodeURIComponent(sig)}`;
}

function validateGalleryRenderUrl(rawUrl) {
    let parsed;
    try {
        parsed = new URL(String(rawUrl || ''));
    } catch {
        const err = new Error('INVALID_RENDER_URL');
        err.code = 'INVALID_RENDER_URL';
        throw err;
    }

    const pathname = parsed.pathname.replace(/\/+$/, '') + '/';
    if (
        parsed.protocol !== 'https:' ||
        parsed.hostname.toLowerCase() !== GALLERY_ALLOWED_HOSTNAME ||
        pathname !== '/teinvit-gallery-render/'
    ) {
        const err = new Error('RENDER_URL_NOT_ALLOWED');
        err.code = 'RENDER_URL_NOT_ALLOWED';
        throw err;
    }

    for (const key of ['product_id', 'vertical', 'theme_key', 'exp', 'sig']) {
        if (!parsed.searchParams.get(key)) {
            const err = new Error('RENDER_URL_MISSING_QUERY');
            err.code = 'RENDER_URL_MISSING_QUERY';
            throw err;
        }
    }

    if (!['wedding', 'birthday', 'baptism'].includes(String(parsed.searchParams.get('vertical') || '').toLowerCase())) {
        const err = new Error('RENDER_URL_INVALID_VERTICAL');
        err.code = 'RENDER_URL_INVALID_VERTICAL';
        throw err;
    }

    return parsed.toString();
}

function isPrivateHostname(hostname) {
    const host = String(hostname || '').trim().toLowerCase();
    if (!host || host === 'localhost' || host.endsWith('.localhost')) return true;
    if (host === '::1' || host === '[::1]') return true;

    const ipType = net.isIP(host);
    if (ipType === 4) {
        const parts = host.split('.').map((part) => parseInt(part, 10));
        if (parts.length !== 4 || parts.some((part) => !Number.isFinite(part))) return true;
        if (parts[0] === 10 || parts[0] === 127 || parts[0] === 0) return true;
        if (parts[0] === 169 && parts[1] === 254) return true;
        if (parts[0] === 172 && parts[1] >= 16 && parts[1] <= 31) return true;
        if (parts[0] === 192 && parts[1] === 168) return true;
    }
    if (ipType === 6) {
        if (host.startsWith('fc') || host.startsWith('fd') || host.startsWith('fe80')) return true;
    }

    return false;
}

function isBlockedGalleryResourceUrl(rawUrl) {
    let parsed;
    try {
        parsed = new URL(String(rawUrl || ''));
    } catch {
        return true;
    }

    if (['data:', 'blob:', 'about:'].includes(parsed.protocol)) {
        return false;
    }
    if (!['http:', 'https:'].includes(parsed.protocol)) {
        return true;
    }

    return isPrivateHostname(parsed.hostname);
}

function cleanupGalleryTmpOrphans() {
    let entries = [];
    try {
        entries = fs.readdirSync(GALLERY_TMP_DIR, { withFileTypes: true });
    } catch {
        return;
    }

    const now = Date.now();
    for (const entry of entries) {
        if (!entry.isDirectory()) continue;
        const fullPath = path.join(GALLERY_TMP_DIR, entry.name);
        try {
            const stat = fs.statSync(fullPath);
            if (now - stat.mtimeMs > GALLERY_TMP_TTL_MS) {
                fs.rmSync(fullPath, { recursive: true, force: true });
            }
        } catch {
            // best effort cleanup
        }
    }
}

cleanupGalleryTmpOrphans();
setInterval(cleanupGalleryTmpOrphans, 30 * 60 * 1000).unref();

function numberOrZero(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

function baseGalleryMetadata(payload) {
    return {
        status: 'pending',
        product_id: parseInt(payload.product_id, 10) || 0,
        requested_product_id: parseInt(payload.product_id, 10) || 0,
        product_slug: String(payload.product_slug || ''),
        gallery_file_base_slug: String(payload.gallery_file_base_slug || ''),
        vertical: String(payload.vertical || ''),
        theme_key_internal: String(payload.theme_key_internal || ''),
        theme_label: String(payload.theme_label || ''),
        theme_file_key: String(payload.theme_file_key || ''),
        theme_css_class_expected: String(payload.theme_css_class_expected || payload.theme_css_class || ''),
        theme_css_class_actual: '',
        background_url: String(payload.background_url || ''),
        expected_background_url: String(payload.background_url || ''),
        actual_dom_background_url: '',
        background_loaded: false,
        background_decoded: false,
        background_match: false,
        background_attachment_id: parseInt(payload.background_attachment_id, 10) || 0,
        background_source_product_id: parseInt(payload.background_source_product_id, 10) || 0,
        layout: {
            css_width: 559,
            css_height: 794,
            device_scale_factor: 2
        },
        png: {
            exists: false,
            width: 0,
            height: 0,
            bytes: 0
        },
        webp: {
            exists: false,
            width: 0,
            height: 0,
            bytes: 0,
            quality: GALLERY_WEBP_QUALITY
        },
        readiness: {
            fonts_ready: false,
            images_decoded: false,
            theme_applied: false,
            autofit_done: false,
            final_pass_done: false,
            layout_stable: false,
            dom_stable: false,
            overflow_count: 0
        },
        capture_selector: GALLERY_CAPTURE_SELECTOR,
        capture_box: {
            width: 0,
            height: 0
        },
        timings_ms: {
            goto: 0,
            ready: 0,
            screenshot: 0,
            webp: 0
        },
        warnings: []
    };
}

function galleryError(code, message, metadata) {
    const err = new Error(message || code);
    err.code = code;
    err.metadata = metadata;
    return err;
}

async function collectGalleryDomMetadata(page, payload) {
    return page.evaluate((selector, expectedTheme, expectedBackground) => {
        const root = document.querySelector(selector);
        const canvas = root ? root.querySelector('.teinvit-canvas') : null;
        const bg = root ? root.querySelector('.teinvit-bg') : null;
        const state = window.__TEINVIT_GALLERY_STATE__ || {};
        const rootBox = root ? root.getBoundingClientRect() : { width: 0, height: 0 };
        const actualTheme = state.actual_theme_class || (canvas ? Array.from(canvas.classList || []).find((cls) => cls.indexOf('theme-') === 0) || '' : '');
        const actualBackground = state.background_url || (bg ? (bg.currentSrc || bg.src || '') : '');
        const normalizeUrl = (url) => String(url || '').replace(/#.*$/, '');
        return {
            state,
            capture_box: {
                width: Math.round(rootBox.width || 0),
                height: Math.round(rootBox.height || 0)
            },
            theme_css_class_actual: actualTheme,
            theme_match: expectedTheme !== '' && actualTheme === expectedTheme,
            background_url: actualBackground,
            background_match: state.background_match === true || (expectedBackground !== '' && normalizeUrl(actualBackground) === normalizeUrl(expectedBackground)),
            background_loaded: state.background_loaded === true || !!(bg && bg.complete && bg.naturalWidth > 0 && bg.naturalHeight > 0),
            background_decoded: state.images_decoded === true || state.background_decoded === true || !!(bg && bg.complete && bg.naturalWidth > 0 && bg.naturalHeight > 0),
            background_natural: state.background_natural || {
                width: bg ? (bg.naturalWidth || 0) : 0,
                height: bg ? (bg.naturalHeight || 0) : 0
            },
            readiness: {
                fonts_ready: state.fonts_ready === true,
                images_decoded: state.images_decoded === true,
                theme_applied: state.theme_applied === true,
                autofit_done: state.autofit_done === true,
                final_pass_done: state.final_pass_done === true,
                layout_stable: state.layout_stable === true,
                dom_stable: state.dom_stable === true,
                overflow_count: Number.isFinite(Number(state.overflow_count)) ? Number(state.overflow_count) : 1
            }
        };
    }, GALLERY_CAPTURE_SELECTOR, String(payload.theme_css_class_expected || payload.theme_css_class || ''), String(payload.background_url || ''));
}

function mergeGalleryDomMetadata(metadata, domMetadata) {
    metadata.theme_css_class_actual = String(domMetadata.theme_css_class_actual || '');
    metadata.actual_dom_background_url = String(domMetadata.background_url || '');
    metadata.background_url = String(domMetadata.background_url || metadata.background_url || '');
    metadata.background_loaded = !!domMetadata.background_loaded;
    metadata.background_decoded = !!domMetadata.background_decoded;
    metadata.background_match = !!domMetadata.background_match;
    metadata.background_natural = domMetadata.background_natural || { width: 0, height: 0 };
    metadata.capture_box = domMetadata.capture_box || { width: 0, height: 0 };
    metadata.readiness = Object.assign({}, metadata.readiness, domMetadata.readiness || {});
    return metadata;
}

function assertGalleryReadyMetadata(metadata) {
    const readiness = metadata.readiness || {};
    const checks = [
        ['fonts_ready', readiness.fonts_ready === true],
        ['background_loaded', metadata.background_loaded === true],
        ['background_decoded', metadata.background_decoded === true],
        ['images_decoded', readiness.images_decoded === true],
        ['theme_applied', readiness.theme_applied === true],
        ['theme_css_class_actual', metadata.theme_css_class_actual === metadata.theme_css_class_expected],
        ['autofit_done', readiness.autofit_done === true],
        ['final_pass_done', readiness.final_pass_done === true],
        ['layout_stable', readiness.layout_stable === true],
        ['dom_stable', readiness.dom_stable === true],
        ['overflow_count', Number(readiness.overflow_count || 0) === 0],
        ['capture_box', metadata.capture_box && metadata.capture_box.width === 559 && metadata.capture_box.height === 794],
        ['background_match', metadata.background_match === true]
    ];

    const failed = checks.filter(([, ok]) => !ok).map(([name]) => name);
    if (failed.length > 0) {
        metadata.warnings.push(`readiness failed: ${failed.join(', ')}`);
        throw galleryError('gallery_readiness_failed', `Gallery readiness failed: ${failed.join(', ')}`, metadata);
    }
}

async function renderGalleryAttempt(payload, jobDir) {
    const metadata = baseGalleryMetadata(payload);
    const renderUrl = validateGalleryRenderUrl(payload.render_url);
    let browser;
    let page;

    try {
        browser = await puppeteer.launch({
            executablePath: CHROME_PATH,
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage'
            ]
        });

        page = await browser.newPage();
        await page.setViewport({
            width: 559,
            height: 794,
            deviceScaleFactor: 2
        });

        await page.setRequestInterception(true);
        page.on('request', (request) => {
            if (isBlockedGalleryResourceUrl(request.url())) {
                request.abort();
                return;
            }
            if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
                try {
                    validateGalleryRenderUrl(request.url());
                } catch {
                    request.abort();
                    return;
                }
            }
            request.continue();
        });

        const gotoStarted = Date.now();
        await page.goto(renderUrl, {
            waitUntil: 'networkidle0',
            timeout: 60000
        });
        metadata.timings_ms.goto = Date.now() - gotoStarted;

        const readyStarted = Date.now();
        try {
            await page.waitForFunction(
                () => window.__TEINVIT_GALLERY_READY__ === true,
                { timeout: 45000 }
            );
        } catch {
            try {
                const partial = await collectGalleryDomMetadata(page, payload);
                mergeGalleryDomMetadata(metadata, partial);
            } catch {
                // best effort readiness metadata
            }
            throw galleryError('gallery_ready_timeout', 'Gallery readiness flag did not appear in time.', metadata);
        }
        metadata.timings_ms.ready = Date.now() - readyStarted;

        const domMetadata = await collectGalleryDomMetadata(page, payload);
        mergeGalleryDomMetadata(metadata, domMetadata);
        assertGalleryReadyMetadata(metadata);

        const element = await page.$(GALLERY_CAPTURE_SELECTOR);
        if (!element) {
            throw galleryError('capture_selector_missing', 'Gallery capture selector missing.', metadata);
        }

        const screenshotStarted = Date.now();
        const pngBuffer = await element.screenshot({ type: 'png', omitBackground: false });
        metadata.timings_ms.screenshot = Date.now() - screenshotStarted;
        const pngPath = path.join(jobDir, 'render.png');
        fs.writeFileSync(pngPath, pngBuffer);
        const pngInfo = await sharp(pngBuffer).metadata();
        metadata.png = {
            exists: true,
            width: numberOrZero(pngInfo.width),
            height: numberOrZero(pngInfo.height),
            bytes: pngBuffer.length
        };
        if (metadata.png.width !== 1118 || metadata.png.height !== 1588) {
            throw galleryError('invalid_png_dimensions', 'Generated PNG dimensions are invalid.', metadata);
        }

        const webpStarted = Date.now();
        try {
            const webpBuffer = await sharp(pngBuffer).webp({ quality: GALLERY_WEBP_QUALITY }).toBuffer();
            metadata.timings_ms.webp = Date.now() - webpStarted;
            const webpPath = path.join(jobDir, 'render.webp');
            fs.writeFileSync(webpPath, webpBuffer);
            const webpInfo = await sharp(webpBuffer).metadata();
            metadata.webp = {
                exists: true,
                width: numberOrZero(webpInfo.width),
                height: numberOrZero(webpInfo.height),
                bytes: webpBuffer.length,
                quality: GALLERY_WEBP_QUALITY
            };
            if (metadata.webp.width !== 1118 || metadata.webp.height !== 1588) {
                throw galleryError('failed_webp', 'Generated WebP dimensions are invalid.', metadata);
            }
        } catch (err) {
            if (err && err.code === 'failed_webp') {
                throw err;
            }
            throw galleryError('failed_webp', err.message || 'WebP generation failed.', metadata);
        }

        metadata.status = 'ok';
        fs.writeFileSync(path.join(jobDir, 'metadata.json'), JSON.stringify(metadata, null, 2));

        await browser.close();
        return metadata;
    } catch (err) {
        if (page) {
            try {
                const partial = await collectGalleryDomMetadata(page, payload);
                mergeGalleryDomMetadata(metadata, partial);
            } catch {
                // best effort metadata
            }
            try {
                await page.screenshot({ path: path.join(jobDir, 'debug.png'), fullPage: false });
            } catch {
                // best effort debug
            }
        }

        if (browser) {
            try { await browser.close(); } catch {}
        }

        if (!err.metadata) {
            err.metadata = metadata;
        }
        throw err;
    }
}

async function renderGalleryWithRetry(payload, jobDir) {
    let lastErr = null;
    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            const metadata = await renderGalleryAttempt(payload, jobDir);
            metadata.attempt = attempt + 1;
            fs.writeFileSync(path.join(jobDir, 'metadata.json'), JSON.stringify(metadata, null, 2));
            return metadata;
        } catch (err) {
            lastErr = err;
            if (err && err.code && !['gallery_ready_timeout', 'gallery_readiness_failed'].includes(err.code) && attempt > 0) {
                break;
            }
        }
    }
    throw lastErr;
}

/* ================= GALLERY ENDPOINTS ================= */
app.post('/api/gallery/render', async (req, res) => {
    if (!isAuthorizedNodeRequest(req)) {
        return res.status(401).json({
            status: 'error',
            code: 'UNAUTHORIZED'
        });
    }

    const payload = req.body || {};
    let renderUrl;
    try {
        renderUrl = validateGalleryRenderUrl(payload.render_url);
    } catch (err) {
        return res.status(400).json({
            status: 'error',
            code: err.code || 'INVALID_RENDER_URL',
            message: err.message
        });
    }

    const jobId = `${Date.now()}-${crypto.randomUUID()}`;
    const jobDir = galleryJobDir(jobId);
    fs.mkdirSync(jobDir, { recursive: true });

    try {
        const metadata = await renderGalleryWithRetry(Object.assign({}, payload, { render_url: renderUrl }), jobDir);
        return res.json({
            status: 'ok',
            job_id: jobId,
            files: {
                png: { download_path: galleryDownloadPath(jobId, 'render.png') },
                webp: { download_path: galleryDownloadPath(jobId, 'render.webp') },
                metadata: { download_path: galleryDownloadPath(jobId, 'metadata.json') }
            },
            metadata
        });
    } catch (err) {
        const metadata = err && err.metadata ? err.metadata : baseGalleryMetadata(payload);
        metadata.status = 'error';
        fs.writeFileSync(path.join(jobDir, 'metadata.json'), JSON.stringify(metadata, null, 2));
        return res.status(500).json({
            status: 'error',
            code: err.code || 'gallery_render_failed',
            message: err.message || 'Gallery render failed.',
            job_id: jobId,
            files: fs.existsSync(path.join(jobDir, 'debug.png'))
                ? { debug: { download_path: galleryDownloadPath(jobId, 'debug.png') }, metadata: { download_path: galleryDownloadPath(jobId, 'metadata.json') } }
                : { metadata: { download_path: galleryDownloadPath(jobId, 'metadata.json') } },
            metadata
        });
    }
});

app.get('/api/gallery/download/:jobId/:fileName', (req, res) => {
    const jobId = String(req.params.jobId || '');
    const fileName = String(req.params.fileName || '');
    if (!isSafeFilename(fileName) || !verifyGalleryDownload(jobId, fileName, req.query.exp, req.query.sig)) {
        return res.status(403).json({
            status: 'error',
            code: 'FORBIDDEN'
        });
    }

    let filePath;
    try {
        filePath = path.join(galleryJobDir(jobId), fileName);
    } catch {
        return res.status(400).json({
            status: 'error',
            code: 'INVALID_JOB_ID'
        });
    }

    if (!fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
        return res.status(404).json({
            status: 'error',
            code: 'FILE_NOT_FOUND'
        });
    }

    if (fileName.endsWith('.png')) {
        res.type('image/png');
    } else if (fileName.endsWith('.webp')) {
        res.type('image/webp');
    } else {
        res.type('application/json');
    }
    return res.sendFile(filePath);
});

app.post('/api/gallery/cleanup', (req, res) => {
    if (!isAuthorizedNodeRequest(req)) {
        return res.status(401).json({
            status: 'error',
            code: 'UNAUTHORIZED'
        });
    }

    const jobId = String((req.body || {}).job_id || '');
    let dir;
    try {
        dir = galleryJobDir(jobId);
    } catch {
        return res.status(400).json({
            status: 'error',
            code: 'INVALID_JOB_ID'
        });
    }

    fs.rmSync(dir, { recursive: true, force: true });
    return res.json({
        status: 'ok',
        job_id: jobId
    });
});

/* ================= RENDER ENDPOINT ================= */
app.post('/api/render', async (req, res) => {

    const { token, order_id, filename, version_id } = req.body || {};

    const parsedOrderId = parseInt(order_id, 10);
    if (!token || !Number.isFinite(parsedOrderId) || parsedOrderId <= 0 || !filename) {
        return res.status(400).json({
            status: 'error',
            code: 'INVALID_PAYLOAD'
        });
    }

    const rawFilename = String(filename || '').trim();
    const safeFilename = path.basename(rawFilename);
    if (
        !safeFilename ||
        safeFilename !== rawFilename ||
        /[<>:"|?*\\\x00-\x1F\x7F]/.test(safeFilename) ||
        !/\.pdf$/i.test(safeFilename)
    ) {
        return res.status(400).json({
            status: 'error',
            code: 'INVALID_FILENAME'
        });
    }

    const parsedVersionId = parseInt(version_id, 10);
    const hasVersion = Number.isFinite(parsedVersionId) && parsedVersionId > 0;
    const versionQuery = hasVersion ? `?teinvit_version_id=${encodeURIComponent(parsedVersionId)}` : '';
    const targetUrl = `${PDF_BASE_URL}/pdf/${encodeURIComponent(String(token).trim())}/${versionQuery}`;
    const orderDir  = path.join(OUTPUT_DIR, String(parsedOrderId));

    if (!fs.existsSync(orderDir)) {
        fs.mkdirSync(orderDir, { recursive: true });
    }

    const pdfPath = path.join(orderDir, safeFilename);
    const resolvedPdfPath = path.resolve(pdfPath);
    const resolvedOrderDir = path.resolve(orderDir);
    if (!resolvedPdfPath.startsWith(resolvedOrderDir + path.sep)) {
        return res.status(400).json({
            status: 'error',
            code: 'INVALID_FILENAME_PATH'
        });
    }

    let browser;

    try {
        browser = await puppeteer.launch({
            executablePath: CHROME_PATH,
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage'
            ]
        });

        const page = await browser.newPage();

        await page.setViewport({
            width: 559,
            height: 794,
            deviceScaleFactor: 1
        });

        await page.goto(targetUrl, {
            waitUntil: 'networkidle0',
            timeout: 60000
        });

        // Handshake PDF final
        await page.waitForFunction(
            () => window.__TEINVIT_PDF_READY__ === true,
            { timeout: 30000 }
        );

        await page.pdf({
            path: pdfPath,
            format: 'A5',
            printBackground: true,
            preferCSSPageSize: true,
            margin: {
                top: 0,
                right: 0,
                bottom: 0,
                left: 0
            }
        });

        await browser.close();

        return res.json({
            status: 'ok',
            pdf_url: `/pdf/${parsedOrderId}/${safeFilename}`
        });

    } catch (err) {

        if (browser) {
            try { await browser.close(); } catch {}
        }

        return res.status(500).json({
            status: 'error',
            code: 'RENDER_FAILED',
            message: err.message
        });
    }
});

/* ================= DELETE ENDPOINT ================= */
app.post('/api/delete', async (req, res) => {
    if (!isAuthorizedDelete(req)) {
        return res.status(401).json({
            status: 'error',
            code: 'UNAUTHORIZED'
        });
    }

    const { order_id, filenames } = req.body || {};
    const hasFilenameFilter = Object.prototype.hasOwnProperty.call(req.body || {}, 'filenames');
    const parsedOrderId = parseInt(order_id, 10);
    if (!Number.isFinite(parsedOrderId) || parsedOrderId <= 0) {
        return res.status(400).json({
            status: 'error',
            code: 'INVALID_ORDER_ID'
        });
    }

    if (hasFilenameFilter && !Array.isArray(filenames)) {
        return res.status(400).json({
            status: 'error',
            code: 'INVALID_FILENAMES'
        });
    }

    const requested = [];
    if (hasFilenameFilter) {
        for (const name of filenames) {
            const rawName = String(name || '').trim();
            const safeName = path.basename(rawName);
            if (
                !safeName ||
                safeName !== rawName ||
                /[<>:"|?*\\\x00-\x1F\x7F]/.test(safeName) ||
                !/\.pdf$/i.test(safeName)
            ) {
                return res.status(400).json({
                    status: 'error',
                    code: 'INVALID_FILENAME'
                });
            }
            requested.push(safeName);
        }

        if (requested.length === 0) {
            return res.status(400).json({
                status: 'error',
                code: 'INVALID_FILENAMES'
            });
        }
    }

    const orderDir = path.join(OUTPUT_DIR, String(parsedOrderId));
    const resolvedOrderDir = path.resolve(orderDir);
    const resolvedBaseDir = path.resolve(OUTPUT_DIR);
    if (!resolvedOrderDir.startsWith(resolvedBaseDir + path.sep)) {
        return res.status(400).json({
            status: 'error',
            code: 'INVALID_ORDER_DIR'
        });
    }

    if (!fs.existsSync(orderDir)) {
        console.log(`[TeInvit PDF Delete] order=${parsedOrderId} folder missing path=${orderDir}`);
        return res.json({
            status: 'ok',
            order_id: parsedOrderId,
            deleted_files: [],
            missing_files: hasFilenameFilter ? requested : [],
            remaining_entries: 0,
            folder_deleted: false,
            folder_missing: true
        });
    }

    const deletedFiles = [];
    const missingFiles = [];
    const errors = [];

    try {
        const filesInDir = fs.readdirSync(orderDir);
        const targetSet = hasFilenameFilter
            ? new Set(requested)
            : new Set(filesInDir.filter((f) => /\.pdf$/i.test(f)));

        if (hasFilenameFilter) {
            const filesInDirSet = new Set(filesInDir);
            for (const fileName of requested) {
                if (!filesInDirSet.has(fileName)) {
                    missingFiles.push(fileName);
                }
            }
        }

        for (const fileName of filesInDir) {
            if (!targetSet.has(fileName)) continue;
            const fullPath = path.join(orderDir, fileName);
            const resolvedFilePath = path.resolve(fullPath);
            if (!resolvedFilePath.startsWith(resolvedOrderDir + path.sep)) {
                continue;
            }
            try {
                if (fs.existsSync(fullPath) && fs.statSync(fullPath).isFile()) {
                    fs.unlinkSync(fullPath);
                    deletedFiles.push(fileName);
                } else if (fs.existsSync(fullPath)) {
                    errors.push({ file: fileName, message: 'NOT_A_FILE' });
                }
            } catch (err) {
                errors.push({ file: fileName, message: err.message });
            }
        }

        let folderDeleted = false;
        let folderMissing = false;
        let remainingEntries = 0;
        try {
            const remaining = fs.readdirSync(orderDir);
            remainingEntries = remaining.length;
            if (remaining.length === 0) {
                fs.rmdirSync(orderDir);
                folderDeleted = true;
                console.log(`[TeInvit PDF Delete] order=${parsedOrderId} empty folder removed path=${orderDir}`);
            } else {
                console.log(`[TeInvit PDF Delete] order=${parsedOrderId} folder kept path=${orderDir} remaining_entries=${remaining.length}`);
            }
        } catch (err) {
            if (err && err.code === 'ENOENT') {
                folderMissing = true;
                console.log(`[TeInvit PDF Delete] order=${parsedOrderId} folder missing path=${orderDir}`);
            } else {
                errors.push({ folder: String(parsedOrderId), message: err.message });
            }
        }

        if (errors.length > 0) {
            return res.status(500).json({
                status: 'error',
                code: 'DELETE_PARTIAL',
                order_id: parsedOrderId,
                deleted_files: deletedFiles,
                missing_files: missingFiles,
                remaining_entries: remainingEntries,
                errors
            });
        }

        if (!folderMissing && deletedFiles.length === 0 && missingFiles.length === 0 && !folderDeleted) {
            return res.status(409).json({
                status: 'error',
                code: 'NOTHING_DELETED',
                order_id: parsedOrderId,
                deleted_files: [],
                missing_files: [],
                remaining_entries: remainingEntries,
                folder_deleted: false,
                folder_missing: false
            });
        }

        return res.json({
            status: 'ok',
            order_id: parsedOrderId,
            deleted_files: deletedFiles,
            missing_files: missingFiles,
            remaining_entries: remainingEntries,
            folder_deleted: folderDeleted,
            folder_missing: folderMissing
        });
    } catch (err) {
        return res.status(500).json({
            status: 'error',
            code: 'DELETE_FAILED',
            message: err.message
        });
    }
});

/* ================= START ================= */
app.listen(PORT, '0.0.0.0', () => {
    console.log('TeInvit PDF service running on port ' + PORT);
});
