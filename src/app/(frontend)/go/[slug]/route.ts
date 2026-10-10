import { cms } from '@/lib/cms'
import { exchangeDefinitions, isOfferActive, isSafeDestination } from '@/lib/exchanges'
export const dynamic = 'force-dynamic'
export async function GET(request: Request, { params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params
  const headers = { 'X-Robots-Tag': 'noindex, nofollow', 'Cache-Control': 'no-store' }
  if (!exchangeDefinitions.some((e) => e.slug === slug))
    return new Response('未知交易所', { status: 404, headers })
  const payload = await cms()
  const offer = (
    await payload.find({
      collection: 'affiliate-offers',
      where: { exchangeSlug: { equals: slug } },
      limit: 1,
      overrideAccess: true,
    })
  ).docs[0]
  if (!offer || !isOfferActive(offer) || !isSafeDestination(slug, offer.destination))
    return new Response(null, {
      status: 302,
      headers: {
        ...headers,
        Location: new URL(`/zh-cn/register/${slug}/`, request.url).toString(),
      },
    })
  try {
    await payload.create({
      collection: 'affiliate-clicks',
      data: { exchangeSlug: slug, offer: offer.id },
      overrideAccess: true,
    })
  } catch {
    console.warn('Affiliate click recording failed')
  }
  return new Response(null, { status: 302, headers: { ...headers, Location: offer.destination } })
}
