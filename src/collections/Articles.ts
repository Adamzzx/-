import type { CollectionConfig } from 'payload'
export const Articles: CollectionConfig = {
  slug: 'articles',
  labels: { singular: '文章', plural: '内容与评测' },
  admin: { useAsTitle: 'title' },
  versions: { drafts: true },
  access: {
    read: ({ req }) => (req.user ? true : { _status: { equals: 'published' } }),
    create: ({ req }) => !!req.user,
    update: ({ req }) => !!req.user,
    delete: ({ req }) => !!req.user,
  },
  fields: [
    { name: 'title', label: '标题', type: 'text', required: true, localized: true },
    {
      name: 'slug',
      label: '网址标识',
      type: 'text',
      required: true,
      unique: true,
      validate: (v: string | null | undefined) =>
        (!!v && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(v)) || '请使用小写英文、数字和连字符',
    },
    {
      name: 'kind',
      label: '内容类型',
      type: 'select',
      required: true,
      options: [
        { label: '评测', value: 'review' },
        { label: '操作指南', value: 'guide' },
        { label: '比较分析', value: 'comparison' },
        { label: '推荐榜单', value: 'ranking' },
      ],
    },
    {
      name: 'exchanges',
      label: '关联交易所',
      type: 'relationship',
      relationTo: 'exchanges',
      hasMany: true,
    },
    { name: 'description', label: '摘要与搜索描述', type: 'textarea', localized: true },
    { name: 'body', label: '正文', type: 'richText', localized: true, required: true },
    { name: 'sponsored', label: '赞助内容（前台明确标识）', type: 'checkbox', defaultValue: false },
    {
      name: 'sources',
      label: '资料来源',
      type: 'array',
      fields: [
        { name: 'url', type: 'text', required: true },
        { name: 'note', label: '支持的事实', type: 'text' },
      ],
    },
    { name: 'reviewedAt', label: '核验日期', type: 'date' },
  ],
}
