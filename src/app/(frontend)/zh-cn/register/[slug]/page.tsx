import { exchange, cms } from '@/lib/cms'
import { notFound } from 'next/navigation'
import { isOfferActive, isSafeDestination } from '@/lib/exchanges'
export const dynamic = 'force-dynamic'
export const metadata = { title: '注册入口与推荐说明', robots: { index: false, follow: true } }
export default async function Page({ params }: { params: Promise<{ slug: string }> }) {
  const e = await exchange((await params).slug)
  if (!e) notFound()
  const offer = (
    await (
      await cms()
    ).find({
      collection: 'affiliate-offers',
      locale: 'zh-cn',
      where: { exchangeSlug: { equals: e.slug } },
      limit: 1,
      overrideAccess: true,
    })
  ).docs[0]
  const active = offer && isOfferActive(offer) && isSafeDestination(e.slug, offer.destination)
  return (
    <article className="page narrow">
      <p className="eyebrow">REGISTRATION</p>
      <h1>{e.name} 注册入口</h1>
      <p className="notice">
        商业披露：通过推荐入口注册，我们可能获得佣金。是否享受优惠以交易所最终展示的条款为准。
      </p>
      <section className="panel">
        <h2>注册前确认</h2>
        <p>
          请核实所在地、年龄及产品资格，确认访问的是交易所官方域名。本站不会索取账户密码、验证码或钱包助记词。
        </p>
        <p>{e.regionNotes || '具体地区条件尚待核验，请阅读官方条款。'}</p>
        <h2>活动与优惠条件</h2>
        <p>
          {active
            ? offer.terms || '未声明额外优惠，请以官方页面为准。'
            : '目前尚未配置有效的推荐活动。'}
        </p>
        {active ? (
          <a className="button" href={`/go/${e.slug}/`} rel="sponsored nofollow">
            前往 {e.name} 官方注册 →
          </a>
        ) : (
          <p>推荐入口配置完成后会在这里提供。</p>
        )}
      </section>
    </article>
  )
}
