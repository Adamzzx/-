# WordPress implementation

Standalone classic theme and companion plugin for existing PHP/MySQL hosting. The Next.js project is preserved unchanged. Components are GPL-2.0-or-later.

Build uploadable archives: `python wordpress/build.py`.

See [安装说明](安装说明.md) for owner-facing installation. No secrets, referral destinations or invented exchange facts are included in the archives.

Local test environment (isolated from the Next.js database and Hostinger):

```bash
docker compose -f wordpress/tests/compose.yaml up -d wordpress
docker compose -f wordpress/tests/compose.yaml run --rm -T cli wp core install --url=http://localhost:8088 --title='交易所指南测试站' --admin_user=smoke-admin --admin_password=local-test-only --admin_email=smoke@example.invalid --skip-email
docker compose -f wordpress/tests/compose.yaml run --rm -T cli wp plugin activate exchange-guide
docker compose -f wordpress/tests/compose.yaml run --rm -T cli wp theme activate exchange-guide
docker compose -f wordpress/tests/compose.yaml run --rm -T cli wp rewrite structure '/%postname%/'
docker compose -f wordpress/tests/compose.yaml run --rm -T cli wp eval 'cg_setup_pages(true); update_option("blog_public",0);'
docker compose -f wordpress/tests/compose.yaml run --rm -T cli wp eval-file - < wordpress/tests/assertions.php
python wordpress/tests/http-smoke.py
```

The local credentials above are exclusively for the loopback test environment. Do not use them in production. Assertions create test articles and a subscriber in this dedicated WordPress instance. ZIP installation is validated separately with source mounts removed, using `compose.packages.yaml`. No local previews are published. The packaged installation and functional checks passed on WordPress 7.1.3 / PHP 8.3.35.

After ZIP installation, `python wordpress/tests/admin-smoke.py` verifies the actual CMS login, setup form, CSRF protection and existing homepage preservation.

Phase 1 / 1.1.0: non-destructive navigation setup, shared topic taxonomy, primary topic and related exchanges. Run `wp eval-file - < wordpress/tests/navigation.php` in the local CLI container, then `python wordpress/tests/navigation-http.py`. A published test guide is created only in the isolated test database. Desktop dropdown and mobile collapse use a small standalone script; no-JavaScript navigation stays visible. Homepage composition is reserved for Phase 2.

1.4.0 fixes the first-install experience: the theme renders the confirmed dropdown navigation and all four near-top registration banners without requiring the plugin or seeded exchange records. Registration destinations remain centralized; missing offers are disclosed, never synthesized. `tests/layout-states.cjs` exercises initialized, uninitialized, and theme-only installations at desktop/mobile widths against the disposable Docker test site and restores its state afterward. It requires Playwright and Chromium (`CG_CHROMIUM_PATH`, default `/usr/bin/chromium`).

1.5.0 reorganizes the CMS administration: exchange CPTs share one menu; tokenized-stock and crypto desks aggregate authorized existing content by topic. An explicit nonce-protected action creates an attributed auto-draft. Empty exchange lists link to initialization. `tests/admin-content-http.py` covers menus, aggregation, input/CSRF protection, draft attribution and the empty-state notice.

1.6.0 opens registration banners directly through `/go/` in new tabs. Four centralized official defaults apply without seeded exchange records, while existing enabled valid referral URLs take precedence. `tests/direct-registration.php` checks precedence, expiry and unsafe-link fallback; `tests/direct-registration-browser.cjs` checks actual new-tab navigation with mocked external registration responses.

1.7.0 adds paginated related editorial content beneath each exchange hub using the existing private exchange associations. Checkbox selection supports multiple platforms; publication/password filters exclude nonpublic content, and hub cache invalidation follows content/association changes. `tests/exchange-content.php` covers types, exact ID matching, legacy metadata, privacy, pagination and cache notifications; `tests/exchange-hub-browser.cjs` covers rendered cards, sponsorship, pagination, article navigation and mobile layout with disposable fixtures.

1.8.0 adds an optional native-post review template, empty Gutenberg review pattern and editable provenance tables. Draft creation is explicit, idempotent per editor, and never publishes. Review policy is closed by default and requires a valid recommendation, verified official terms, product/country constraints, reader confirmation and a short-lived signed redirect; self-reported country is not KYC. Known SEO plugins take precedence over the built-in Article graph. Review block snapshots and source metadata use WordPress revisions. `tests/review-template.php`, `review-admin-http.py` and `review-browser.cjs` validate the policy, native draft/editor flow and rendered experience.

1.9.0 applies the simple editorial renderer automatically to native posts and existing editorial CPTs. Existing bodies/URLs/associations remain unchanged; TOC, related content and SEO no longer depend on choosing a special template. Standard editor blocks remain the default, advanced verification blocks stay optional. Article CTA restrictions continue; homepage registrations are unchanged. `tests/simple-article-browser.cjs` covers ordinary posts with existing associations and no template/primary-platform configuration, including mobile scroll, portrait images, Schema, TOC and closed-by-default CTA.
