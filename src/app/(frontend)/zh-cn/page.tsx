import Link from 'next/link'
import { exchanges } from '@/lib/cms'
export const dynamic = 'force-dynamic'
export default async function Page() {
  const docs = await exchanges()
  return (
    <>
      <section className="hero">
        <p className="eyebrow">面向中文用户的交易所决策工具</p>
        <h1>
          找到适合你的交易所，
          <br />
          从比较开始。
        </h1>
        <p className="lead">了解费用条件、地区资格与操作流程，让每一次选择都有依据。</p>
        <Link className="button" href="/zh-cn/compare/">
          开始比较 <span aria-hidden>↗</span>
        </Link>
      </section>
      <section>
        <div className="section-head">
          <h2>六家交易所，一处了解</h2>
          <Link href="/zh-cn/exchanges/">查看全部 →</Link>
        </div>
        <p className="muted">目前为建设中的资料库。未核验的数据不会显示为事实或评分。</p>
        <div className="grid">
          {docs.map((e) => (
            <Link className="card" key={e.id} href={`/zh-cn/exchanges/${e.slug}/`}>
              <span className="badge">
                {e.verificationStatus === 'verified' ? '已核验' : '待核验'}
              </span>
              <h3>{e.name}</h3>
              <p>{e.summary || '查看交易所资料、费用条件与注册入口。'}</p>
              <span>查看资料 →</span>
            </Link>
          ))}
        </div>
        {!docs.length && <p>交易所资料即将上线。</p>}
      </section>
      <section className="principles">
        <h2>比较有依据，选择更清楚</h2>
        <div className="grid">
          <div>
            <h3>条件写明白</h3>
            <p>费用与优惠必须注明等级、地区和适用条件。</p>
          </div>
          <div>
            <h3>信息有来源</h3>
            <p>交易所资料展示核验状态，未确认的信息保留为空。</p>
          </div>
          <div>
            <h3>推广有披露</h3>
            <p>推荐链接单独管理，不通过佣金决定编辑结论。</p>
          </div>
        </div>
      </section>
    </>
  )
}
