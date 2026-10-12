import runpy,re,subprocess
ctx=runpy.run_path('wordpress/tests/admin-smoke.py');request=ctx['request']
def wp(*args):return subprocess.check_output(['docker','compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',*args],text=True).strip()
s,h,b=request('/wp-admin/admin.php?page=cg-review-start');assert s==200 and '不会填写数据或自动发布' in b
nonce=re.search(r'name="_wpnonce" value="([^"]+)"',b).group(1)
s,h,b=request('/wp-admin/admin-post.php',{'action':'cg_review_create'});assert s==403
s,h,b=request('/wp-admin/admin-post.php',{'action':'cg_review_create','_wpnonce':nonce});assert s==302
id=re.search(r'post=(\d+)',h['Location']).group(1)
try:
 assert wp('post','get',id,'--field=post_type')=='post'
 assert wp('post','get',id,'--field=post_status')=='draft'
 assert wp('post','meta','get',id,'_wp_page_template')=='templates/exchange-review.php'
 assert wp('post','meta','get',id,'_cg_review_exchange')=='binance'
 assert '币安 Binance 评测 2026' in wp('post','get',id,'--field=post_title')
 body=wp('post','get',id,'--field=post_content');assert '"value"' not in body and '美股代币支持情况' in body
 s,h,b=request('/wp-admin/admin-post.php',{'action':'cg_review_create','_wpnonce':nonce});assert re.search(r'post=(\d+)',h['Location']).group(1)==id
 print('PASS native Binance draft title, template, platform, empty-data structure, nonce and repeated creation protection')
finally:wp('post','delete',id,'--force')
