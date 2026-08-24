# Phase 4 Plan: UI/UX Redesign Implementation (Readdy Design System)

**TOPIC NAME**: ekkairo-flagship  
**ISSUE NAME**: phase4-ui-ux-redesign  

---

## 1. Objective

Implement the complete, section-by-section visual UI/UX redesign for `ekkairo-flagship` on `backstage.ekkairo.org`, matching 100% of the Readdy design system preview (`https://readdy.cc/preview/1d954d61-ec69-4ca4-9643-e0a7fc396a55/12913398/`), local export (`new/Ελληνική Κοινότητα Καΐρου - Επίσημη Ιστοσελίδα.html`), screenshot reference (`new/Ελληνική-Κοινότητα-Καΐρου-Επίσημη-Ιστοσελίδα-08-20-2026_06_53_PM.png`), and `spec-phase4.md`.

---

## 2. Comprehensive Section Inventory & Component Mapping

| Section # | Visual Component Name | Target File / Location | Key Design & Architecture Specifications |
| :--- | :--- | :--- | :--- |
| **Section 0** | **Header & Navigation Bar** | `parts/header.html` & `src/` | Fixed overlay starting transparent over Hero carousel (`bg-transparent`, text light), transitioning via JS scroll listener to solid white (`.is-scrolled`, `#ffffff`, dark text). Top utility bar (`Ειδήσεις`, `Νέο Φως`, Search), brand logo + titles, responsive navigation menu. |
| **Section 1** | **Hero News Carousel** | `blocks/hero-slider/` | Dynamic PHP block querying 3 latest sticky/featured news posts. Full-width slider container (480px mobile / 600px desktop height), dark gradient overlay (`from-black/60 via-black/20 to-black/10`), post title text with drop shadow, prev/next arrows, dot indicators. |
| **Section 2** | **Welcome Intro Card** | `patterns/welcome-card.php` / `templates/front-page.html` | Centered welcome box (`max-w-3xl`) with light primary background (`bg-primary-50/70`), subtle border (`border-primary-100/60`), intro text starting with bold "Καλώς ήλθατε". |
| **Section 3** | **News Grid (7 + 1 CTA Card)** | `blocks/news-grid/` | Dynamic PHP block querying 7 latest news posts. 4-column responsive grid: 7 post cards (image `h-40`, date icon, title line-clamp 2, excerpt line-clamp 2, "Διαβάστε περισσότερα" link) + 8th Card CTA box leading to `/category/news/` ("Όλες οι Ειδήσεις", "Προς τις ειδήσεις ->"). |
| **Section 4** | **About / Purpose Box** | `patterns/about-purpose.php` / `templates/front-page.html` | Section heading with red vertical bar (`bg-accent-500`), white card container, 1904 founding history & mission text, "Διαβάστε περισσότερα" link to `/idrysi-skopos/`. |
| **Section 5** | **Publications Promo Banner** | `blocks/publications-banner/` | Dark navy blue card (`bg-primary-800`), background image overlay (`L1003234_preview-1024x683.jpg`), book cover thumbnail (`NEO_FOS-1.jpg`), "Βιβλία" badge, heading "Βιβλία που Εκδόθηκαν από την Κοινότητα", red CTA button ("Μετάβαση στις Εκδόσεις" linking to `/publications/`). |
| **Section 6** | **Neo Fos Subscribe Card** | `blocks/subscribe-card/` | Newsletter subscription block with dark gradient background, "Newsletter" badge, title "Εγγραφείτε στο Νέο Φως", Name & Email input fields, red submit button ("Εγγραφή"). |
| **Section 7** | **Social Media Attraction Card** | `patterns/social-attraction.php` / `templates/front-page.html` | White card box with share icon badge, title "Μείνετε συνδεδεμένοι", 4 circular brand social buttons: Facebook (`#1877F2`), Instagram (gradient), YouTube (`#FF0000`), Twitter/X (`#000000`). |
| **Section 8** | **Neo Fos Issues Quick Grid** | `patterns/neo-fos-grid.php` / `templates/front-page.html` | Section heading "Νέο Φως — Τελευταία τεύχη", 3-column grid of 6 newspaper issue cards with `ri-newspaper-line` icon, issue title, and link "-> Διαβάστε". |
| **Section 9** | **Institutional Footer & Legal Bar** | `parts/footer.html` & `src/` | Light background (`#F8F9FA` / `bg-background-100`). 3-column grid: Col 1 EKK Logo + About text + Follow Us icons; Col 2 Navigation grid (14 links in 2 columns); Col 3 Search form + Newsletter button. Bottom legal bar (`bg-background-200/50`) with Copyright and Privacy Policy / Contact links. |

