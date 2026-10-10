import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { randomBytes } from 'node:crypto'
if (!existsSync('.env')) {
  const password = randomBytes(24).toString('hex')
  writeFileSync(
    '.env',
    `DATABASE_URL=postgres://postgres:${password}@127.0.0.1:5432/exchange_dev\nPAYLOAD_SECRET=${randomBytes(32).toString('hex')}\nSITE_URL=http://localhost:3000\n`,
    { mode: 0o600 },
  )
}
const env = readFileSync('.env', 'utf8')
const connection = env.match(/^DATABASE_URL=(.+)$/m)?.[1]
if (!connection) throw new Error('Missing DATABASE_URL in .env')
const url = new URL(connection)
if (url.hostname !== '127.0.0.1' || url.pathname !== '/exchange_dev')
  throw new Error(
    'Local Docker setup requires the local exchange_dev database. See README for remote databases.',
  )
writeFileSync('.env.docker', `POSTGRES_PASSWORD=${decodeURIComponent(url.password)}\n`, {
  mode: 0o600,
})
console.log('Local configuration prepared; secrets remain in ignored files.')
