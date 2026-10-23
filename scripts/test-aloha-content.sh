#!/bin/bash
# Disposable real WordPress, using the existing pinned images. Never tests against :8088.
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
if [ "${CONTENT_GALLERY_TEST:-0}" = 1 ]; then export CONTENT_HERO_TEST=1; fi
if [ "${CONTENT_HERO_TEST:-0}" = 1 ]; then export CONTENT_AREA_TEST=1; fi
run_dir="$(mktemp -d "$PWD/.local/content-test-XXXXXX")"
project="aloha-content-test-$(basename "$run_dir" | tr '[:upper:]' '[:lower:]')"
test_compose="$run_dir/compose.json"
cleanup() {
  docker compose --project-name "$project" --file "$test_compose" exec -T wordpress sh -c 'if [ -f wp-content/debug.log ]; then cat wp-content/debug.log; fi' > "$run_dir/wp-debug.log" 2>/dev/null || true
  docker compose --project-name "$project" --file "$test_compose" down --volumes --remove-orphans >/dev/null 2>&1 || true
  rm -f "$test_compose" "$run_dir/auth.json" "$run_dir/offline-updates.php"
}
trap cleanup EXIT
python3 - "$run_dir" <<'PY'
import ipaddress, json, os, secrets, subprocess, sys
from pathlib import Path
root=Path.cwd(); target=Path(sys.argv[1])
# Capture privately: docker compose config contains local credentials. Replace all of them.
c=json.loads(subprocess.check_output(['docker','compose','--file','compose.yaml','--profile','tools','config','--format','json']))
dbpass,rootpass,adminpass=(secrets.token_hex(24) for _ in range(3))
netids=subprocess.check_output(['docker','network','ls','-q'],text=True).split()
networks=json.loads(subprocess.check_output(['docker','network','inspect',*netids])) if netids else []
used=[ipaddress.ip_network(x['Subnet']) for n in networks for x in ((n.get('IPAM') or {}).get('Config') or []) if x.get('Subnet')]
subnet=next(str(candidate) for candidate in (ipaddress.ip_network(f'10.203.{i}.0/24') for i in range(89,150)) if not any(candidate.overlaps(old) for old in used if old.version==4))
c.pop('name',None)
c.pop('x-wordpress-env',None)  # Already expanded into services; discard the original credential-bearing anchor.
(target/'offline-updates.php').write_text('''<?php
// Test-only offline fixture for Core update/translation checks; no external requests.
if (!defined('ALOHA_CONTENT_TEST_SITE') || !ALOHA_CONTENT_TEST_SITE) { return; }
add_filter('pre_http_request', function ($pre, $args, $url) {
 if (parse_url($url, PHP_URL_HOST) !== 'api.wordpress.org') { return $pre; }
 $path = parse_url($url, PHP_URL_PATH);
 if (str_starts_with($path, '/core/version-check/')) { $body = array('offers'=>array()); }
 elseif (str_starts_with($path, '/plugins/update-check/')) { $body = array('plugins'=>(object) array(), 'no_update'=>(object) array(), 'translations'=>array()); }
 elseif (str_starts_with($path, '/themes/update-check/')) { $body = array('themes'=>(object) array(), 'no_update'=>(object) array(), 'translations'=>array()); }
 elseif (str_starts_with($path, '/translations/')) { $body = array('translations'=>array()); }
 else { return $pre; }
 return array('response'=>array('code'=>200,'message'=>'OK'), 'headers'=>array(), 'body'=>wp_json_encode($body), 'cookies'=>array());
}, 10, 3);
add_filter('aloha_content_area_base', function ($base) { return get_option('aloha_content_test_area_base', $base); });
add_filter('aloha_content_guide_slug', function ($slug) { return get_option('aloha_content_test_guide_slug', $slug); });
add_filter('aloha_content_investor_base', function ($base) { return get_option('aloha_content_test_investor_base', $base); });
add_action('template_redirect', function () {
 global $wp_query;
 header('X-Aloha-Test-Object-ID: ' . get_queried_object_id());
 header('X-Aloha-Test-Post-Type: ' . (get_post_type(get_queried_object_id()) ?: 'none'));
 header('X-Aloha-Test-Is-404: ' . (is_404() ? '1' : '0'));
 header('X-Aloha-Test-Is-Preview: ' . (is_preview() ? '1' : '0'));
 $object = get_queried_object();
 header('X-Aloha-Test-Taxonomy: ' . ($object instanceof WP_Term ? $object->taxonomy : 'none'));
 header('X-Aloha-Test-Posts: ' . implode(',', wp_list_pluck($wp_query->posts, 'ID')));
}, 9);
''')
for service in c['services'].values():
 service.pop('depends_on',None)
 env=service['environment']
 if 'MARIADB_DATABASE' in env:
  env.update(MARIADB_DATABASE='aloha_content_test',MARIADB_USER='aloha_test',MARIADB_PASSWORD=dbpass,MARIADB_ROOT_PASSWORD=rootpass)
 else:
  env.update(WORDPRESS_DB_NAME='aloha_content_test',WORDPRESS_DB_USER='aloha_test',WORDPRESS_DB_PASSWORD=dbpass)
  env['WORDPRESS_CONFIG_EXTRA'] += "\ndefine('ALOHA_CONTENT_TEST_SITE', true);\n"
  service['volumes'].append({'type':'bind','source':str(target/'offline-updates.php'),'target':'/var/www/html/wp-content/mu-plugins/aloha-content-test-offline.php','read_only':True})
 service['volumes'].append({'type':'bind','source':str(root/'scripts'),'target':'/var/www/aloha-tests','read_only':True})
