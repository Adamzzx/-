import runpy
suite=runpy.run_path('wordpress/tests/http-smoke.py')
get,check=suite['get'],suite['check']
status,headers,body=get('/')
check(status==200 and 'id="home-articles"' in body,'homepage renders the editorial feed')
for label in ['从比较开始','六家交易所，一处了解','开户注册前确认','让信息有依据，让风险看得见','自定义首页内容不会被覆盖']:
    check(label not in body,'removed homepage block or full body is absent: '+label)
check('role="search"' in body and 'name="s"' in body,'homepage provides a native article search form')
check(body.count('cg-registration-label')==4,'homepage retains four exchange registration banners')
check('smoke-draft-review' not in body,'draft article excluded from homepage')
check('/reviews/smoke-published-review/' in body,'published review displayed on homepage')
check('/guides/navigation-topic-smoke/' in body,'published topical guide displayed on homepage')
