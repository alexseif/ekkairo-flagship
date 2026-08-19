# Phase 3 Plan: Content Migration Engine & Gutenberg Conversion

**TOPIC NAME**: ekkairo-flagship  
**ISSUE NAME**: phase3-content-migration-gutenberg-conversion  

---

## 1. Objective

Execute the iterative content transformation pipeline on `backstage.ekkairo.org` (`backstage_ekk`). Audit installed plugins to explicitly disregard legacy builders, sliders, and obsolete utilities, adapt `bin/02-migrate-content.sh` and `bin/migration-content-engine.php` to scan Muffin Builder & LayerSlider data, convert shortcodes to valid Gutenberg block markup, log unmapped shortcodes to `ai-work/logs/unmapped-shortcodes.log`, deactivate legacy plugins/themes, validate block AST integrity, and transition Nginx to `php8.2-fpm.sock`.

---

## 2. Plugin Audit & Disregard Matrix

The following table explicitly classifies all installed plugins on `backstage.ekkairo.org` into **Legacy Plugins to Disregard / Deactivate** vs. **Functional Utilities to Retain**:

### Legacy Plugins to Disregard & Deactivate (Task 4 Target)
| Plugin Slug | Name | Status | Rationale for Disregard | Remediation Path |
| :--- | :--- | :--- | :--- | :--- |
| `js_composer` | WPBakery Page Builder | Active | Legacy page builder; conflicts with Gutenberg | Content migrated to core Gutenberg blocks (`core/columns`, `core/paragraph`, etc.) |
| `LayerSlider` | LayerSlider WP | Active | Legacy jQuery slider plugin | Replaced by FSE Hero Cover / Gallery blocks |
| `awesome-weather` | Awesome Weather Widget | Active | Unmaintained PHP 7 legacy widget | Disregarded; weather functionality retired or replaced with modern block |
| `jetpack` | Jetpack by WordPress.com | Active | Bloated legacy plugin suite; unnecessary in FSE | Deactivated |
| `jetpack-boost` | Jetpack Boost | Inactive | Redundant performance suite | Deactivated |
| `google-captcha` | Google Captcha (reCAPTCHA) | Inactive | Legacy captcha causing staging friction | Deactivated |
| `w3-total-cache` | W3 Total Cache | Inactive | Legacy drop-in cache; conflicts with Nginx | Cache purged & disabled in favor of Nginx FastCGI |
| `ewww-image-optimizer` | EWWW Image Optimizer | Inactive | Obsolete image optimizer | Deactivated |
| `display-posts-shortcode` | Display Posts Shortcode | Active | Legacy query shortcode plugin | Replaced by native `core/query` block |
| `show-hide-author` | Show/Hide Author | Active | Legacy postmeta display hack | Handled cleanly in FSE template hierarchy |
| `mailchimp` | MailChimp for WP | Active | Outdated newsletter integration | Disregarded / Deactivated |
| `facebook-pixel` | Facebook Pixel | Active | Legacy tracking snippet | Disregarded / Deactivated |
| `manage-xml-rpc` | Manage XML-RPC | Active | Obsolete security plugin | XML-RPC access restricted at Nginx level |
| `php-compatibility-checker` | PHP Compatibility Checker | Active | Obsolete diagnostic script | Deactivated |
| `wp-missed-schedule-master` | WP Missed Schedule | Active | Obsolete 2014 cron workaround | Deactivated |

### Retained Functional & Utility Plugins
| Plugin Slug | Name | Status | Rationale for Retention |
| :--- | :--- | :--- | :--- |
| `polylang` | Polylang | Active | Core multi-language translation engine |
| `polylang-theme-strings` | Polylang Theme Strings | Active | Theme string i18n support |
| `contact-form-7` | Contact Form 7 | Active | Active contact form provider |
| `duplicate-post` | Yoast Duplicate Post | Active | Content editing & workflow utility |
| `user-role-editor` | User Role Editor | Active | Admin permission management utility |
| `pdf-embedder` | PDF Embedder | Active | Document embedding utility |
| `pdf-image-generator` | PDF Image Generator | Active | Document thumbnail generator |
| `cloudflare` | Cloudflare | Active | CDN / SSL helper integration |
| `aryo-activity-log` | Activity Log | Active | Admin audit logging utility |
| `disable-comments` | Disable Comments | Active | Comment governance utility |
| `force-regenerate-thumbnails` | Force Regenerate Thumbnails | Active | Utility for thumbnail regeneration after theme migration |

