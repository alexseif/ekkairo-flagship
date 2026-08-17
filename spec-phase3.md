# Specification: Phase 3 — Content Migration Engine & Gutenberg Conversion

## 1. Objective

Phase 3 executes the automated content transformation pipeline on `backstage_ekk`. It scans legacy Betheme Muffin Builder & LayerSlider data, converts shortcodes to valid Gutenberg blocks under PHP 7.4, logs any unmapped shortcodes for human review, deactivates legacy plugins/themes, and transitions the web server environment to PHP 8.2-FPM.

---

## 2. Technical Deliverables

1. **Database Scoping & Audit**:
   - Run `bin/scope-betheme-config.php` and `bin/scope-legacy-items.php` on `backstage_ekk`.
   - Audit post/page content for Muffin Builder wrappers, shortcodes, embedded sliders, and raw HTML.

2. **Automated Migration Engine Adaptation (`bin/migration-content-engine.php`)**:
   - Parse Muffin Builder grid/column definitions into `wp:columns` and `wp:column` blocks.
   - Parse Muffin Builder headings, text, buttons, and images into corresponding native Gutenberg blocks (`wp:heading`, `wp:paragraph`, `wp:button`, `wp:image`).
   - Re-engineer LayerSlider shortcodes into custom Hero Slider blocks or block patterns.
   - **Unmapped Shortcode Protocol**: Any shortcodes or elements lacking a migration destination will NOT be silently discarded or converted into invalid markup. They will be logged to `ai-work/logs/unmapped-shortcodes.log` for human review to identify false shortcodes vs. elements needing new migration destinations.

3. **Runtime Transition & Plugin Deactivation**:
   - Run `bin/01-backstage-setup.sh` to execute content engine under PHP 7.4.
   - Deactivate legacy Betheme theme, Betheme-Child, LayerSlider, Muffin Builder, Jetpack, and obsolete plugins via WP-CLI.
   - Activate `ekkairo-flagship` theme.
   - Reconfigure Nginx site handler `backstage.ekkairo.org.conf` from `php7.4-fpm.sock` to `php8.2-fpm.sock` and reload Nginx.

4. **Gutenberg Markup Validation**:
   - Execute AST and DOM block sanitizer (`bin/test-fse-sanitizer.php` and `bin/validate-ast.js`).
   - Guarantee zero "This block contains invalid content" warnings in the WordPress Block Editor.

---

## 3. Human Verification & Gate 3 Criteria

- [ ] Database scoping logs generated in `ai-work/logs/`.
- [ ] `migration-content-engine.php` executes to completion without fatal errors under PHP 7.4.
- [ ] `ai-work/logs/unmapped-shortcodes.log` reviewed by human for unmapped shortcode resolution.
- [ ] Legacy Betheme and unused plugins successfully deactivated.
- [ ] Nginx handler switched to `php8.2-fpm.sock` and site loads under PHP 8.2.
- [ ] Gutenberg editor opens posts/pages cleanly with zero invalid block recovery prompts.
- [ ] Human review and explicit approval requested before advancing to Phase 4.
