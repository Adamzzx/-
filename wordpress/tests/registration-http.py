import runpy
suite=runpy.run_path('wordpress/tests/http-smoke.py')
get,check=suite['get'],suite['check']
for slug in ['binance','okx','bitget','bybit']:
    status,headers,body=get('/register/'+slug+'/')
    check(status==200 and '注册前确认' in body and '地区' in body and '佣金' in body,'registration conditions and disclosure: '+slug)
    check('noindex' in body,'registration page excluded from indexing: '+slug)
status,headers,body=get('/register/binance/')
check('/go/binance/' in body,'registration uses centralized referral')
status,headers,body=get('/register/unknown/')
check(status==404,'unknown registration page returns 404')
status,headers,body=get('/reviews/smoke-published-review/')
check('cg-simple-article' in body and 'cg-registration-sidebar' not in body and 'class="cg-registration-card' not in body,'ordinary editorial page uses simple layout without unconditional registration banners')