for name in ('database','wordpress'):
 c['volumes'][name].pop('name',None)
c['networks']['default'].pop('name',None)
c['networks']['default']['ipam']['config']=[{'subnet':subnet}]
c['services']['wordpress']['ports']=[{'host_ip':'127.0.0.1','published':'8091','target':80,'protocol':'tcp'}]
c['services']['cli']['environment'].update(WP_ADMIN_USER='aloha_test_admin',WP_ADMIN_PASSWORD=adminpass,WP_ADMIN_EMAIL='test@example.test')
if os.environ.get('CONTENT_AREA_TEST') == '1':
 media=Path(os.environ.get('ALOHA_AREA_MEDIA_DIR',str(root/'.local/reference-area/assets'))).resolve()
 if not media.is_dir(): raise SystemExit('Extract the supplied Area design assets locally, or set ALOHA_AREA_MEDIA_DIR.')
 c['services']['cli']['volumes'].append({'type':'bind','source':str(media),'target':'/var/www/aloha-area-media','read_only':True})
(target/'compose.json').write_text(json.dumps(c))
(target/'auth.json').write_text(json.dumps({'url':'http://127.0.0.1:8091','user':'aloha_test_admin','password':adminpass}))
print('Isolated test project prepared; HTTP port 8091, private disposable volumes.')
PY
dc() { docker compose --project-name "$project" --file "$test_compose" "$@"; }
wp() { dc run --rm --no-deps -T cli "$@"; }
dc up -d --wait --wait-timeout 180 db
dc up -d --wait --wait-timeout 180 wordpress
dc run --rm --no-deps -T --entrypoint sh cli -ec 'wp core install --url=http://127.0.0.1:8091 --title="Aloha Content isolated tests" --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" --admin_email="$WP_ADMIN_EMAIL" --skip-email'
wp option update blog_public 0
wp rewrite structure '/%postname%/' --hard
wp theme activate aloha-hello
wp plugin activate aloha-content
wp eval 'wp_version_check(); wp_update_plugins(); wp_update_themes(); require_once ABSPATH . "wp-admin/includes/translation-install.php"; if (is_wp_error(translations_api("core", array("version"=>get_bloginfo("version"))))) { WP_CLI::error("Offline Core fixture failed."); } WP_CLI::success("Offline Core update/translation fixture is valid.");'
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/aloha-content.php
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/inc/integrity.php
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/inc/area-media.php
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/inc/area-routing.php
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/inc/community-routing.php
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/inc/guide-categories.php
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/inc/guide-integrity.php
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/inc/guide-routing.php
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/inc/investor-routing.php
dc exec -T wordpress php -l /var/www/html/wp-content/plugins/aloha-content/inc/routing-overview.php
if [ "${CONTENT_AREA_TEST:-0}" = 1 ]; then
  dc exec -T wordpress sh -c 'for file in wp-content/themes/aloha-hello/inc/area*.php wp-content/themes/aloha-hello/patterns/area-*.php /var/www/aloha-tests/area-*.php /var/www/aloha-tests/seed-area-mauna-lani.php; do php -l "$file" || exit 1; done'
