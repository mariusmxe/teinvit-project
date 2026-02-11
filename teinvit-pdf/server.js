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
const PDF_BASE_URL = 'https://pdf.teinvit.com';
const OUTPUT_DIR = path.join(__dirname, 'pdf');
const CHROME_PATH = '/usr/bin/google-chrome';

/* ================= ENSURE DIR ================= */
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

/* ================= STATIC ================= */
app.use('/pdf', express.static(OUTPUT_DIR));

/* ================= RENDER ENDPOINT ================= */
app.post('/api/render', async (req, res) => {

    const { token, order_id, filename } = req.body || {};

    if (!token || !order_id || !filename) {
        return res.status(400).json({
            status: 'error',
            code: 'INVALID_PAYLOAD'
        });
    }

    const targetUrl = `${PDF_BASE_URL}/pdf/${token}`;
    const orderDir  = path.join(OUTPUT_DIR, String(order_id));

    if (!fs.existsSync(orderDir)) {
        fs.mkdirSync(orderDir, { recursive: true });
    }

    const pdfPath = path.join(orderDir, filename);

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

        // Handshake PDF final (exact cum ai definit deja)
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
            pdf_url: `/pdf/${order_id}/${filename}`
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

/* ================= START ================= */
app.listen(PORT, '0.0.0.0', () => {
    console.log('TeInvit PDF service running on port ' + PORT);
});