---

## 3. Work Breakdown & Vertical Slices

### Task 1: Design Tokens, CSS Utility System & Theme Reset
- Reset theme assets to clean baseline while preserving `new/` directory.
- Update `theme.json` with exact Readdy color palette (`primary`, `primary-dark`, `secondary`, `accent`, `dark-neutral`, `light-neutral`, `border-gray`, `muted`), typography presets (`Inter` sans-serif, `Source Serif 4` serif), fluid spacing, and layout sizes.
- Setup `src/index.scss` with CSS design token references and base resets.

### Task 2: Core Header & Footer Template Parts (`parts/header.html`, `parts/footer.html`)
- Rebuild `parts/header.html` with top bar utility links, brand logo/titles, and responsive navigation.
- Implement Option A sticky header scroll dynamics (`src/index.js` scroll listener toggling `.is-scrolled`).
- Rebuild `parts/footer.html` matching Readdy 3-column layout + legal bottom bar.

### Task 3: Custom Gutenberg Blocks Development (`blocks/`)
- **Block 1**: `blocks/hero-slider` (Dynamic PHP block, 3 sticky/featured posts, slider controls, dot indicators, gradient overlay).
- **Block 2**: `blocks/news-grid` (Dynamic PHP block, 7 latest posts + 8th archive CTA card).
- **Block 3**: `blocks/publications-banner` (Community Publications banner block with book thumbnail & red CTA).
- **Block 4**: `blocks/subscribe-card` (Neo Fos newsletter subscription card with inputs & submit button).
- Register all blocks in `functions.php` and verify render callbacks via WP-CLI.

### Task 4: Block Patterns & Template Assembly (`patterns/`, `templates/front-page.html`, `templates/page-publications.html`)
- Register reusable block patterns for Section 2 (Welcome), Section 4 (About/Purpose), Section 7 (Social Attraction), and Section 8 (Neo Fos Issues Grid).
- Assemble complete 9-section homepage template in `templates/front-page.html`.
- Build dedicated Community Publications page template (`templates/page-publications.html`).
- Build Contact page 3-language layout (Greek, Arabic, English text blocks).
- Enforce deletion of legacy sidebars, weather widget, focus section, and churches section.

### Task 5: Gate 4 Verification & Quality Audit
- Conduct full audit against all Gate 4 acceptance criteria in `spec-phase4.md`.
- Verify mobile/tablet/desktop responsive scaling across viewports.
- Compile production assets via `npm run build`.
- Present Phase 4 completion report to human supervisor.

---

## 4. Git & Approval Workflow

- **Human Review Gate**: Every task requires explicit human review and approval ("yes" or "proceed") before git commit or advancing to the next task.
- Commit Milestones:
  - Task 1: `feat(theme): configure theme.json design tokens and CSS base resets`
  - Task 2: `feat(parts): implement header scroll dynamics and 3-column footer part`
  - Task 3: `feat(blocks): implement dynamic Gutenberg blocks hero-slider, news-grid, publications-banner, subscribe-card`
  - Task 4: `feat(templates): assemble 9-section front-page and dedicated page templates`
  - Task 5: `docs(gate4): complete Phase 4 UI/UX redesign verification and sign-off report`