fi
wp eval-file /var/www/aloha-tests/aloha-content-check.php
# Exercise actual WP-CLI commands (not just PHP calls through eval-file).
area_id="$(wp post create --post_type=area --post_status=publish --post_title='CLI test Area' --porcelain)"
community_id="$(wp post create --post_type=community --post_status=draft --post_title='CLI test Community' --porcelain)"
if wp post update "$community_id" --post_status=publish; then echo 'FAIL: CLI publication without Area accepted' >&2; exit 1; fi
wp post meta update "$community_id" aloha_area_id "$area_id"
wp post update "$community_id" --post_status=publish
if wp post meta update "$community_id" aloha_area_id 999999999; then echo 'FAIL: invalid CLI relation accepted' >&2; exit 1; fi
if wp post delete "$area_id" --force; then echo 'FAIL: referenced CLI Area deleted' >&2; exit 1; fi
wp plugin deactivate aloha-content
wp eval 'if (post_type_exists("area") || post_type_exists("community")) { WP_CLI::error("CPT still registered without plugin."); } WP_CLI::success("CPT absent in a fresh request with plugin disabled.");'
curl --fail --silent --show-error --output "$run_dir/plugin-disabled.html" http://127.0.0.1:8091/
wp plugin activate aloha-content
wp post meta get "$community_id" aloha_area_id | python3 -c 'import sys; assert int(sys.stdin.read()) == int(sys.argv[1])' "$area_id"
wp theme activate twentytwentyfive
wp eval 'if (!post_type_exists("area") || !post_type_exists("community")) { WP_CLI::error("CPT missing with another theme."); }'
wp theme activate aloha-hello
wp post delete "$community_id" --force
wp post delete "$area_id" --force
wp eval-file /var/www/aloha-tests/aloha-area-routing-check.php setup > "$run_dir/area-fixtures.json"
python3 - "$run_dir/area-fixtures.json" <<'PY'
import json,sys
print('PASS:',json.load(open(sys.argv[1]))['checks'],'Area server checks.')
PY
wp eval-file /var/www/aloha-tests/aloha-community-routing-check.php setup > "$run_dir/community-fixtures.json"
python3 - "$run_dir/community-fixtures.json" <<'PY'
import json,sys
print('PASS:',json.load(open(sys.argv[1]))['checks'],'Community server checks.')
PY
wp eval-file /var/www/aloha-tests/aloha-guide-check.php setup > "$run_dir/guide-fixtures.json"
python3 - "$run_dir/guide-fixtures.json" <<'PY'
import json,sys
print('PASS:',json.load(open(sys.argv[1]))['checks'],'Guide server checks.')
PY
wp aloha-content guide-categories check --user=aloha_test_admin
wp aloha-content guide-categories install --user=aloha_test_admin
python3 scripts/aloha-area-http-check.py "$run_dir/area-fixtures.json" default "$run_dir/community-fixtures.json"
python3 scripts/aloha-guide-http-check.py "$run_dir/guide-fixtures.json" default
wp eval-file /var/www/aloha-tests/aloha-community-routing-check.php move
python3 scripts/aloha-area-http-check.py "$run_dir/area-fixtures.json" moved "$run_dir/community-fixtures.json"
wp option update aloha_content_test_area_base island-test
python3 scripts/aloha-area-http-check.py "$run_dir/area-fixtures.json" configured "$run_dir/community-fixtures.json"
python3 scripts/aloha-guide-http-check.py "$run_dir/guide-fixtures.json" configured
wp option delete aloha_content_test_area_base
wp eval-file /var/www/aloha-tests/aloha-community-routing-check.php restore
python3 scripts/aloha-area-http-check.py "$run_dir/area-fixtures.json" restored "$run_dir/community-fixtures.json"
wp option update aloha_content_test_guide_slug travel
python3 scripts/aloha-guide-http-check.py "$run_dir/guide-fixtures.json" segment
wp option delete aloha_content_test_guide_slug
python3 scripts/aloha-guide-http-check.py "$run_dir/guide-fixtures.json" restored
wp eval-file /var/www/aloha-tests/aloha-investor-check.php setup > "$run_dir/investor-fixtures.json"
python3 - "$run_dir/investor-fixtures.json" <<'PY'
import json,sys
print('PASS:',json.load(open(sys.argv[1]))['checks'],'Investor server checks.')
PY
wp aloha-content investor-category check --user=aloha_test_admin
wp aloha-content investor-category install --user=aloha_test_admin
python3 scripts/aloha-investor-http-check.py "$run_dir/investor-fixtures.json"
wp eval-file /var/www/aloha-tests/aloha-investor-check.php remove
python3 scripts/aloha-investor-http-check.py "$run_dir/investor-fixtures.json" removed
wp eval-file /var/www/aloha-tests/aloha-investor-check.php restore
wp option update aloha_content_test_investor_base owners/research/guides
python3 scripts/aloha-investor-http-check.py "$run_dir/investor-fixtures.json" configured
wp option delete aloha_content_test_investor_base
python3 scripts/aloha-investor-http-check.py "$run_dir/investor-fixtures.json" restored
wp eval-file /var/www/aloha-tests/aloha-routing-overview-check.php > "$run_dir/routing-overview-server-report.json"
python3 - "$run_dir/routing-overview-server-report.json" <<'PY'
import json,sys
print('PASS:',len(json.load(open(sys.argv[1]))['checks']),'Routing Overview server checks.')
PY
if [ "${CONTENT_AREA_TEST:-0}" = 1 ]; then
  wp eval-file /var/www/aloha-tests/area-presentation-check.php setup > "$run_dir/area-presentation-fixtures.json"
  python3 - "$run_dir" <<'AREA_REPORT'
