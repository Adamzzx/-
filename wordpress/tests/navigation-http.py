import runpy,re
context=runpy.run_path('wordpress/tests/admin-smoke.py')
request=context['request']
s,h,body=request('/wp-admin/admin.php?page=cg-setup')
form=next(f for f in re.findall(r'<form\b.*?</form>',body,re.S) if 'value="cg_navigation"' in f)
nonce=re.search(r'name="_wpnonce" value="([^"]+)"',form).group(1)
s,h,body=request('/wp-admin/admin-post.php',{'action':'cg_navigation','_wpnonce':nonce})
assert s==302 and 'cg_navigation_done=1' in h.get('Location','')
s,h,body=request('/wp-admin/admin-post.php',{'action':'cg_navigation'})
assert s==403
s,h,body=request('/')
nav=re.search(r'<nav id="cg-primary-nav".*?</nav>',body,re.S).group(0)
labels=['Binance','OKX','Bitget','Bybit','交易所对比']
assert [nav.index('>'+x+'<') for x in labels]==sorted(nav.index('>'+x+'<') for x in labels)
assert 'MEXC' not in nav and 'Gate' not in nav
assert '工具与资源' not in nav and '投资指南' not in nav
assert 'aria-controls="cg-primary-nav"' in body and 'cg-submenu-toggle' in nav
assert 'Crypto Investing &amp; Digital Finance' in body
s,h,body=request('/topics/crypto/wallets-security/')
assert s==200 and 'cg-crypto-archive' in body,'Configured empty crypto archive remains accessible'
assert 'noindex' in re.search(r'<meta name=[\'"]robots[\'"].*?>',body).group(0),'Empty crypto archive should not be indexed'
s,h,body=request('/topics/crypto/beginners/')
assert s==200 and '导航分类测试' in body
print('PASS: navigation setup action/nonce, confirmed order, removed links, responsive controls and populated/empty category routing')
