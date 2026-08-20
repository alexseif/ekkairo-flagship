# Specification: Phase 4 — UI/UX Redesign Implementation (Readdy Design System)

## 1. Objective

Phase 4 implements the approved visual UI/UX redesign based on the Readdy preview (`https://readdy.cc/preview/1d954d61-ec69-4ca4-9643-e0a7fc396a55/12913398/`) and the specifications from `Rebirth Meeting 2026-07-29.md`.

---

## 2. Technical Deliverables

1. **Homepage Template (`templates/front-page.html`) Structure**:
   - **Section 1: Hero News Slider Block (`blocks/hero-slider`)** — Dynamic PHP Gutenberg block displaying 3 latest sticky/featured news posts with image backgrounds, overlays, and smooth transitions.
   - **Section 2: Welcome Block (`Καλώς ήλθατε`)** — Clean typography section introducing EKK.
   - **Section 3: News Grid Block (`blocks/news-grid`)** — Displays 7 latest news pieces in a dynamic layout + an 8th card CTA leading to the full news archive (`/news` or `/category/news`).
   - **Section 4: About / Purpose & Embassies (`Ίδρυση – Σκοπός & Embassies`)** — Information card grid.
   - **Section 5: Neo Fos Subscribe Block (`blocks/subscribe-card`)** — Newsletter subscription form card.
   - **Section 6: Social Media Attraction Block** — High-visibility social channels banner (Facebook, Instagram, YouTube).
   - **Section 7: Community Publications Banner (`blocks/publications-banner`)** — Banner linking to the new Community Publications page with inquiry CTA.
   - **Deletions Enforced**: Focus section, Churches section, Weather widget, and legacy sidebar completely removed. Widgets integrated into main content flow where applicable.

2. **Dedicated Pages & Header/Footer**:
   - **Community Publications Page (`templates/page-publications.html`)**: Dedicated page layout showcasing community publications, historical archives, and acquisition inquiry buttons.
   - **Contact Page**: 3-language discovery layout (Greek, Arabic, English text blocks for location/contact info).
   - **Header Template Part (`parts/header.html`)**: Fluid logo scaling, navigation menu, clean header utility links. Sticky/overlay header behavior (starts transparent over full-width hero carousel, animates to solid white background with crisp contrast on scroll).
   - **Footer Template Part (`parts/footer.html`)**: Community links, copyright, and institutional footer layout.

3. **Block Patterns (`patterns/`)**:
   - Register reusable FSE patterns for hero sections, news cards, publication banners, and content callouts.

---

## 3. Human Verification & Gate 4 Criteria

- [ ] Homepage renders matching the approved Readdy visual preview.
- [ ] Hero 3-news slider renders dynamically from WordPress posts.
- [ ] 7+1 News Grid correctly displays 7 posts and the 8th Archive CTA card.
- [ ] Community Publications page template built and functional.
- [ ] Sidebar, weather widget, churches section, and focus section completely removed.
- [ ] Mobile responsive layout scales cleanly across desktop, tablet, and mobile viewports.
- [ ] Human review and explicit approval requested before advancing to Phase 5.
