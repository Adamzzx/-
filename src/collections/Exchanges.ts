import type { CollectionConfig } from 'payload'
import { exchangeDefinitions } from '../lib/exchanges'
export const Exchanges: CollectionConfig = {
  slug: 'exchanges',
  labels: { singular: '交易所', plural: '交易所资料' },
  admin: { useAsTitle: 'name', defaultColumns: ['name', 'slug', 'updatedAt'] },
  access: {
    read: () => true,
    create: ({ req }) => !!req.user,
    update: ({ req }) => !!req.user,
    delete: ({ req }) => !!req.user,
  },
  fields: [
    { name: 'name', label: '名称', type: 'text', required: true },
    {
      name: 'slug',
      label: '网址标识',
      type: 'select',
      required: true,
      unique: true,
      options: exchangeDefinitions.map((e) => ({ label: e.name, value: e.slug })),
    },
    { name: 'summary', label: '简介', type: 'textarea', localized: true },
    {
      name: 'verificationStatus',
      label: '资料状态',
      type: 'select',
      defaultValue: 'pending',
      required: true,
      options: [
        { label: '待核验', value: 'pending' },
        { label: '已核验', value: 'verified' },
      ],
    },
    { name: 'verifiedAt', label: '最近核验日期', type: 'date' },
    { name: 'sourceURL', label: '官方资料来源', type: 'text' },
    { name: 'regionNotes', label: '地区与资格说明', type: 'textarea', localized: true },
    { name: 'feeNotes', label: '费用与适用条件', type: 'textarea', localized: true },
  ],
}