---

## 3. Assumptions & Architecture Options

### Stated Assumptions
1. **Pre-flight Baseline**: `bin/01-backstage-setup.sh` has already executed and the `backstage_ekk` database is initialized and ready.
2. **Orchestrator Script Adaptation**: `bin/02-migrate-content.sh` (previously pointing to `ekalexandria`) will be updated to target `/var/www/backstage.ekkairo.org` and `ekkairo-flagship`.
3. **Migration Engine Runtime**: Content migration scripts execute via WP-CLI under PHP 7.4/8.2 to process legacy Muffin Builder serialized meta before switching the web server environment to PHP 8.2-FPM.
4. **Human Verification Gate before Commit**: After completing each task, verification output will be presented to the human supervisor for explicit approval BEFORE committing changes to git.
5. **Iterative Incremental Workflow**: Migration is performed iteratively: **Scoping → Migration Run → Inspect Results → Adapt Engine → Re-run**.

### Architecture Trade-Off Options (Presented for Awareness)
- **Option A (Direct AST/DOM Block Construction - Recommended)**: Parse Muffin Builder grid/wrappers directly into native Gutenberg block structure comment nodes (`<!-- wp:columns -->`, `<!-- wp:heading -->`) using a clean transform pipeline. Ensures exact block schema adherence.
- **Option B (Regex Replacement + Classic-to-Gutenberg Parser)**: Convert shortcodes to intermediate HTML and pass through `wp.blocks.rawHandler`. Higher risk of invalid block structures or unexpected nesting errors.

---

## 4. Vertical Work Slices & Tasks

### Task 1: Database Scoping & Legacy Content Audit Execution
- **Deliverables**:
  - Run `bin/scope-betheme-config.php` and `bin/scope-legacy-items.php` via WP-CLI on `backstage_ekk`.
  - Generate scoping reports: `ai-work/scopings/betheme-config-scoping.json`, `ai-work/scopings/mfn-pages.json`, `ai-work/scopings/legacy-items-inventory.json`, and `ai-work/scopings/betheme-custom-css.css`.
  - Include plugin scoping audit validating the Disregard Matrix.
- **Acceptance Criteria**:
  - All 4 scoping JSON/CSS files created and populated without PHP errors.
  - Inventory accurately counts pages, MFN builder wrappers, sliders, and shortcodes.
- **Verification**: Verify file existence and inspect JSON metrics in `ai-work/scopings/`. Stop for human verification before git commit.

### Task 2: Migration Orchestrator Adaptation & Content Engine Refinement
- **Deliverables**:
  - Update `bin/02-migrate-content.sh` to fix project paths (`/var/www/backstage.ekkairo.org`, theme `ekkairo-flagship`, log directory `ai-work/logs`).
  - Adapt `bin/migration-content-engine.php` for Muffin Builder sections/wraps/items, headings, text, buttons, and LayerSlider components.
  - Implement Unmapped Shortcode Protocol: log any unhandled shortcodes to `ai-work/logs/unmapped-shortcodes.log`.
- **Acceptance Criteria**:
  - `bin/02-migrate-content.sh` executes cleanly without path or WP-CLI errors.
  - `ai-work/logs/unmapped-shortcodes.log` populated with detailed audit entries for human review.
- **Verification**: Run `bin/02-migrate-content.sh` and inspect execution logs. Stop for human verification before git commit.

### Task 3: Iterative Migration, AST & DOM Markup Validation Cycle
- **Deliverables**:
  - Execute AST validator (`bin/validate-ast.js`) and FSE block sanitizer (`bin/test-fse-sanitizer.php`).
  - Perform iterative refinement: Scoping → Migration Run → Inspect Results → Adapt Engine → Re-run until 0 invalid block errors remain.
- **Acceptance Criteria**:
  - Zero syntax errors or malformed block comments (`<!-- wp:... -->`) found across migrated posts/pages.
  - Gutenberg block editor validation passes without invalid content warnings.
