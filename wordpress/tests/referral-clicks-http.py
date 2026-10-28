"""Focused HTTP idempotency/security checks against the disposable click-test WP.

Never follows a /go/ redirect, sends an external request, resets accumulated data,
uses production WordPress, or deletes another test's event receipts.
Run from the repository root after installing the current package locally.
"""
import concurrent.futures
import hashlib
import http.cookiejar
import json
import re
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = 'http://localhost:8088'
PROJECT = 'exchange-guide-click-test'
COMPOSE = ['docker', 'compose', '-f', 'wordpress/tests/compose.packages.yaml', '-p', PROJECT]
TOKEN_PATH = '/wp-json/cg/v1/referral-click-token'
EVENT_PATH = '/wp-json/cg/v1/referral-click'
NORMAL_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36'
SOURCE_PATH = '/click-http-article/'
PRIVATE_QUERY = 'cg-click-http-private-query'
EVENTS = []


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, newurl):
        return None


def wp(code):
    command = COMPOSE + ['run', '--rm', '-T', 'cli', 'wp', 'eval-file', '-']
    result = subprocess.run(command, input='<?php\n' + code, text=True, capture_output=True, check=True)
    return result.stdout.strip()


def database(sql):
    command = COMPOSE + ['exec', '-T', 'db', 'mariadb', '--user=wordpress',
                         '--password=local-test-only', '--batch', '--raw',
                         '--skip-column-names', 'wordpress', '-e', sql]
    return subprocess.run(command, text=True, capture_output=True, check=True).stdout


def request(path, method='GET', data=None, headers=None, cookies=None):
    url = path if path.startswith(BASE + '/') else BASE + path
    assert urllib.parse.urlsplit(url).netloc == 'localhost:8088', 'HTTP requests are local-only'
    defaults = {'User-Agent': NORMAL_UA, 'Origin': BASE, 'X-CG-Click': '1'}
    if headers:
        for key, value in headers.items():
            if value is None:
                defaults.pop(key, None)
            else:
                defaults[key] = value
    payload = None
    if data is not None:
        payload = json.dumps(data).encode('utf-8')
        defaults['Content-Type'] = 'application/json'
    handlers = [NoRedirect()]
    if cookies is not None:
        handlers.append(urllib.request.HTTPCookieProcessor(cookies))
    opener = urllib.request.build_opener(*handlers)
    req = urllib.request.Request(url, data=payload, headers=defaults, method=method)
    try:
        response = opener.open(req, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode('utf-8', errors='replace')
    try:
        decoded = json.loads(body) if body else None
    except json.JSONDecodeError:
        decoded = None
    return response.status, dict(response.headers.items()), decoded


def passed(label):
    print('PASS:', label, flush=True)


def event(token, default_target, **changes):
    identity = str(uuid.uuid4())
    EVENTS.append(identity)
    value = {'event': identity, 'issued': token['issued'], 'proof': token['proof'],
             'target': default_target, 'source': BASE + SOURCE_PATH + '?campaign=' + PRIVATE_QUERY + '#private-fragment'}
    value.update(changes)
    # Include every valid-shaped replacement in this run's explicit cleanup list.
    if isinstance(value['event'], str):
        try:
            if uuid.UUID(value['event']).version == 4:
                EVENTS.append(value['event'])
        except ValueError:
            pass
    return value


def assert_excluded(payload, label, headers=None, cookies=None, method='POST'):
    status, _, result = request(EVENT_PATH, method, payload if method == 'POST' else None,
                                headers=headers, cookies=cookies)
    assert status < 500, (label, status, result)
    assert not isinstance(result, dict) or result.get('counted') is not True, (label, result)
    assert_counts(label)
    passed(label)


def assert_counts(label):
    ids = ','.join(str(row['id']) for row in state['platforms'].values())
    rows = database(f"SELECT post_id,meta_key,meta_value FROM {state['prefix']}postmeta "
                    f"WHERE post_id IN ({ids}) AND meta_key IN ('_cg_clicks','_cg_confirmed_clicks') "
                    "ORDER BY post_id,meta_key,meta_id;")
    values = {}
    for line in rows.splitlines():
        post_id, key, value = line.split('\t', 2)
        values.setdefault((int(post_id), key), []).append(value)
    for slug, row in state['platforms'].items():
        assert values.get((row['id'], '_cg_clicks'), []) == row['meta']['_cg_clicks'], (label, slug, 'legacy counter changed')
        current = values.get((row['id'], '_cg_confirmed_clicks'), [])
        assert len(current) <= 1, (label, slug, 'duplicate counter rows')
        assert int(current[0] if current else 0) == expected[slug], (label, slug, current, expected[slug])


def login_admin():
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPCookieProcessor(jar))
    opener.open(BASE + '/wp-login.php', timeout=30).read()
    encoded = urllib.parse.urlencode({'log': 'smoke-admin', 'pwd': 'local-test-only',
                                      'testcookie': '1', 'redirect_to': BASE + '/wp-admin/'}).encode()
    try:
        opener.open(urllib.request.Request(BASE + '/wp-login.php', data=encoded,
                                          headers={'User-Agent': NORMAL_UA}), timeout=30).read()
    except urllib.error.HTTPError as error:
        assert error.code == 302, 'local administrator login failed'
        error.read()
    logged_in = next((cookie.value for cookie in jar if cookie.name.startswith('wordpress_logged_in_')), None)
    assert logged_in, 'administrator login cookie missing'
    parts = urllib.parse.unquote(logged_in).split('|')
    assert len(parts) == 4 and parts[0] == 'smoke-admin'
    return jar, parts[2]


