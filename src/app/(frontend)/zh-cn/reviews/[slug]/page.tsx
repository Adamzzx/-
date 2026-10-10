import { cms } from '@/lib/cms'
import { notFound } from 'next/navigation'
import { RichText } from '@payloadcms/richtext-lexical/react'
export const dynamic = 'force-dynamic'
export default async function Page({ params }: { params: Promise<{ slug: string }> }) {
  const article = (
    await (
      await cms()
    ).find({
      collection: 'articles',
      locale: 'zh-cn',
      where: {
        and: [
          { slug: { equals: (await params).slug } },
          { kind: { equals: 'review' } },
          { _status: { equals: 'published' } },
        ],
      },
      overrideAccess: false,
      limit: 1,
    })
  ).docs[0]
  if (!article) notFound()
  return (
    <article className="page narrow">
      <p className="eyebrow">REVIEW</p>
      <h1>{article.title}</h1>
      {article.sponsored && <p className="notice">赞助内容：本文涉及商业合作。</p>}
      <p className="lead">{article.description}</p>
      {article.reviewedAt && (
        <p>核验日期：{new Date(article.reviewedAt).toLocaleDateString('zh-CN')}</p>
      )}
      <RichText data={article.body} />
      <h2>资料来源</h2>
      {article.sources?.map((s, i) => (
        <p key={i}>
          {s.note}{' '}
          {/^https:\/\//.test(s.url) && (
            <a href={s.url} rel="noopener noreferrer">
              查看来源
            </a>
          )}
        </p>
      ))}
    </article>
  )
}
