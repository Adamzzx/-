import type { CollectionConfig } from 'payload'
import { isSafeDestination, exchangeDefinitions } from '../lib/exchanges'
export const AffiliateOffers: CollectionConfig = {
  slug: 'affiliate-offers',
  labels: { singular: '推荐入口', plural: '推荐链接管理' },
  admin: {
    useAsTitle: 'exchangeSlug',
    description: '仅支持已批准的交易所官方域名；每家交易所一个当前入口。',
  },
  access: {
    read: ({ req }) => !!req.user,
    create: ({ req }) => !!req.user,
    update: ({ req }) => !!req.user,
    delete: ({ req }) => !!req.user,
  },
  hooks: {
    beforeValidate: [
      ({ data }) => {
        if (data?.destination && !isSafeDestination(data.exchangeSlug, data.destination))
          throw new Error('推荐链接必须是对应交易所允许的 HTTPS 官方域名。')
        if (data?.startsAt && data?.endsAt && Date.parse(data.startsAt) >= Date.parse(data.endsAt))
          throw new Error('结束时间必须晚于开始时间。')
        return data
      },
    ],
  },
  fields: [
    {
      name: 'exchangeSlug',
      label: '交易所',
      type: 'select',
      required: true,
      unique: true,
      options: exchangeDefinitions.map((e) => ({ label: e.name, value: e.slug })),
    },
    { name: 'destination', label: '你的推荐链接', type: 'text', required: true },
    { name: 'enabled', label: '已核实并启用', type: 'checkbox', defaultValue: false },
    { name: 'terms', label: '优惠条款与资格限制', type: 'textarea', localized: true },
    { name: 'startsAt', label: '开始时间', type: 'date' },
    { name: 'endsAt', label: '结束时间', type: 'date' },
  ],
}
