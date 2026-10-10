import 'dotenv/config'
import { getPayload } from 'payload'
import config from '../src/payload.config'
import { exchangeDefinitions } from '../src/lib/exchanges'
const payload = await getPayload({ config })
for (const e of exchangeDefinitions) {
  const existing = await payload.find({
    collection: 'exchanges',
    where: { slug: { equals: e.slug } },
    limit: 1,
  })
  if (!existing.docs.length)
    await payload.create({
      collection: 'exchanges',
      locale: 'zh-cn',
      data: { name: e.name, slug: e.slug, verificationStatus: 'pending' },
    })
}
console.log('Six exchange identities prepared; existing content preserved.')
await payload.destroy()

process.exit(0)
