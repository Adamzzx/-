import { exchanges } from '@/lib/cms'
import Link from 'next/link'
export const dynamic = 'force-dynamic'
export const metadata = { title: '交易所比较', robots: { index: false, follow: true } }
export default async function Page({
  searchParams,
}: {
  searchParams: Promise<{ a?: string; b?: string }>
}) {
  const docs = await exchanges()
  const q = await searchParams
  const a = docs.find((e) => e.slug === q.a) || docs.find((e) => e.slug === 'binance')
  const b = docs.find((e) => e.slug === q.b) || docs.find((e) => e.slug === 'bybit')
  return (
    <section className="page">
      <p className="eyebrow">COMPARE</p>
      <h1>并排比较，更清楚</h1>
      <p className="lead">资料缺失表示尚未核验，不代表交易所不支持该功能。</p>
      <form className="selectors" action="/zh-cn/compare/" method="get">
        <label>
          第一家
          <select name="a" defaultValue={a?.slug}>
            {docs.map((e) => (
              <option key={e.id} value={e.slug}>
                {e.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          第二家
          <select name="b" defaultValue={b?.slug}>
            {docs.map((e) => (
              <option key={e.id} value={e.slug}>
                {e.name}
              </option>
            ))}
          </select>
        </label>
        <button className="button">比较</button>
      </form>
      {a && b && (
        <div className="table-scroll" tabIndex={0} role="region" aria-label="交易所比较表">
          <table>
            <caption>
              {a.name} 与 {b.name}：核验资料比较
            </caption>
            <thead>
              <tr>
                <th>比较项目</th>
                <th>{a.name}</th>
                <th>{b.name}</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th>资料状态</th>
                {[a, b].map((e, i) => (
                  <td key={i}>{e.verificationStatus === 'verified' ? '已核验' : '待核验'}</td>
                ))}
              </tr>
              <tr>
                <th>费用与条件</th>
                {[a, b].map((e, i) => (
                  <td key={i}>{e.feeNotes || '待核验'}</td>
                ))}
              </tr>
              <tr>
                <th>地区与资格</th>
                {[a, b].map((e, i) => (
                  <td key={i}>{e.regionNotes || '待核验'}</td>
                ))}
              </tr>
              <tr>
                <th>进一步了解</th>
                {[a, b].map((e, i) => (
                  <td key={i}>
                    <Link href={`/zh-cn/exchanges/${e.slug}/`}>查看 {e.name} 资料 →</Link>
                  </td>
                ))}
              </tr>
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
