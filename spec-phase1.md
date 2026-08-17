# Specification: Phase 1 — Analysis, Staging Setup & Tooling Port

## 1. Objective

Phase 1 establishes the local isolated staging environment (`backstage.ekkairo.org`), initializes the `ekkairo-flagship` Git repository, ports development skills, and adapts database scoping & content migration scripts from `ekalexandria-flagship/bin`.

---

## 2. Technical Deliverables

1. **Git Repository Initialization**:
   - Repository root: `/var/www/ekkairo.org/public/wp-content/themes/ekkairo-flagship`.
   - Track `.gitignore`, `master_plan.md`, `ARCHITECTURE.md`, `DOS_AND_DONTS.md`, and all `spec-phase*.md` files.

2. **Agent Skills Import**:
   - Import `.agents/skills` from `/var/www/ekkairo.org/public/wp-content/themes/ekkairo-gutenberg-theme/.agents/skills`.

3. **Staging Webroot & Nginx Configuration**:
   - Webroot: `/var/www/backstage.ekkairo.org/public`.
   - Nginx site config: `/etc/nginx/sites-available/backstage.ekkairo.org.conf` linked to `/etc/nginx/sites-enabled/`.
   - Initial FastCGI handler: `unix:/var/run/php/php7.4-fpm.sock`.
   - Media uploads proxying: `/wp-content/uploads/` proxy_pass to `https://ekkairo.org`.
   - Local DNS mapping: `127.0.0.1 backstage.ekkairo.org` in `/etc/hosts`.

4. **Staging Database (`backstage_ekk`)**:
   - Snapshot production database `db207080_ekk`.
   - Create local staging database `backstage_ekk` and import snapshot.
   - Run WP-CLI search-replace to update URLs to `https://backstage.ekkairo.org`.

5. **Migration Toolkit Port (`bin/`)**:
   - Copy scripts from `/var/www/ekalexandria.org/public/wp-content/themes/ekalexandria-flagship/bin/` to `bin/`.
   - Adapt `bin/01-backstage-setup.sh` and `bin/deploy-production.sh` targeting EKK paths and databases.
   - Adapt `scope-betheme-config.php` and `scope-legacy-items.php`.

---

## 3. Human Verification & Gate 1 Criteria

- [ ] Git repository initialized with all plan, architecture, and phase spec files committed.
- [ ] Staging webroot `/var/www/backstage.ekkairo.org/public` created.
- [ ] Nginx configuration active and `nginx -t` passes.
- [ ] `https://backstage.ekkairo.org` responds cleanly under PHP 7.4-FPM.
- [ ] Database `backstage_ekk` loaded and URLs replaced.
- [ ] Media assets load transparently from live site via Nginx `proxy_pass`.
- [ ] Human review and explicit approval requested before advancing to Phase 2.
