import 'dotenv/config'
import assert from 'node:assert/strict'
import { randomBytes } from 'node:crypto'
import { spawn, spawnSync } from 'node:child_process'
const url = new URL(process.env.DATABASE_URL)
assert.equal(url.hostname, '127.0.0.1', 'Smoke test requires the local Docker database')
url.pathname = '/exchange_validation'
const env = { ...process.env, DATABASE_URL: url.toString(), NODE_ENV: 'production' }
function command(cmd, args) {
  const p = spawnSync(cmd, args, { env, stdio: 'inherit' })
  assert.equal(p.status, 0, `${cmd} failed`)
}
command('docker', [
  'compose',
  'exec',
  '-T',
  'db',
  'dropdb',
  '-U',
  'postgres',
  '--if-exists',
  '--force',
  'exchange_validation',
])
command('docker', [
  'compose',
  'exec',
  '-T',
  'db',
  'createdb',
  '-U',
  'postgres',
  'exchange_validation',
])
command('npm', ['run', 'db:migrate'])
command('npm', ['run', 'seed'])
const server = spawn(
  'node',
  ['node_modules/next/dist/bin/next', 'start', '--hostname', '127.0.0.1', '--port', '3001'],
  { env, stdio: ['ignore', 'pipe', 'pipe'] },
)
let serverLog = ''
server.stdout.on('data', (d) => (serverLog += d))
server.stderr.on('data', (d) => (serverLog += d))
const base = 'http://127.0.0.1:3001'
let token = ''
async function req(path, options = {}) {
  return fetch(base + path, { redirect: path.startsWith('/go/') ? 'manual' : 'follow', ...options })
}
async function api(path, method, data, auth = true) {
  return req('/api/' + path + (path.includes('?') ? '&' : '?') + 'locale=zh-cn', {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(auth && token ? { Authorization: `JWT ${token}` } : {}),
    },
    ...(data ? { body: JSON.stringify(data) } : {}),
  })
}
try {
  let ready = false
  for (let i = 0; i < 90; i++) {
    try {
      if ((await req('/zh-cn/')).status === 200) {
        ready = true
        break
      }
    } catch {}
    await new Promise((r) => setTimeout(r, 500))
  }
  assert.equal(ready, true, 'Server did not start')
  assert.match(await (await req('/zh-cn/')).text(), /Binance/)
  assert.match(await (await req('/zh-cn/compare/?a=binance&b=okx')).text(), /OKX/)
  assert.equal((await req('/admin')).status, 200)
  assert.equal((await req('/go/unknown/')).status, 404)
  assert.equal((await req('/go/binance/')).status, 302)
  assert.equal((await api('affiliate-offers', 'GET', null, false)).status, 403)
  const email = 'smoke@example.invalid',
    password = randomBytes(24).toString('hex')
  const first = await api('users/first-register', 'POST', { email, password }, false)
  assert.equal(first.ok, true, 'First admin bootstrap failed')
  const login = await api('users/login', 'POST', { email, password }, false)
  assert.equal(login.ok, true)
  token = (await login.json()).token
  assert.ok(token)
  assert.equal(
    (await api('users', 'POST', { email: 'intruder@example.invalid', password }, false)).status,
    403,
  )
  const ex = (await (await api('exchanges?where[slug][equals]=binance', 'GET')).json()).docs[0]
  const edit = await api(`exchanges/${ex.id}`, 'PATCH', {
    feeNotes: '测试费用条件，不用于真实发布。',
  })
  assert.equal(edit.ok, true)
  assert.equal((await edit.json()).doc.feeNotes, '测试费用条件，不用于真实发布。')
  assert.match(await (await req('/zh-cn/exchanges/binance/')).text(), /测试费用条件/)
  const bad = await api('affiliate-offers', 'POST', {
    exchangeSlug: 'binance',
    destination: 'https://evil.invalid/',
    enabled: true,
  })
  assert.equal(bad.ok, false)
  const good = await api('affiliate-offers', 'POST', {
    exchangeSlug: 'binance',
    destination: 'https://www.binance.com/en/register?ref=smoke',
    enabled: true,
    terms: '测试条款',
  })
  assert.equal(good.ok, true)
  const offer = (await good.json()).doc
  const go = await req('/go/binance/')
  assert.equal(go.status, 302)
  assert.equal(go.headers.get('location'), 'https://www.binance.com/en/register?ref=smoke')
  assert.equal(go.headers.get('cache-control'), 'no-store')
  const clicks = await (await api('affiliate-clicks', 'GET')).json()
  assert.equal(clicks.totalDocs, 1)
  assert.equal(
    (await api('affiliate-clicks', 'POST', { exchangeSlug: 'binance', offer: offer.id }, false))
      .status,
    403,
  )
  const body = {
    root: {
      type: 'root',
      version: 1,
      format: '',
      indent: 0,
      direction: 'ltr',
      children: [
        {
          type: 'paragraph',
          version: 1,
          format: '',
          indent: 0,
          direction: 'ltr',
          children: [
            {
              type: 'text',
              version: 1,
              text: '正文保存与发布验证。',
              format: 0,
              detail: 0,
              mode: 'normal',
              style: '',
            },
          ],
        },
      ],
    },
  }
  const draft = await api('articles', 'POST', {
    title: 'Smoke review',
    slug: 'smoke-review',
    kind: 'review',
    exchanges: [ex.id],
    body,
    _status: 'draft',
  })
  assert.equal(draft.ok, true)
  const article = (await draft.json()).doc
  assert.equal((await req('/zh-cn/reviews/smoke-review/')).status, 404)
  const pub = await api(`articles/${article.id}`, 'PATCH', { _status: 'published' })
  assert.equal(pub.ok, true)
  assert.match(await (await req('/zh-cn/reviews/smoke-review/')).text(), /正文保存与发布验证/)
  assert.equal((await api(`affiliate-offers/${offer.id}`, 'PATCH', { enabled: false })).ok, true)
  assert.match((await req('/go/binance/')).headers.get('location'), /\/zh-cn\/register\/binance/)
  console.log(
    'PASS: fresh database migrations, six exchanges, CMS bootstrap/login/edit/publish, draft privacy, anonymous access controls, safe redirects and click recording.',
  )
} catch (e) {
  console.error(serverLog)
  throw e
} finally {
  server.kill('SIGTERM')
  await new Promise((r) => server.once('exit', r))
  command('docker', [
    'compose',
    'exec',
    '-T',
    'db',
    'dropdb',
    '-U',
    'postgres',
    '--if-exists',
    '--force',
    'exchange_validation',
  ])
}
