# Phase 3 Task List: Content Migration Engine & Gutenberg Conversion

- [ ] Task 1: Database Scoping & Legacy Content Audit Execution
  - [ ] Run `bin/scope-betheme-config.php` and `bin/scope-legacy-items.php`
  - [ ] Verify scoping outputs in `ai-work/scopings/`
- [ ] Task 2: Automated Migration Engine Adaptation & Unmapped Shortcode Logging
  - [ ] Update `bin/migration-content-engine.php` with Muffin Builder transforms & LayerSlider handling
  - [ ] Implement `ai-work/logs/unmapped-shortcodes.log` logging protocol
  - [ ] Execute content engine under PHP 7.4
- [ ] Task 3: Gutenberg AST & DOM Markup Validation
  - [ ] Run `bin/test-fse-sanitizer.php` and `bin/validate-ast.js`
  - [ ] Ensure 0 block editor invalid content warnings
- [ ] Task 4: Runtime Transition, Plugin Cleanup & Nginx Switch
  - [ ] Run plugin deactivations via WP-CLI and activate `ekkairo-flagship`
  - [ ] Reconfigure Nginx site handler to `php8.2-fpm.sock` and reload
- [ ] Task 5: Gate 3 Verification & Human Review Sign-Off
  - [ ] Present Gate 3 report and `unmapped-shortcodes.log` to human supervisor
