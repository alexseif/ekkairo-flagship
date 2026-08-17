# Specification: Phase 5 — QA, Performance, SEO/GEO & Production Cutover

## 1. Objective

Phase 5 executes comprehensive quality assurance, Lighthouse performance optimization, SEO/GEO metadata validation, and finalizes the automated production deployment script (`bin/deploy-production.sh`).

---

## 2. Technical Deliverables

1. **Performance & Speed Optimization**:
   - Zero render-blocking CSS/JS assets.
   - Fluid typography and layout without Cumulative Layout Shift (CLS).
   - Leverage browser caching, Gzip/Brotli compression, and optimized WebP images.
   - Achieve 90+ Lighthouse Performance score.

2. **SEO & GEO Location Optimization**:
   - Enforce single `<h1>` hierarchy per page with semantic HTML5 markup (`<header>`, `<nav>`, `<main>`, `<article>`, `<footer>`).
   - OpenGraph and Twitter Card metadata integration.
   - Structured JSON-LD schema markup (`Organization`, `NGO`, `NewsArticle`, `Place` for Cairo, Egypt location attributes).
   - Meta descriptions and canonical URLs generated dynamically.

3. **Production Cutover Script (`bin/deploy-production.sh`)**:
   - Pre-flight environment check (PHP 8.2 active, required extensions loaded).
   - Backup production database.
   - Execute production content engine migration directly on live database (without backstage domain search-replace).
   - Deactivate legacy Betheme, Betheme-Child, LayerSlider, Muffin Builder, Jetpack, and obsolete plugins.
   - Activate `ekkairo-flagship` theme.
   - Flush Object Cache, W3 Total Cache, and OPcache.
   - Run AST block sanitizer to confirm zero invalid block markup on production.

4. **Final Verification & Project Sign-Off**:
   - Full automated test suite run (`npm test` / PHPUnit if configured).
   - Comprehensive human review and sign-off for live production deployment.

---

## 3. Human Verification & Gate 5 Criteria

- [ ] Lighthouse score meets 90+ performance benchmark.
- [ ] SEO title tags, meta descriptions, and JSON-LD schema validated.
- [ ] GEO location schema for Cairo, Egypt validated.
- [ ] Dry-run test of `bin/deploy-production.sh` completes with zero errors.
- [ ] Final human sign-off & project completion achieved.
