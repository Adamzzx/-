import { getPayload } from 'payload'
import config from '@/payload.config'
export async function cms() {
  return getPayload({ config })
}
export async function exchanges() {
  return (
    await (
      await cms()
    ).find({
      collection: 'exchanges',
      locale: 'zh-cn',
      limit: 20,
      sort: 'name',
      overrideAccess: false,
    })
  ).docs
}
export async function exchange(slug: string) {
  return (
    await (
      await cms()
    ).find({
      collection: 'exchanges',
      locale: 'zh-cn',
      where: { slug: { equals: slug } },
      limit: 1,
      overrideAccess: false,
    })
  ).docs[0]
}
