import { test } from 'node:test'
import assert from 'node:assert/strict'
import { isSafeDestination, isOfferActive } from '../src/lib/exchanges'
test('allows official HTTPS referral links and rejects redirect abuse', () => {
  assert.equal(isSafeDestination('binance', 'https://www.binance.com/en/register?ref=demo'), true)
  for (const url of [
    'http://www.binance.com',
    'https://binance.com.evil.test',
    'https://evil.test',
    'https://user:pass@www.binance.com',
    'https://www.binance.com:8443',
    'javascript:alert(1)',
    '/relative',
  ])
    assert.equal(isSafeDestination('binance', url), false)
  assert.equal(isSafeDestination('okx', 'https://www.binance.com'), false)
  assert.equal(isSafeDestination('unknown', 'https://www.binance.com'), false)
})
test('disabled, expired, future or malformed offers never activate', () => {
  const now = Date.parse('2026-01-01T00:00:00Z')
  assert.equal(isOfferActive({ enabled: true }, now), true)
  assert.equal(isOfferActive({ enabled: false }, now), false)
  assert.equal(isOfferActive({ enabled: true, startsAt: '2027-01-01' }, now), false)
  assert.equal(isOfferActive({ enabled: true, endsAt: '2025-12-31' }, now), false)
  assert.equal(isOfferActive({ enabled: true, endsAt: 'invalid' }, now), false)
  assert.equal(isOfferActive({ enabled: true, endsAt: '2026-01-01T00:00:00Z' }, now), false)
})
