/**
 * Automated FSE Template Part & Block Serialization Validation Test
 */

const { parse } = require("@wordpress/block-serialization-default-parser");
const fs = require("fs");
const path = require("path");

const files = [
	"parts/hero-slider.html",
	"parts/header.html",
	"parts/footer.html",
	"parts/news-grid.html",
	"parts/about-purpose.html",
	"parts/publications-banner.html",
	"parts/subscribe-card.html",
	"parts/social-attraction.html",
	"parts/neo-fos-grid.html",
	"templates/front-page.html",
];

let errors = 0;

files.forEach((file) => {
	const filePath = path.join(process.cwd(), file);
	if (!fs.existsSync(filePath)) {
		console.error(`[ERROR] File not found: ${file}`);
		errors++;
		return;
	}
	const content = fs.readFileSync(filePath, "utf8");
	const blocks = parse(content);

	function validateBlock(block) {
		if (block.blockName === "core/post-template") {
			// Ensure post-template does not contain unsupported align attribute
			if (block.attrs && block.attrs.align) {
				console.error(`[ERROR] ${file}: core/post-template cannot have "align" attribute!`);
				errors++;
			}
			// Ensure innerHTML is clean and does not contain raw text node injections
			if (block.innerHTML && (block.innerHTML.includes("<ul") || block.innerHTML.includes("<li"))) {
				console.error(`[ERROR] ${file}: core/post-template innerHTML contains invalid raw text nodes!`);
				errors++;
			}
		}
		if (block.innerBlocks) {
			block.innerBlocks.forEach(validateBlock);
		}
	}

	blocks.forEach(validateBlock);
	console.log(`[PASS] ${file}: ${blocks.length} block(s) parsed cleanly.`);
});

if (errors > 0) {
	console.error(`\nValidation FAILED with ${errors} error(s).`);
	process.exit(1);
} else {
	console.log("\nALL FSE TEMPLATES AND PARTS PASSED BLOCK VALIDATION 100%!");
}
