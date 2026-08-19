# Phase 3 Plan: Content Migration Engine & Gutenberg Conversion

**TOPIC NAME**: ekkairo-flagship  
**ISSUE NAME**: phase3-content-migration-gutenberg-conversion  

---

## 1. Objective

Execute the iterative content transformation pipeline on `backstage.ekkairo.org` (`backstage_ekk`). Adapt `bin/02-migrate-content.sh` and `bin/migration-content-engine.php` to scan legacy Betheme Muffin Builder & LayerSlider data, convert shortcodes to valid Gutenberg block markup, log unmapped shortcodes to `ai-work/logs/unmapped-shortcodes.log`, deactivate legacy plugins/themes, validate block AST integrity, and transition Nginx to `php8.2-fpm.sock`.

---

## 2. Assumptions & Architecture Options

### Stated Assumptions
1. **Pre-flight Baseline**: `bin/01-backstage-setup.sh` has already executed and the `backstage_ekk` database is initialized and ready.
2. **Orchestration Script Adaptation**: `bin/02-migrate-content.sh` (previously pointing to `ekalexandria`) will be updated to target `/var/www/backstage.ekkairo.org` and `ekkairo-flagship`.
3. **Migration Engine Runtime**: Content migration scripts execute via WP-CLI under PHP 7.4/8.2 to process legacy Muffin Builder serialized meta before switching the web server environment to PHP 8.2-FPM.
4. **Human Verification Gate before Commit**: After completing each task, verification output will be presented to the human supervisor for explicit approval BEFORE committing changes to git.
5. **Iterative Incremental Workflow**: Migration is performed iteratively: **Scoping → Migration Run → Inspect Results → Adapt Engine → Re-run**.

### Architecture Trade-Off Options (Presented for Awareness)
- **Option A (Direct AST/DOM Block Construction - Recommended)**: Parse Muffin Builder grid/wrappers directly into native Gutenberg block structure comment nodes (`<!-- wp:columns -->`, `<!-- wp:heading -->`) using a clean transform pipeline. Ensures exact block schema adherence.
- **Option B (Regex Replacement + Classic-to-Gutenberg Parser)**: Convert shortcodes to intermediate HTML and pass through `wp.blocks.rawHandler`. Higher risk of invalid block structures or unexpected nesting errors.

---

## 3. Vertical Work Slices & Tasks

### Task 1: Database Scoping & Legacy Content Audit Execution
- **Deliverables**:
  - Run `bin/scope-betheme-config.php` and `bin/scope-legacy-items.php` via WP-CLI on `backstage_ekk`.
  - Generate scoping reports: `ai-work/scopings/betheme-config-scoping.json`, `ai-work/scopings/mfn-pages.json`, `ai-work/scopings/legacy-items-inventory.json`, and `ai-work/scopings/betheme-custom-css.css`.
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
  - Deactivate legacy Betheme theme, Betheme-Child, LayerSlider, Muffin Builder, Jetpack, and obsolete plugins via WP-CLI.
  - Activate `ekkairo-flagship` block theme.
  - Update Nginx site handler `backstage.ekkairo.org.conf` from `php7.4-fpm.sock` to `php8.2-fpm.sock` and reload Nginx service.
- **Acceptance Criteria**:
  - Legacy plugins and themes deactivated; `ekkairo-flagship` active.
  - Site `backstage.ekkairo.org` responds HTTP 200 running on PHP 8.2-FPM.
- **Verification**: `wp theme status`, `wp plugin list`, and Nginx PHP socket check. Stop for human verification before git commit.

### Task 5: Gate 3 Verification & Final Human Review Sign-Off
- **Deliverables**:
  - Complete full Gate 3 verification checklist.
  - Present `unmapped-shortcodes.log` and final migration metrics to human supervisor.
- **Acceptance Criteria**: All 7 criteria from `spec-phase3.md` Section 3 satisfied.

---

## 4. Dependency Graph

```mermaid
graph TD
    T1[Task 1: Database Scoping & Content Audit] --> T2[Task 2: Adapt 02-migrate-content.sh & Content Engine]
    T2 --> T3[Task 3: Iterative Migration & AST/DOM Validation Cycle]
    T3 --> T4[Task 4: Plugin Cleanup, Theme Activation & Nginx PHP 8.2 Switch]
    T4 --> T5[Task 5: Gate 3 Verification & Final Sign-off]
```

---

## 5. Token Cost & Optimization Analysis

| Task / Phase Step | Estimated Input Tokens | Estimated Output Tokens | Estimated Total Tokens | Estimated Cost (USD) |
| :--- | :--- | :--- | :--- | :--- |
| **Task 1: Database Scoping & Content Audit** | ~15,000 | ~3,000 | ~18,000 | ~$0.09 |
| **Task 2: Migration Orchestrator & Content Engine** | ~35,000 | ~10,000 | ~45,000 | ~$0.26 |
| **Task 3: Iterative Migration & Block Validation** | ~25,000 | ~7,000 | ~32,000 | ~$0.18 |
| **Task 4: Runtime Transition & Nginx Switch** | ~18,000 | ~4,000 | ~22,000 | ~$0.11 |
| **Task 5: Gate 3 Verification & Sign-off** | ~12,000 | ~3,000 | ~15,000 | ~$0.08 |
| **Total Estimated Phase 3 Cost** | **~105,000** | **~27,000** | **~132,000** | **~$0.72** |

*Note: Calculations based on standard blended LLM API rates ($3.00/1M input, $15.00/1M output tokens).*

---

## 6. Git & Approval Workflow

- **Human Approval Gate**: After completing each Task (Task 1 to Task 5), execution outputs and verification results will be submitted to the human supervisor. Git commit will ONLY occur upon explicit human approval.
- Working Branch: `phase-3-migration`
- Commit Milestones:
  - Task 1: `feat(migration): execute database scoping and legacy inventory audit`
  - Task 2: `feat(migration): adapt 02-migrate-content.sh orchestrator and content engine`
  - Task 3: `test(gutenberg): complete iterative migration and AST/DOM block validation`
  - Task 4: `ops(runtime): deactivate legacy plugins, activate flagship theme, switch to php8.2-fpm`
  - Task 5: `docs(gate3): complete Phase 3 migration verification and sign-off report`
