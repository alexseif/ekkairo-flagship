# Phase 4 Task List: UI/UX Redesign Implementation (Readdy Design System)

- [x] Task 1: Design Tokens, CSS Utility System & Theme Reset
  - [x] Reset theme files to clean baseline while preserving `new/` directory
  - [x] Update `theme.json` with exact Readdy colors (`primary`, `secondary`, `accent`, `dark-neutral`, `light-neutral`, `border-gray`, `muted`), font families (`Inter`, `Source Serif 4`), and layout dimensions
  - [x] Configure `src/index.scss` with CSS token references and mobile-first resets
  - [x] **Human Verification Gate**: Present results for human review before git commit

- [x] Task 2: Core Header & Footer Template Parts (`parts/header.html`, `parts/footer.html`)
  - [x] Section 0: Implement `parts/header.html` with top utility bar (`Ειδήσεις`, `Νέο Φως`, Search), brand logo/titles, and responsive navigation
  - [x] Implement sticky transparent-to-white header scroll dynamics in `src/index.js` & `src/index.scss` (`.site-header.is-scrolled`)
  - [x] Section 9: Implement `parts/footer.html` matching Readdy 3-column layout (Brand/About, 14 Navigation links, Search & Subscribe) + bottom legal bar
  - [x] Compile via `npm run build` and verify header/footer rendering
  - [x] **Human Verification Gate**: Present results for human review before git commit

- [x] Task 3: Custom Dynamic Gutenberg Blocks Development (`blocks/`)
  - [x] Section 1: Implement `blocks/hero-slider` (dynamic PHP block, 3 latest sticky/featured posts, slider controls, dot indicators, gradient overlay)
  - [x] Section 3: Implement `blocks/news-grid` (dynamic PHP block, 7 latest posts + 8th archive CTA card)
  - [x] Section 5: Implement `blocks/publications-banner` (Community Publications banner block with book thumbnail & red CTA)
  - [x] Section 6: Implement `blocks/subscribe-card` (Neo Fos newsletter subscription card with Name/Email inputs & submit button)
  - [x] Register block render callbacks in `functions.php` and verify block API compliance via WP-CLI
  - [x] **Human Verification Gate**: Present results for human review before git commit

- [x] Task 4: Block Patterns & Template Assembly (`patterns/`, `templates/front-page.html`, `templates/page-publications.html`)
  - [x] Section 2: Create Welcome card block pattern (`patterns/welcome-card.php`)
  - [x] Section 4: Create About/Purpose card block pattern (`patterns/about-purpose.php`)
  - [x] Section 7: Create Social Attraction block pattern (`patterns/social-attraction.php`)
  - [x] Section 8: Create Neo Fos Issues quick grid block pattern (`patterns/neo-fos-grid.php`)
  - [x] Assemble complete 9-section homepage template in `templates/front-page.html`
  - [x] Build dedicated Community Publications page template (`templates/page-publications.html`)
  - [x] Build Contact page 3-language discovery layout (Greek, Arabic, English text blocks)
  - [x] Confirm complete deletion of legacy focus section, churches section, weather widget, and sidebars
  - [x] **Human Verification Gate**: Present results for human review before git commit

- [x] Task 5: Gate 4 Verification & Quality Audit
  - [x] Validate all Gate 4 checklist criteria from `spec-phase4.md`
  - [x] Verify responsive layout across mobile, tablet, and desktop viewports
  - [x] Compile final production assets via `npm run build`
  - [x] Present Phase 4 completion report to human supervisor
