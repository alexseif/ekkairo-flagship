# Specification: Phase 2 — Theme Scaffolding & Core Architecture

## 1. Objective

Phase 2 builds the core WordPress Full Site Editing (FSE) block theme structure for `ekkairo-flagship`, configures `theme.json` design tokens, and establishes the Node `@wordpress/scripts` asset build pipeline.

---

## 2. Technical Deliverables

1. **Theme Metadata & Core Files**:
   - `style.css`: Theme header metadata (Theme Name: EKK Flagship Theme, Version: 1.0.0, Requires PHP: 8.2, Text Domain: ekkairo-flagship).
   - `functions.php`: Theme setup, block asset enqueues, custom block registrations, and PHP 8.2 strict typing hooks.

2. **Global Design System (`theme.json`)**:
   - Schema version 2/3.
   - Color Palette: Primary EKK Brand colors, background neutrals, text contrast pairs.
   - Typography: Font families, fluid typography scales, font weights, line heights.
   - Spacing & Layout: Content size (e.g. 1200px), Wide size (e.g. 1400px), margin/padding presets.
   - Block Styles: Default settings for core blocks (`core/button`, `core/heading`, `core/paragraph`, `core/group`).

3. **FSE Directory Scaffold**:
   - `templates/`: Baseline HTML block templates (`index.html`, `front-page.html`, `single.html`, `page.html`, `archive.html`, `404.html`).
   - `parts/`: FSE template parts (`header.html`, `footer.html`).
   - `patterns/`: Standard theme patterns directory.
   - `blocks/`: Custom Gutenberg block source directory.

4. **Asset Build Pipeline (`package.json`)**:
   - Install `@wordpress/scripts` as devDependency.
   - Configure scripts: `"build": "wp-scripts build"`, `"start": "wp-scripts start"`.
   - Setup asset compilation source directories (`src/`) outputting to `build/`.
   - Pre-compiled assets committed to Git for Node-free production deployment.

---

## 3. Human Verification & Gate 2 Criteria

- [ ] Theme `style.css` and `functions.php` load without PHP errors or warnings under PHP 7.4 / PHP 8.2.
- [ ] `theme.json` validates with zero syntax or schema errors.
- [ ] `npm run build` executes cleanly with `@wordpress/scripts`.
- [ ] FSE template parts (`header.html`, `footer.html`) render in WordPress Site Editor.
- [ ] Human review and explicit approval requested before advancing to Phase 3.
