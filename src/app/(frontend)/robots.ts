import type { MetadataRoute } from 'next'
export default function robots(): MetadataRoute.Robots {
  return {
    rules: { userAgent: '*', disallow: ['/admin', '/api/', '/go/'] },
    sitemap: `${process.env.SITE_URL || 'http://localhost:3000'}/sitemap.xml`,
  }
}
