#!/usr/bin/env bash
# Run on the actual WordPress host BEFORE replacing the plugin or applying cleanup.
set -euo pipefail
if [[ $# -ne 2 ]]; then
  echo 'Usage: bash backup-before-cleanup.sh /absolute/wordpress/root /absolute/private/backup-parent' >&2
  exit 2
fi
cleanup_wp_root=$(realpath "$1")
mkdir -p "$2"
cleanup_backup_parent=$(realpath "$2")
case "$cleanup_backup_parent/" in "$cleanup_wp_root/"*) echo 'Backup must be outside the public WordPress root.' >&2; exit 2;; esac
cleanup_site=$(wp --path="$cleanup_wp_root" option get home)
if [[ "$cleanup_site" != 'https://degendeer.com' && "$cleanup_site" != 'https://degendeer.com/' ]]; then
  echo 'Refusing: the selected database is not the formal degendeer.com website.' >&2
  exit 2
fi
umask 077
cleanup_backup_dir=$(mktemp -d "$cleanup_backup_parent/degendeer-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXXXX")
wp --path="$cleanup_wp_root" db export "$cleanup_backup_dir/database.sql" --quiet
test -s "$cleanup_backup_dir/database.sql"
tar -C "$cleanup_wp_root" --exclude='./wp-content/cache' -czf "$cleanup_backup_dir/files.tar.gz" .
tar -tzf "$cleanup_backup_dir/files.tar.gz" > "$cleanup_backup_dir/file-list.txt"
wp --path="$cleanup_wp_root" plugin list --format=json > "$cleanup_backup_dir/plugins.json"
wp --path="$cleanup_wp_root" eval 'echo wp_json_encode(array("home"=>home_url("/"),"show_on_front"=>get_option("show_on_front"),"page_on_front"=>get_option("page_on_front"),"page_for_posts"=>get_option("page_for_posts"),"privacy"=>get_option("wp_page_for_privacy_policy"),"theme"=>get_stylesheet()),JSON_PRETTY_PRINT);' > "$cleanup_backup_dir/state.json"
(cd "$cleanup_backup_dir" && sha256sum database.sql files.tar.gz > SHA256SUMS && sha256sum -c SHA256SUMS)
echo "Backup verified and saved outside the public root: $cleanup_backup_dir"
echo 'This step only backs up; no cleanup or configuration change has been performed.'
echo 'Restore after pausing site writes: extract files.tar.gz into the same WordPress root, then wp --path=ROOT db import DATABASE.SQL; clear actual server/CDN caches and recheck publicly.'
