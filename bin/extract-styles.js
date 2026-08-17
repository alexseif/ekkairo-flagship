const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
    console.log('Launching browser to extract tokens from https://ekalexandria.org ...');
    const browser = await chromium.launch();
    const page = await browser.newPage();
    
    // Visit the live site
    await page.goto('https://ekalexandria.org', { waitUntil: 'networkidle' });

    const tokens = await page.evaluate(() => {
        const getStyles = (selector) => {
            const el = document.querySelector(selector);
            if (!el) return null;
            const computed = window.getComputedStyle(el);
            return {
                fontFamily: computed.fontFamily,
                fontSize: computed.fontSize,
                fontWeight: computed.fontWeight,
                lineHeight: computed.lineHeight,
                color: computed.color,
                backgroundColor: computed.backgroundColor,
                padding: computed.padding,
                margin: computed.margin
            };
        };

        return {
            body: getStyles('body'),
            h1: getStyles('h1, .column_attr h1'),
            h2: getStyles('h2, .column_attr h2'),
            h3: getStyles('h3, .column_attr h3'),
            h4: getStyles('h4, .column_attr h4'),
            h5: getStyles('h5, .column_attr h5'),
            h6: getStyles('h6, .column_attr h6'),
            p: getStyles('p, .column_attr p'),
            a: getStyles('a'),
            header: getStyles('#Top_bar') || getStyles('#Header'),
            footer: getStyles('#Footer'),
            primaryColor: getStyles('.mfn-btn, .button_theme')?.backgroundColor || 'rgb(41, 142, 206)' // default fallback
        };
    });

    const outputPath = path.join(__dirname, '..', 'ai-work', 'scopings', 'styles.json');
    fs.mkdirSync(path.dirname(outputPath), { recursive: true });
    fs.writeFileSync(outputPath, JSON.stringify(tokens, null, 2));

    await browser.close();
    console.log('Tokens successfully extracted to ai-work/scopings/styles.json');
})().catch(err => {
    console.error('Error during extraction:', err);
    process.exit(1);
});
