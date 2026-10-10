export const exchangeDefinitions = [
  {
    slug: 'binance',
    name: 'Binance',
    domains: ['binance.com', 'www.binance.com', 'accounts.binance.com'],
  },
  { slug: 'bybit', name: 'Bybit', domains: ['bybit.com', 'www.bybit.com'] },
  { slug: 'okx', name: 'OKX', domains: ['okx.com', 'www.okx.com'] },
  { slug: 'bitget', name: 'Bitget', domains: ['bitget.com', 'www.bitget.com'] },
  { slug: 'mexc', name: 'MEXC', domains: ['mexc.com', 'www.mexc.com'] },
  { slug: 'gate', name: 'Gate', domains: ['gate.com', 'www.gate.com', 'gate.io', 'www.gate.io'] },
] as const
export function isSafeDestination(slug: string, value: string): boolean {
  try {
    const url = new URL(value)
    const exchange = exchangeDefinitions.find((e) => e.slug === slug)
    return (
      !!exchange &&
      url.protocol === 'https:' &&
      !url.username &&
      !url.password &&
      (!url.port || url.port === '443') &&
      (exchange.domains as readonly string[]).includes(url.hostname)
    )
  } catch {
    return false
  }
}
export function isOfferActive(
  offer: { enabled?: boolean | null; startsAt?: string | null; endsAt?: string | null },
  now = Date.now(),
) {
  if (!offer.enabled) return false
  if (
    offer.startsAt &&
    (!Number.isFinite(Date.parse(offer.startsAt)) || Date.parse(offer.startsAt) > now)
  )
    return false
  if (
    offer.endsAt &&
    (!Number.isFinite(Date.parse(offer.endsAt)) || Date.parse(offer.endsAt) <= now)
  )
    return false
  return true
}