import json,sys
from pathlib import Path
p=Path(sys.argv[1]); data=json.loads((p/'area-presentation-fixtures.json').read_text())
(p/'area-presentation-server-report.json').write_text(json.dumps({'checks':data['checks']},indent=2)+'\n')
print('PASS:',len(data['checks']),'Area presentation server checks.')
AREA_REPORT
fi
if [ "${CONTENT_BROWSER_TEST:-0}" = 1 ]; then
  ALOHA_CONTENT_TEST_AUTH="$run_dir/auth.json" ALOHA_AREA_TEST_FIXTURES="$run_dir/area-fixtures.json" ALOHA_GUIDE_TEST_FIXTURES="$run_dir/guide-fixtures.json" ALOHA_INVESTOR_TEST_FIXTURES="$run_dir/investor-fixtures.json" EVIDENCE_DIR="$run_dir" node scripts/aloha-content-editor.cjs
  wp eval 'if (is_wp_error(wp_insert_user(array("user_login"=>"aloha_routing_reader", "user_pass"=>getenv("WP_ADMIN_PASSWORD"), "role"=>"subscriber")))) { WP_CLI::error("Test reader creation failed."); }'
  ALOHA_CONTENT_TEST_AUTH="$run_dir/auth.json" EVIDENCE_DIR="$run_dir" node scripts/aloha-routing-overview-browser.cjs
  wp eval 'require_once ABSPATH . "wp-admin/includes/user.php"; wp_delete_user(get_user_by("login", "aloha_routing_reader")->ID);'
  if [ "${CONTENT_AREA_TEST:-0}" = 1 ]; then
    ALOHA_CONTENT_TEST_AUTH="$run_dir/auth.json" ALOHA_AREA_PRESENTATION_FIXTURES="$run_dir/area-presentation-fixtures.json" EVIDENCE_DIR="$run_dir" node scripts/area-presentation-browser.cjs
  fi
fi
if [ "${CONTENT_HERO_TEST:-0}" = 1 ]; then
  wp eval-file /var/www/aloha-tests/area-hero-check.php setup > "$run_dir/area-hero-fixtures.json"
  python3 - "$run_dir" <<'HERO_REPORT'
