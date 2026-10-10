import { postgresAdapter } from '@payloadcms/db-postgres'
import { lexicalEditor } from '@payloadcms/richtext-lexical'
import path from 'path'
import { buildConfig } from 'payload'
import { fileURLToPath } from 'url'
import sharp from 'sharp'

import { Users } from './collections/Users'
import { Media } from './collections/Media'
import { Exchanges } from './collections/Exchanges'
import { Articles } from './collections/Articles'
import { AffiliateOffers } from './collections/AffiliateOffers'
import { Clicks } from './collections/Clicks'
import { zh } from '@payloadcms/translations/languages/zh'
import { en } from '@payloadcms/translations/languages/en'

const filename = fileURLToPath(import.meta.url)
const dirname = path.dirname(filename)

export default buildConfig({
  admin: {
    user: Users.slug,
    importMap: {
      baseDir: path.resolve(dirname),
    },
  },
  collections: [Users, Media, Exchanges, Articles, AffiliateOffers, Clicks],
  i18n: { supportedLanguages: { zh, en }, fallbackLanguage: 'zh' },
  localization: { locales: ['zh-cn', 'zh-tw', 'en'], defaultLocale: 'zh-cn', fallback: false },
  graphQL: { disable: true },
  editor: lexicalEditor(),
  secret: process.env.PAYLOAD_SECRET || '',
  typescript: {
    outputFile: path.resolve(dirname, 'payload-types.ts'),
  },
  db: postgresAdapter({
    push: process.env.NODE_ENV !== 'production',
    pool: {
      connectionString: process.env.DATABASE_URL || '',
    },
  }),
  sharp,
  plugins: [],
})
