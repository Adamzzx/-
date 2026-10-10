import type { MetadataRoute } from 'next'
import { exchanges } from '@/lib/cms'
export const dynamic = 'force-dynamic'
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const base = process.env.SITE_URL || 'http://localhost:3000'
  return [
    { url: `${base}/zh-cn/` },
    { url: `${base}/zh-cn/exchanges/` },
    ...(await exchanges())
      .filter((e) => e.verificationStatus === 'verified')
      .map((e) => ({
        url: `${base}/zh-cn/exchanges/${e.slug}/`,
        lastModified: new Date(e.updatedAt),
      })),
  ]
}
