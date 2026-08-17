# EKK Portal Modernization & FSE Theme Master Plan

## 1. Executive Summary

This master plan details the end-to-end upgrade of the **Greek Community of Cairo (EKK)** portal (`ekkairo.org`).
The project transitions the codebase from **PHP 7.4 + Betheme (Muffin Builder / LayerSlider)** to a modern **PHP 8.2 + Full Site Editing (FSE) Gutenberg Block Theme (`ekkairo-flagship`)**.

All theme development, migration tooling, documentation, and phase specifications are maintained within the `ekkairo-flagship` Git repository to ensure full reproducibility (`git clone` + automated migration run).

---

## 2. Technical Architecture & Lifecycle Strategy

1. **Git & Repository Management**:
   - Theme repository at `/var/www/ekkairo.org/public/wp-content/themes/ekkairo-flagship`.
   - Clean, atomic git commit strategy.
   - Assets pre-compiled (`npm run build`) and committed for production deployment without server-side Node.js dependencies.

2. **Staging Environment (`backstage.ekkairo.org`)**:
   - Dedicated local webroot: `/var/www/backstage.ekkairo.org/public`.
   - Production DB snapshot: `db207080_ekk`.
   - Staging DB: `backstage_ekk` (cloned from `db207080_ekk`).
   - Nginx config: `/etc/nginx/sites-available/backstage.ekkairo.org.conf`.
   - Uploads strategy: Proxy pass `/wp-content/uploads/` to `https://ekkairo.org` via Nginx to conserve local disk space.

3. **PHP Runtime Transition Sequence**:
   - **Stage 1 (Initial Setup & Migration)**: Execute under **PHP 7.4-FPM** so legacy Betheme, Muffin Builder, and LayerSlider remain operational while data scoping and shortcode-to-Gutenberg parsing execute.
   - **Stage 2 (Cutover & Hardening)**: Deactivate legacy Betheme and plugins, activate `ekkairo-flagship`, and switch Nginx handler to **PHP 8.2-FPM**.

4. **Migration Engine & Script Tooling**:
   - Re-run database scoping (`scope-betheme-config.php`, `scope-legacy-items.php`) tailored specifically for `db207080_ekk`.
   - 6-step automated content migration parser (`migration-content-engine.php`) to convert Muffin Builder shortcodes, columns, and sliders into valid Gutenberg blocks (`wp:paragraph`, `wp:heading`, `wp:image`, `wp:group`, `wp:columns`).
   - Deliver 2 primary shell entrypoints in `bin/`:
     - `bin/01-backstage-setup.sh`: Automated reset, clone of `db207080_ekk` -> `backstage_ekk`, search-replace, and content engine execution.
     - `bin/deploy-production.sh`: Production cutover script (executes theme activation, plugin deactivation, and block sanitization without backstage search-replace).

5. **FSE Theme Standards (`ekkairo-flagship`)**:
   - Pure WordPress Gutenberg FSE block theme.
   - Standard `theme.json` styling, typography, spacing, and color palettes.
   - Custom block patterns (`patterns/`) and custom Gutenberg blocks (`blocks/`) for Hero 3-news slider, 7+1 news grid, Neo Fos subscribe card, and Community Publications banner.
   - Zero invalid block markup (validated against Gutenberg block parser schemas).

6. **Phase Gatekeeper & Human Verification**:
   - Work broken down into explicit sequential phases.
   - Human intervention and sign-off required at the conclusion of every phase.

---

## 3. Project Phase Roadmap

### Phase 1: Environment Setup, Database Scoping & Tooling Port (Current Phase)
- Initialize Git repository in `ekkairo-flagship`.
- Create `/var/www/backstage.ekkairo.org` webroot.
- Setup Nginx virtual host `backstage.ekkairo.org.conf` with upload `proxy_pass` to `ekkairo.org` and host entry in `/etc/hosts`.
- Copy skills from `.agents/skills` into `ekkairo-flagship/.agents/skills`.
- Port and adapt scoping & migration scripts from `ekalexandria-flagship/bin` into `ekkairo-flagship/bin`.
- Create `backstage_ekk` database from `db207080_ekk`.
- *Human Verification Gate 1*.

### Phase 2: Theme Scaffolding & Core Architecture
- Create `style.css`, `functions.php`, `theme.json`, and FSE structure (`templates/`, `parts/`, `patterns/`, `blocks/`, `assets/`).
- Setup Node asset build pipeline (`package.json`, build scripts).
- Implement global styles, color tokens, and responsive typography in `theme.json`.
- *Human Verification Gate 2*.

### Phase 3: Content Migration & Block Conversion
- Execute database scoping analysis on `backstage_ekk`.
- Run automated `migration-content-engine.php` under PHP 7.4.
- Deactivate legacy Betheme and plugins on backstage.
- Switch `backstage.ekkairo.org` Nginx handler to PHP 8.2-FPM.
- Validate zero block errors or invalid block warnings.
- *Human Verification Gate 3*.

### Phase 4: UI/UX Redesign Implementation (Readdy Design System)
- Build homepage block templates (`front-page.html`, `index.html`, `single.html`, `page-publications.html`).
- Implement Hero 3-news slider, 7+1 news grid, Neo Fos subscribe section, and Publications banner.
- Remove legacy sidebar, weather widget, churches, and focus sections per Readdy mockup.
- *Human Verification Gate 4*.

### Phase 5: QA, Performance, SEO/GEO & Production Cutover Tooling
- Audit Lighthouse performance, SEO structured schema, and GEO location tags.
- Finalize `bin/deploy-production.sh`.
- Execute dry-run cutover test.
- Final Human Sign-off & Mission Success.
