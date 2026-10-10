import Link from 'next/link'
import { exchanges } from '@/lib/cms'
export const dynamic = 'force-dynamic'
export const metadata = { title: '交易所资料' }
export default async function Page() {
  return (
    <section className="page">
      <p className="eyebrow">EXCHANGES</p>
      <h1>交易所资料库</h1>
      <p className="lead">先了解适用条件，再选择交易平台。</p>
      <div className="grid">
        {(await exchanges()).map((e) => (
          <Link className="card" key={e.id} href={`/zh-cn/exchanges/${e.slug}/`}>
            <span className="badge">
              {e.verificationStatus === 'verified' ? '已核验' : '待核验'}
            </span>
            <h2>{e.name}</h2>
            <p>{e.summary || '资料正在整理，请以官方信息为准。'}</p>
            <span>查看详情 →</span>
          </Link>
        ))}
      </div>
    </section>
  )
}
