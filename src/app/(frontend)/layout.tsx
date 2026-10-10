import React from 'react'
import Link from 'next/link'
import type { Metadata } from 'next'
import './styles.css'
export const metadata: Metadata = {
  metadataBase: new URL(process.env.SITE_URL || 'http://localhost:3000'),
  title: { default: '交易所指南｜中文交易所比较', template: '%s｜交易所指南' },
  description: '从费用、地区资格和使用场景出发，比较加密交易所。',
}
export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="zh-CN">
      <body>
        <a className="skip" href="#main">
          跳到正文
        </a>
        <header>
          <div className="wrap header">
            <Link className="brand" href="/zh-cn/">
              交易所指南<span>COMPARE BEFORE YOU TRADE</span>
            </Link>
            <nav aria-label="主导航">
              <Link href="/zh-cn/compare/">交易所比较</Link>
              <Link href="/zh-cn/exchanges/">交易所资料</Link>
            </nav>
          </div>
        </header>
        <main id="main" className="wrap">
          {children}
        </main>
        <footer className="wrap">
          <strong>先了解，再选择。</strong>
          <p>
            加密资产价格波动较大，可能损失全部本金。请核实所在地与产品资格；本站不提供投资建议。
          </p>
          <p>
            商业披露：部分注册入口可能是推荐链接，我们可能因此获得佣金。当前资料尚待编辑核验，不构成排名或安全承诺。
          </p>
        </footer>
      </body>
    </html>
  )
}
