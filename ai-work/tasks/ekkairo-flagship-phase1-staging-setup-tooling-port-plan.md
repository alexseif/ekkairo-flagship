# Phase 1 Specification Plan: Analysis, Staging Setup & Tooling Port

**TOPIC NAME**: ekkairo-flagship  
**ISSUE NAME**: phase1-staging-setup-tooling-port  

## 1. Objective
Establish local isolated staging environment (`backstage.ekkairo.org`), initialize theme Git repository, port development agent skills, setup local self-signed SSL certificates via OpenSSL, configure Nginx virtual host, create staging database `backstage_ekk`, and adapt database scoping & content migration scripts in `bin/`.

## 2. Technical Scope & Slices
1. **Task 1: Repository Core & Spec Tracking Initialization**
   - Track `.gitignore`, `master_plan.md`, `ARCHITECTURE.md`, `DOS_AND_DONTS.md`, and all `spec-phase*.md` files.
   - Commit core specs to Git repository.
2. **Task 2: Agent Skills Port & Sync**
   - Port `.agents/skills` from `/var/www/ekkairo.org/public/wp-content/themes/ekkairo-gutenberg-theme/.agents/skills`.
   - Commit agent skills.
3. **Task 3: Staging Webroot, Local SSL (OpenSSL) & Nginx Infrastructure**
   - Webroot: `/var/www/backstage.ekkairo.org/public`.
   - Generate local SSL certificates (`backstage.ekkairo.org.crt` / `key`) using OpenSSL in `/etc/ssl/certs/` or `/etc/ssl/private/`.
   - Nginx config: `/etc/nginx/sites-available/backstage.ekkairo.org.conf` linked to `/etc/nginx/sites-enabled/`.
   - Initial FastCGI handler: `unix:/var/run/php/php7.4-fpm.sock`.
   - SSL directives: `ssl_certificate` and `ssl_certificate_key`.
   - Media uploads proxying: `/wp-content/uploads/` proxy_pass to `https://ekkairo.org`.
   - Local DNS mapping: `127.0.0.1 backstage.ekkairo.org` in `/etc/hosts`.
4. **Task 4: Database Snapshot & Staging DB Provisioning (`backstage_ekk`)**
   - Snapshot production database `db207080_ekk`.
   - Create local staging database `backstage_ekk` and import snapshot.
   - Run WP-CLI search-replace to update URLs to `https://backstage.ekkairo.org`.
5. **Task 5: Migration Toolkit Port & Adaptation (`bin/`)**
   - Copy scripts from `/var/www/ekalexandria.org/public/wp-content/themes/ekalexandria-flagship/bin/` to `bin/`.
   - Adapt `bin/01-backstage-setup.sh` and `bin/deploy-production.sh` for EKK paths and databases.
   - Adapt `scope-betheme-config.php` and `scope-legacy-items.php`.
6. **Task 6: Gate 1 Verification & Human Sign-off**
   - Verify all 7 criteria from `spec-phase1.md` plus local SSL functionality.

## 3. Dependency Graph
Task 1 -> Task 2 -> Task 3 -> Task 4 -> Task 5 -> Task 6 (Gate 1)
