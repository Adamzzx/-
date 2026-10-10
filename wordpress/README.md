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
