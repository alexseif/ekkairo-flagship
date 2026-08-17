const fs = require('fs');
const path = require('path');
const { parse } = require('@wordpress/block-serialization-default-parser');

const dirs = [
    path.join(__dirname, '../templates'),
    path.join(__dirname, '../parts')
];

let totalFiles = 0;
let errors = 0;

console.log('--- AST Block Serialization Audit ---');

dirs.forEach(dir => {
    if (!fs.existsSync(dir)) return;
    const files = fs.readdirSync(dir).filter(f => f.endsWith('.html'));

    files.forEach(file => {
        totalFiles++;
        const filePath = path.join(dir, file);
        const content = fs.readFileSync(filePath, 'utf8');

        try {
            const blocks = parse(content);
            if (!Array.isArray(blocks) || blocks.length === 0) {
                console.error(`❌ [AST ERROR] ${file}: Failed to parse blocks or empty AST.`);
                errors++;
            } else {
                console.log(`✅ [VALID] ${file}: Parsed ${blocks.length} top-level blocks.`);
            }
        } catch (err) {
            console.error(`❌ [AST ERROR] ${file}: ${err.message}`);
            errors++;
        }
    });
});

console.log(`\nAudit finished: ${totalFiles} files scanned, ${errors} errors.`);
if (errors > 0) {
    process.exit(1);
} else {
    process.exit(0);
}
