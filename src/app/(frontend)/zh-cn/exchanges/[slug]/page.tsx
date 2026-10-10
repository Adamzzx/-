import Link from 'next/link'
import { notFound } from 'next/navigation'
import { exchange, cms } from '@/lib/cms'
export const dynamic = 'force-dynamic'
export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const e = await exchange((await params).slug)
  return {
    title: e?.name || '交易所',
    alternates: { canonical: `/zh-cn/exchanges/${(await params).slug}/` },
  }
}
export default async function Page({ params }: { params: Promise<{ slug: string }> }) {
  const e = await exchange((await params).slug)
  if (!e) notFound()
  const reviews = await (
    await cms()
  ).find({
    collection: 'articles',
    locale: 'zh-cn',
    where: {
      and: [
        { kind: { equals: 'review' } },
        { exchanges: { contains: e.id } },
        { _status: { equals: 'published' } },
      ],
    },
    overrideAccess: false,
    limit: 10,
  })
  return (
    <article className="page narrow">
      <Link href="/zh-cn/exchanges/">交易所资料 /</Link>
      <h1>{e.name}</h1>
      <span className="badge">{e.verificationStatus === 'verified' ? '已核验' : '待核验'}</span>
      <p className="lead">{e.summary || '本页资料正在整理，尚未形成评测结论。'}</p>
      <section className="panel">
        <h2>地区与资格</h2>
        <p>{e.regionNotes || '请在交易所官方网站核实所在地和拟使用产品的资格要求。'}</p>
        <h2>费用条件</h2>
        <p>{e.feeNotes || '尚待核验。实际费用可能取决于交易产品、账户等级和优惠条件。'}</p>
        {e.verifiedAt && <p>核验日期：{new Date(e.verifiedAt).toLocaleDateString('zh-CN')}</p>}
        {e.sourceURL && /^https:\/\//.test(e.sourceURL) && (
          <a href={e.sourceURL} rel="noopener noreferrer">
            查看资料来源
          </a>
        )}
      </section>
      <h2>评测与分析</h2>
      {reviews.docs.length ? (
        reviews.docs.map((a) => (
          <p key={a.id}>
            <Link href={`/zh-cn/reviews/${a.slug}/`}>{a.title}</Link>
          </p>
        ))
      ) : (
        <p>暂未发布已核验的评测。</p>
      )}
      <Link className="button" href={`/zh-cn/register/${e.slug}/`}>
        查看注册入口与条款 →
      </Link>
    </article>
  )
}