- **Verification**: Execute `bin/test-fse-sanitizer.php` and `bin/validate-ast.js`. Stop for human verification before git commit.

### Task 4: Legacy Plugin Deactivation, Theme Activation & Nginx PHP 8.2 Switch
- **Deliverables**:
  - Deactivate legacy plugins identified in the Disregard Matrix (`js_composer`, `LayerSlider`, `awesome-weather`, `jetpack`, `display-posts-shortcode`, `show-hide-author`, `mailchimp`, `facebook-pixel`, `manage-xml-rpc`, `php-compatibility-checker`, `wp-missed-schedule-master`) and legacy themes (`betheme`, `betheme-child`).
  - Activate `ekkairo-flagship` block theme.
  - Update Nginx site handler `backstage.ekkairo.org.conf` from `php7.4-fpm.sock` to `php8.2-fpm.sock` and reload Nginx service.
- **Acceptance Criteria**:
  - Legacy plugins and themes deactivated; `ekkairo-flagship` active.
  - Retained utility plugins remain active (`polylang`, `contact-form-7`, `duplicate-post`, etc.).
  - Site `backstage.ekkairo.org` responds HTTP 200 running on PHP 8.2-FPM.
- **Verification**: `wp theme status`, `wp plugin list`, and Nginx PHP socket check. Stop for human verification before git commit.

### Task 5: Gate 3 Verification & Final Human Review Sign-Off
- **Deliverables**:
  - Complete full Gate 3 verification checklist.
  - Present `unmapped-shortcodes.log` and final migration metrics to human supervisor.
- **Acceptance Criteria**: All 7 criteria from `spec-phase3.md` Section 3 satisfied.

---

## 5. Dependency Graph

```mermaid
graph TD
    T1[Task 1: Database Scoping & Plugin Audit] --> T2[Task 2: Adapt 02-migrate-content.sh & Content Engine]
    T2 --> T3[Task 3: Iterative Migration & AST/DOM Validation Cycle]
    T3 --> T4[Task 4: Legacy Plugin Deactivation, Theme Activation & Nginx PHP 8.2 Switch]
    T4 --> T5[Task 5: Gate 3 Verification & Final Sign-off]
```

---

## 6. Token Cost & Optimization Analysis

| Task / Phase Step | Estimated Input Tokens | Estimated Output Tokens | Estimated Total Tokens | Estimated Cost (USD) |
| :--- | :--- | :--- | :--- | :--- |
| **Task 1: Database Scoping & Plugin Audit** | ~18,000 | ~4,000 | ~22,000 | ~$0.11 |
| **Task 2: Migration Orchestrator & Content Engine** | ~35,000 | ~10,000 | ~45,000 | ~$0.26 |
| **Task 3: Iterative Migration & Block Validation** | ~25,000 | ~7,000 | ~32,000 | ~$0.18 |
| **Task 4: Runtime Transition & Plugin Deactivation** | ~20,000 | ~5,000 | ~25,000 | ~$0.14 |
| **Task 5: Gate 3 Verification & Sign-off** | ~12,000 | ~3,000 | ~15,000 | ~$0.08 |
| **Total Estimated Phase 3 Cost** | **~110,000** | **~29,000** | **~139,000** | **~$0.77** |

*Note: Calculations based on standard blended LLM API rates ($3.00/1M input, $15.00/1M output tokens).*

---

## 7. Git & Approval Workflow

- **Human Approval Gate**: After completing each Task (Task 1 to Task 5), execution outputs and verification results will be submitted to the human supervisor. Git commit will ONLY occur upon explicit human approval.
- Working Branch: `phase-3-migration`
- Commit Milestones:
  - Task 1: `feat(migration): execute database scoping, legacy inventory and plugin disregard audit`
  - Task 2: `feat(migration): adapt 02-migrate-content.sh orchestrator and content engine`
  - Task 3: `test(gutenberg): complete iterative migration and AST/DOM block validation`
  - Task 4: `ops(runtime): deactivate legacy plugins, activate flagship theme, switch to php8.2-fpm`
  - Task 5: `docs(gate3): complete Phase 3 migration verification and sign-off report`
