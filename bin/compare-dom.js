/**
 * bin/compare-dom.js
 * Audits HTML structure, tags, and CSS classes against baseline requirements.
 */

const fs = require('fs');
const path = require('path');

const templatesDir = path.join(__dirname, '../templates');
const partsDir = path.join(__dirname, '../parts');

console.log('--- DOM Structure Audit ---');

let auditErrors = 0;
let scannedFiles = 0;

[templatesDir, partsDir].forEach(dir => {
    if (!fs.existsSync(dir)) return;
    const files = fs.readdirSync(dir).filter(f => f.endsWith('.html'));

    files.forEach(file => {
        scannedFiles++;
        const filePath = path.join(dir, file);
        const content = fs.readFileSync(filePath, 'utf8');

        // Check for forbidden inline styles
        if (/style\s*=\s*"/i.test(content)) {
            console.error(`❌ [DOM ERROR] ${file}: Contains inline style attribute.`);
            auditErrors++;
        }

        // Check for double nested header or footer tags inside template parts
        if (dir === partsDir) {
            const headerMatches = (content.match(/<header\b/gi) || []).length;
            const footerMatches = (content.match(/<footer\b/gi) || []).length;
            if (headerMatches > 1) {
                console.error(`❌ [DOM ERROR] ${file}: Duplicate <header> wrapper tags detected (${headerMatches}).`);
                auditErrors++;
            }
            if (footerMatches > 1) {
                console.error(`❌ [DOM ERROR] ${file}: Duplicate <footer> wrapper tags detected (${footerMatches}).`);
                auditErrors++;
            }
        }
    });
});

console.log(`\nDOM audit finished: ${scannedFiles} files scanned, ${auditErrors} errors.`);
if (auditErrors > 0) {
    process.exit(1);
}
process.exit(0);
