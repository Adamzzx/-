import type { CollectionConfig } from 'payload'
export const Users: CollectionConfig = {
  slug: 'users',
  labels: { singular: '管理员', plural: '管理员' },
  auth: true,
  admin: { useAsTitle: 'email' },
  access: {
    read: ({ req }) => !!req.user,
    create: async ({ req }) =>
      !!req.user ||
      (await req.payload.count({ collection: 'users', overrideAccess: true })).totalDocs === 0,
    update: ({ req }) => !!req.user,
    delete: ({ req }) => !!req.user,
  },
  fields: [{ name: 'name', label: '姓名', type: 'text' }],
}
