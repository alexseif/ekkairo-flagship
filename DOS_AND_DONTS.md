# Development Guidelines: Do's and Don'ts

## 1. Core Principles

This codebase is governed by strict rules designed to ensure zero downtime, high performance, clean Gutenberg block markup, and compatibility with PHP 8.2 LTS.

---

## 2. Technical Do's (Mandatory Practices)

- **DO** follow the PHP 7.4 -> 8.2 transition sequence: run database scoping and shortcode content parsing under PHP 7.4 while Betheme functions exist, then deactivate legacy plugins and switch environment to PHP 8.2.
- **DO** test all generated block content for Gutenberg validity (zero "block contains invalid content" warnings in WordPress block editor).
- **DO** use `theme.json` for global styles, font sizes, colors, layout dimensions, and block styling defaults.
- **DO** commit pre-built production CSS/JS assets in Git so production deployment requires zero Node.js / `npm` compilation steps on the live server.
- **DO** maintain strict isolation between local staging (`backstage_ekk` on `backstage.ekkairo.org`) and production (`db207080_ekk` on `ekkairo.org`).
- **DO** create automated shell scripts (`bin/01-backstage-setup.sh` and `bin/deploy-production.sh`) that can execute end-to-end without manual intervention.
- **DO** validate JSON files (`theme.json`, `package.json`, block metadata) and JavaScript code (`npm run build` or `node -c`) before committing.
- **DO** request human verification and sign-off at the conclusion of every phase before advancing to the next phase.

---

## 3. Technical Don'ts (Strictly Forbidden)

- **DON'T** touch live production files or databases directly; all work happens on local staging `backstage.ekkairo.org` until final cutover.
- **DON'T** hardcode colors, inline styles, or pixel sizes into PHP or HTML templates when they can be represented as `theme.json` design tokens.
- **DON'T** rely on Polylang or external translation plugins (Polylang is deprecated and abandoned for this project).
- **DON'T** migrate Custom Post Types (CPTs); EKK uses standard posts, pages, categories, and tags.
- **DON'T** use Jetpack, Muffin Builder, LayerSlider, or RevSlider in the new theme.
- **DON'T** write raw string append operations on uninitialized array variables in PHP (violates PHP 8.2 strict syntax).
- **DON'T** write `cd` commands in shell scripts without explicit directory validation or `set -e`.