state = None
admin_token = None
expected = {}
try:
    state = json.loads(wp("""
if(home_url()!=='http://localhost:8088'){throw new RuntimeException('Disposable local click-test WordPress only');}
if(!function_exists('cg_clicks_token_request')){throw new RuntimeException('Install the click-idempotency package before running this test');}
global $wpdb;$rows=array();
foreach(array('binance','okx','bitget','bybit') as $slug){
    $post=cg_exchange($slug);if(!$post||!cg_active_offer($post)){throw new RuntimeException('Initialize active synthetic local referral fixtures first: '.$slug);}
    $meta=array();foreach(array('_cg_clicks','_cg_confirmed_clicks','_cg_confirmed_clicks_first','_cg_enabled') as $key){$meta[$key]=get_post_meta($post->ID,$key);}
    if($meta['_cg_clicks']!==array('45')){throw new RuntimeException('Original local legacy counter must remain 45: '.$slug);}
    $rows[$slug]=array('id'=>$post->ID,'url'=>cg_go_url($slug),'meta'=>$meta);
}
echo wp_json_encode(array('home'=>home_url(),'prefix'=>$wpdb->prefix,'platforms'=>$rows));
"""))
    assert state['home'] == BASE and re.fullmatch(r'[A-Za-z0-9_]+', state['prefix'])
    for slug, row in state['platforms'].items():
        expected[slug] = int(row['meta']['_cg_confirmed_clicks'][0] if row['meta']['_cg_confirmed_clicks'] else 0)
    assert_counts('initial counters')
    status, headers, token = request(TOKEN_PATH)
    assert status == 200 and isinstance(token, dict) and isinstance(token.get('issued'), int)
    assert isinstance(token.get('proof'), str) and len(token['proof']) >= 32
    assert abs(token['issued'] - time.time()) < 60, 'proof is based on current server time'
    assert 'no-store' in headers.get('Cache-Control', '') or 'no-cache' in headers.get('Cache-Control', '')
    assert_counts('issuing a proof does not count a click')
    passed('same-origin token endpoint issues a fresh, noncached proof without counting')

    target = state['platforms']['binance']['url']
    first = event(token, target)
    status, _, result = request(EVENT_PATH, 'POST', first)
    assert status == 200 and result.get('counted') is True, (status, result)
    expected['binance'] += 1
    for _ in range(3):
        status, _, result = request(EVENT_PATH, 'POST', first)
        assert status == 200 and result.get('duplicate') is True, (status, result)
    assert_counts('same event replay')
    passed('one accepted event and repeated HTTP replays increment exactly once')

    repeated = event(token, state['platforms']['okx']['url'])
    with concurrent.futures.ThreadPoolExecutor(max_workers=10) as pool:
        responses = list(pool.map(lambda _: request(EVENT_PATH, 'POST', repeated), range(10)))
    assert all(status == 200 and isinstance(result, dict) for status, _, result in responses), responses
    assert sum(result.get('duplicate') is not True for _, _, result in responses) == 1, responses
    expected['okx'] += 1
    assert_counts('ten simultaneous requests replaying the same event')
    passed('ten concurrent POST requests with the same UUID produce one accepted event')

    independent = [event(token, target), event(token, target)]
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        responses = list(pool.map(lambda value: request(EVENT_PATH, 'POST', value), independent))
    assert all(status == 200 and result.get('counted') is True and not result.get('duplicate')
               for status, _, result in responses), responses
    expected['binance'] += 2
    assert_counts('independent rapid clicks')
    passed('two independent rapid clicks by the same client count twice without coarse visitor filtering')

    invalid = [event(token, target, event='not-a-uuid'), event(token, target, event=''),
               event(token, target, event='00000000-0000-1000-8000-000000000000'),
               event(token, target, proof=('0' if token['proof'][0] != '0' else '1') + token['proof'][1:])]
    for index, payload in enumerate(invalid):
        assert_excluded(payload, 'malformed UUID or proof rejected #' + str(index))
    # The adapter below uses the server's proof signer solely to construct a genuinely
    # expired signed proof. It must not call the event recorder directly.
    expired = json.loads(wp("$issued=time()-601;echo wp_json_encode(array('issued'=>$issued,'proof'=>cg_clicks_token_proof($issued)));"))
    assert_excluded(event(expired, target), 'expired but correctly signed proof rejected')

    assert_excluded(event(token, target), 'foreign Origin rejected', headers={'Origin': 'https://foreign.example'})
    assert_excluded(event(token, target), 'missing Origin and Referer rejected', headers={'Origin': None, 'Referer': None})
    assert_excluded(event(token, target), 'missing activation header rejected', headers={'X-CG-Click': None})
    assert_excluded(event(token, target), 'crawler user agent rejected', headers={'User-Agent': 'Googlebot/2.1 (+http://www.google.com/bot.html)'})
    for name in ['Purpose', 'Sec-Purpose']:
        assert_excluded(event(token, target), name + ' prefetch rejected', headers={name: 'prefetch'})
    assert_excluded(event(token, target, target='https://foreign.example/go/binance/'), 'foreign click target rejected')
    assert_excluded(event(token, target, source='https://foreign.example/forged-source/'), 'foreign source rejected')
    for method in ['GET', 'HEAD']:
        assert_excluded(first, method + ' cannot record an event', method=method)
        status, _, _ = request(target, method)
        assert status == 302, (method, status)
        assert_counts(method + ' go redirect does not record')
    passed('GET/HEAD redirect requests preserve all counters and are never followed externally')

    # An unsuccessful SQL transaction must not consume the event's idempotency key.
    rollback = event(token, state['platforms']['bybit']['url'])
    rollback_id = state['platforms']['bybit']['id']
    rollback_name = 'cg_click_event_' + hashlib.sha256(rollback['event'].encode()).hexdigest()
    wp(f"update_post_meta({rollback_id},'_cg_confirmed_clicks','not-a-number');")
    status, _, result = request(EVENT_PATH, 'POST', rollback)
    assert status == 503 and result.get('code') == 'cg_click_storage', (status, result)
    assert database(f"SELECT COUNT(*) FROM {state['prefix']}options WHERE option_name='{rollback_name}';").strip() == '0', 'failed counter update must roll back the event claim'
    wp(f"update_post_meta({rollback_id},'_cg_confirmed_clicks','{expected['bybit']}');")
    status, _, result = request(EVENT_PATH, 'POST', rollback)
    assert status == 200 and result.get('counted') is True and not result.get('duplicate'), (status, result)
    expected['bybit'] += 1
    status, _, result = request(EVENT_PATH, 'POST', rollback)
    assert status == 200 and result.get('duplicate') is True, (status, result)
    assert_counts('failed transaction then retry')
    passed('counter failure rolls back its event claim; replaying the same UUID after repair counts exactly once')

    # This intentionally invokes the recorder in one local PHP process to verify
    # invalidation of an already warmed post-meta cache. It is not a browser click.
    cache_event = event(token, state['platforms']['bitget']['url'])
    cache_id = state['platforms']['bitget']['id']
    cache_result = json.loads(wp(f"$post=cg_exchange('bitget');$before=(int)get_post_meta({cache_id},'_cg_confirmed_clicks',true);$legacy=get_post_meta({cache_id},'_cg_clicks',true);$result=cg_clicks_record($post,'{cache_event['event']}','{SOURCE_PATH}','direct');echo wp_json_encode(array('before'=>$before,'after'=>(int)get_post_meta({cache_id},'_cg_confirmed_clicks',true),'legacy_before'=>$legacy,'legacy_after'=>get_post_meta({cache_id},'_cg_clicks',true),'result'=>is_wp_error($result)?$result->get_error_code():$result->get_data()));"))
    assert cache_result['before'] == expected['bitget'] and cache_result['after'] == expected['bitget'] + 1
    assert cache_result['legacy_before'] == cache_result['legacy_after'] == '45'
    assert cache_result['result'].get('counted') is True
    expected['bitget'] += 1
    assert_counts('local backend cache invalidation unit')
    passed('local backend UNIT (synthetic, not a visitor click): warmed metadata reads the increment immediately in the same PHP process')

    jar, admin_token = login_admin()
    assert_excluded(event(token, target), 'administrator cookie excluded without an X-WP-Nonce', cookies=jar)
    bitget = state['platforms']['bitget']
    wp(f"update_post_meta({bitget['id']},'_cg_enabled','');")
    assert_excluded(event(token, bitget['url']), 'inactive referral offer does not count')

    names = ['cg_click_event_' + hashlib.sha256(identity.encode()).hexdigest() for identity in set(EVENTS)]
    quoted_names = ','.join("'" + name + "'" for name in names)
    receipts = database(f"SELECT option_name,option_value FROM {state['prefix']}options WHERE option_name IN ({quoted_names});")
    assert PRIVATE_QUERY not in receipts and 'private-fragment' not in receipts and 'campaign=' not in receipts
    assert SOURCE_PATH in receipts, 'accepted receipts retain a useful source path'
    passed('event receipts keep a source path without query strings or fragments')

    # Exact accepted receipt schema/cleanup function names are deliberately adapted
    # to the implementation here, without ever sweeping unrelated options in finally.
    receipt_name = 'cg_click_event_' + hashlib.sha256(first['event'].encode()).hexdigest()
    fresh_name = 'cg_click_event_' + hashlib.sha256(repeated['event'].encode()).hexdigest()
    cleanup_result = json.loads(wp(f"$name='{receipt_name}';$receipt=get_option($name);$keys=array_keys($receipt);$source=$receipt['source_path'];$receipt['timestamp']=time()-8*DAY_IN_SECONDS;update_option($name,$receipt,false);cg_clicks_cleanup_events();echo wp_json_encode(array('gone'=>get_option($name,false)===false,'fresh'=>get_option('{fresh_name}',false)!==false,'keys'=>$keys,'source'=>$source));"))
    assert cleanup_result['gone'] is True and cleanup_result['fresh'] is True
    assert set(cleanup_result['keys']) == {'timestamp', 'post_id', 'source_path', 'entry_type'}
    assert cleanup_result['source'] == SOURCE_PATH
    assert_counts('seven-day receipt cleanup')
    passed('seven-day event receipt cleanup does not reset accumulated click counters')
finally:
    if state:
        encoded = json.dumps(state['platforms'], ensure_ascii=True)
        events = json.dumps(sorted(set(EVENTS)))
        user_session = json.dumps(admin_token or '')
        wp("$rows=json_decode(" + json.dumps(encoded) + ",true);foreach($rows as $row){foreach($row['meta'] as $key=>$values){if($key==='_cg_clicks'){continue;}delete_post_meta($row['id'],$key);foreach($values as $value){add_post_meta($row['id'],$key,wp_slash($value));}}}"
           "$events=json_decode(" + json.dumps(events) + ",true);foreach($events as $event){delete_option('cg_click_event_'.hash('sha256',$event));}"
           "$session=json_decode(" + json.dumps(user_session) + ");if($session!==''){$user=get_user_by('login','smoke-admin');if($user){WP_Session_Tokens::get_instance($user->ID)->destroy($session);}}")
        passed('restored only this run\'s new-counter metadata, activation flag, event receipts and administrator session; original 45 remained read-only')
