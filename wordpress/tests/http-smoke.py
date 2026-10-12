import urllib.request, urllib.error, json
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs): return None
opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),NoRedirect())
def get(path):
    try: r=opener.open('http://localhost:8088'+path,timeout=15)
    except urllib.error.HTTPError as e:r=e
    return r.code,r.headers,r.read().decode()
def check(ok,label):
    assert ok,label
    print('PASS:',label)
s,h,body=get('/')
check(s==200 and '<html lang="' in body and all(n in body for n in ['Binance','Bybit','OKX','Bitget']) and '理解加密资产' in body,'media homepage renders confirmed exchange coverage')
s,h,body=get('/compare/?a=binance&b=okx')
check(s==200 and 'OKX' in body and '测试费用条件' in body,'comparison reads central exchange data')
s,h,body=get('/compare/?a[]=binance&b=%3Cscript%3E')
check(s==200 and '<script>alert' not in body,'malformed filter input handled')
s,h,body=get('/exchanges/binance/')
check(s==200 and 'Binance（币安）' in body and 'Binance 相关文章' in body and '费用与条件</h2>' not in body and '核验日期：' not in body,'exchange hub has an introduction and related articles')
s,h,body=get('/go/binance/?url=https://evil.invalid/')
check(s==302 and h.get('Location')=='https://www.binance.com/en/register?ref=smoke','safe central redirect ignores injected destination')
check('noindex' in h.get('X-Robots-Tag','') and 'no-store' in h.get('Cache-Control',''),'redirect excludes indexing and caching')
s,h,body=get('/go/bybit/')
check(s==302 and h.get('Location')=='https://www.bybit.com/en/register','missing offer uses official registration')
s,h,body=get('/go/unknown/')
check(s==404,'unknown exchange returns 404')
s,h,body=get('/reviews/smoke-published-review/')
check(s==200 and '用于验证发布流程' in body,'published editorial content renders')
s,h,body=get('/reviews/smoke-draft-review/')
check(s==404,'draft review not public')
s,h,body=get('/wp-json/wp/v2/cg_exchange')
check(s==200 and '_cg_destination' not in body and 'ref=smoke' not in body,'private referral configuration excluded from public REST data')
for path in ['/best/','/guides/','/tokenized-stocks/','/disclosure/','/methodology/','/exchanges/','/reviews/']:
    check(get(path)[0]==200,'directory '+path)
s,h,body=get('/not-a-real-page/')
check(s==404 and '暂时不存在' in body,'themed 404')