import json,sys
from pathlib import Path
p=Path(sys.argv[1]);data=json.loads((p/'area-hero-fixtures.json').read_text())
(p/'area-hero-server-report.json').write_text(json.dumps({'checks':data['checks']},indent=2)+'\n')
print('PASS:',len(data['checks']),'Interactive Hero server checks.')
HERO_REPORT
  if [ "${CONTENT_BROWSER_TEST:-0}" = 1 ]; then
    ALOHA_CONTENT_TEST_AUTH="$run_dir/auth.json" ALOHA_AREA_HERO_FIXTURES="$run_dir/area-hero-fixtures.json" ALOHA_AREA_PRESENTATION_FIXTURES="$run_dir/area-presentation-fixtures.json" EVIDENCE_DIR="$run_dir" node scripts/area-hero-browser.cjs
  fi
  wp eval-file /var/www/aloha-tests/area-hero-check.php cleanup
fi
if [ "${CONTENT_GALLERY_TEST:-0}" = 1 ]; then
  dc exec -T wordpress sh -c 'for file in wp-content/themes/aloha-hello/inc/media-gallery.php wp-content/themes/aloha-hello/blocks/media-gallery/render.php /var/www/aloha-tests/*gallery*.php; do php -l "$file" || exit 1; done'
  wp eval-file /var/www/aloha-tests/media-gallery-check.php setup > "$run_dir/media-gallery-fixtures.json"
  python3 - "$run_dir" <<'GALLERY_REPORT'
import json,sys
from pathlib import Path
p=Path(sys.argv[1]);data=json.loads((p/'media-gallery-fixtures.json').read_text())
(p/'media-gallery-server-report.json').write_text(json.dumps({'checks':data['checks']},indent=2)+'\n')
print('PASS:',len(data['checks']),'Media Gallery server checks.')
GALLERY_REPORT
  if [ "${CONTENT_BROWSER_TEST:-0}" = 1 ]; then
    ALOHA_CONTENT_TEST_AUTH="$run_dir/auth.json" ALOHA_MEDIA_GALLERY_FIXTURES="$run_dir/media-gallery-fixtures.json" EVIDENCE_DIR="$run_dir" node scripts/media-gallery-browser.cjs
  fi
  wp eval-file /var/www/aloha-tests/media-gallery-check.php cleanup
fi
if [ "${CONTENT_AREA_TEST:-0}" = 1 ]; then
  wp eval-file /var/www/aloha-tests/area-presentation-check.php prepare-switch
  wp theme activate twentytwentyfive
  wp eval-file /var/www/aloha-tests/area-presentation-check.php verify-other-theme
  wp theme activate aloha-hello
  wp eval-file /var/www/aloha-tests/area-presentation-check.php verify-restored-theme
  wp eval-file /var/www/aloha-tests/area-presentation-check.php cleanup
fi
wp eval-file /var/www/aloha-tests/aloha-investor-check.php cleanup
wp eval-file /var/www/aloha-tests/aloha-guide-check.php cleanup
wp eval-file /var/www/aloha-tests/aloha-community-routing-check.php cleanup
wp eval-file /var/www/aloha-tests/aloha-area-routing-check.php cleanup
wp eval 'if (get_posts(array("post_type"=>array("area","community"),"post_status"=>array_keys(get_post_stati()),"numberposts"=>1))) { WP_CLI::error("Fixtures remain."); } WP_CLI::success("No Area/Community fixtures remain.");'
dc exec -T wordpress sh -c 'if [ -f wp-content/debug.log ]; then cat wp-content/debug.log; fi' > "$run_dir/wp-debug.log"
python3 - "$run_dir/wp-debug.log" <<'PY'
import re,sys
log=open(sys.argv[1]).read()
if re.search(r'PHP (Fatal error|Warning|Parse error|Notice|Deprecated)',log):
 print(log); sys.exit(1)
PY
evidence_dir="${CONTENT_EVIDENCE_DIR:-.local/evidence/m1-4-b3-c}"
mkdir -p "$evidence_dir"
for artifact in "$run_dir"/*.png "$run_dir"/*-report.json "$run_dir/wp-debug.log"; do
  if [ -f "$artifact" ]; then cp "$artifact" "$evidence_dir"/; fi
done
printf 'PASS: Aloha Content integration suite. Evidence: %s\n' "$run_dir"
