import runpy
suite=runpy.run_path('wordpress/tests/http-smoke.py')
get,check=suite['get'],suite['check']
status,headers,body=get('/')
check(status==200 and '从比较开始' not in body and '六家交易所，一处了解' not in body,'old comparison-only homepage removed')
for label in ['理解加密资产','做出更有依据的投资决策','美股代币与代币化资产','加密货币与资产配置','最新发布','交易所开户注册']:
    check(label in body,'media home section: '+label)
check('smoke-draft-review' not in body,'draft article excluded from homepage')
check('/reviews/smoke-published-review/' in body,'published review displayed on homepage')
check('/guides/navigation-topic-smoke/' in body,'published topical guide displayed on homepage')
check('自定义首页内容不会被覆盖' in body,'owner homepage content retained')
