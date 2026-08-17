# Specification: Phase 1 — Analysis, Staging Setup & Tooling Port

## 1. Phase Objective

The goal of Phase 1 is to establish an isolated local staging environment (`backstage.ekkairo.org`), initialize the `ekkairo-flagship` Git repository, port skill assets, and adapt the database scoping and content migration scripts from `ekalexandria-flagship/bin`.

---

## 2. Scope & Target Artifacts

1. **Git Repository Setup**:
   - Initialize Git repository at `/var/www/ekkairo.org/public/wp-content/themes/ekkairo-flagship`.
   - Add `.gitignore` ignoring `node_modules/`, `.ds_store`, temporary log files, and build caches.
   - Commit initial documentation files (`master_plan.md`, `ARCHITECTURE.md`, `DOS_AND_DONTS.md`, `spec-phase1.md`).

2. **Skill Assets & Agent Toolkit**:
   - Copy `.agents/skills` from `/var/www/ekkairo.org/public/wp-content/themes/ekkairo-gutenberg-theme/.agents/skills` to `ekkairo-flagship/.agents/skills`.

3. **Local Staging Environment Setup (`backstage.ekkairo.org`)**:
   - Webroot directory: `/var/www/backstage.ekkairo.org/public`.
   - Nginx configuration: `/etc/nginx/sites-available/backstage.ekkairo.org.conf` (with symlink to `/etc/nginx/sites-enabled/`).
   - Nginx upstream handler: `unix:/var/run/php/php7.4-fpm.sock` (initially).
   - Nginx uploads proxy: `/wp-content/uploads/` proxy_pass to `https://ekkairo.org`.
   - Domain entry: `127.0.0.1 backstage.ekkairo.org` in `/etc/hosts`.

4. **Staging Database Initialization**:
   - Export production snapshot from `/var/www/ekkairo.org/public` (`db207080_ekk`).
   - Create local staging database `backstage_ekk`.
   - Import snapshot into `backstage_ekk`.
   - Update options table (`siteurl`, `home`) to `https://backstage.ekkairo.org`.

5. **Migration Tooling Port (`bin/`)**:
   - Copy migration scripts from `/var/www/ekalexandria.org/public/wp-content/themes/ekalexandria-flagship/bin/` to `bin/`.
   - Adapt `01-backstage-setup.sh` (renamed `bin/01-backstage-setup.sh`) targeting DB `backstage_ekk` and webroot `/var/www/backstage.ekkairo.org/public`.
   - Adapt database scoping scripts (`scope-betheme-config.php`, `scope-legacy-items.php`).
   - Adapt `migration-content-engine.php` for EKK database structures (removing CPT dependencies).

---

## 3. Verification & Human Gate 1

Before completing Phase 1, the following verification items must pass:
- [ ] Git repository initialized with clean `git status`.
- [ ] Nginx config test `nginx -t` succeeds.
- [ ] Staging URL `https://backstage.ekkairo.org` responds with HTTP 200/301 under PHP 7.4-FPM.
- [ ] Media uploads on `backstage.ekkairo.org` load transparently from live `ekkairo.org` via Nginx proxy_pass.
- [ ] Staging database `backstage_ekk` created and synchronized.
- [ ] `bin/01-backstage-setup.sh` executes cleanly.
- [ ] Human verification and sign-off requested.
