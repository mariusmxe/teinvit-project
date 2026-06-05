/**
 * TeInvit – PDF Service (Puppeteer Core)
 * CANONIC – FINAL (WP decides filename, Node only renders)
 */

const express = require('express');
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const app = express();
app.use(express.json({ limit: '2mb' }));

/* ================= CONFIG ================= */
const PORT = 3000;
const PDF_BASE_URL = 'https://www.teinvit.com';
const OUTPUT_DIR = path.join(__dirname, 'pdf');
const CHROME_PATH = '/usr/bin/google-chrome';
const NODE_SHARED_SECRET = (process.env.TEINVIT_NODE_SHARED_SECRET || '').trim();

/* ================= ENSURE DIR ================= */
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

/* ================= STATIC ================= */
app.use('/pdf', express.static(OUTPUT_DIR));

function isAuthorizedDelete(req) {
    if (!NODE_SHARED_SECRET) return false;
    const provided = String(req.header('X-TeInvit-Secret') || '').trim();
    return provided !== '' && provided === NODE_SHARED_SECRET;
}

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
