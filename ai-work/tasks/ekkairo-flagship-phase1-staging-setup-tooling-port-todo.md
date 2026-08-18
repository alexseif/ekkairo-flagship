# Todo List: Phase 1 — Analysis, Staging Setup & Tooling Port

- [x] Task 1: Repository Core & Spec Tracking Initialization
- [x] Task 2: Agent Skills Port & Sync
- [x] Task 3: Staging Webroot, Local SSL (OpenSSL) & Nginx Infrastructure Setup
- [x] Task 4: Database Snapshot & Staging DB Provisioning (`backstage_ekk`)
- [x] Task 5: Migration Toolkit Port & Adaptation (`bin/`)
  - [x] Adapt `bin/01-backstage-setup.sh` to deactivate `google-captcha`, `w3-total-cache`, and `jetpack-boost`
  - [x] Disable `WP_CACHE` directive in `wp-config.php` and purge drop-in cache files during setup
- [x] Task 6: Gate 1 Verification & Human Sign-off
