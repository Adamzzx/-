import type { CollectionConfig } from 'payload'
export const Clicks: CollectionConfig = {
  slug: 'affiliate-clicks',
  labels: { singular: '推荐点击', plural: '推荐点击记录' },
  admin: {
    useAsTitle: 'exchangeSlug',
    description: '仅记录跳转，不代表成功注册。无 IP、邮箱或钱包地址。',
  },
  access: {
    read: ({ req }) => !!req.user,
    create: () => false,
    update: () => false,
    delete: ({ req }) => !!req.user,
  },
  fields: [
    { name: 'exchangeSlug', type: 'text', required: true },
    { name: 'offer', type: 'relationship', relationTo: 'affiliate-offers', required: true },
  ],
}
