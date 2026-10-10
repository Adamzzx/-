import runpy,re,subprocess,json,base64
ctx=runpy.run_path('wordpress/tests/admin-smoke.py');request=ctx['request']
def wp(*args):
    return subprocess.check_output(['docker','compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp',*args],text=True).strip()
s,h,b=request('/wp-admin/admin.php?page=cg-content-crypto')
assert s==200 and '加密货币 · 内容管理' in b and '导航分类测试' in b
sidebar=re.search(r'<ul id="adminmenu".*?</ul>\s*</div>',b,re.S).group(0)
for title in ['网站管理','交易所管理','美股代币','加密货币','专题与指南']:assert title in sidebar,title
exchange=re.search(r'<li[^>]+id="menu-posts-cg_exchange".*?</ul>',sidebar,re.S).group(0)
for title in ['交易所资料','交易所评测','交易所对比','推荐榜单']:assert title in exchange,title
for post_type in ['cg_review','cg_compare','cg_ranking']:assert 'id="menu-posts-'+post_type+'"' not in sidebar
s,h,tokenized=request('/wp-admin/admin.php?page=cg-content-tokenized-stocks')
assert s==200 and '美股代币 · 内容管理' in tokenized and '还没有可编辑的主题内容' in tokenized
form=next(f for f in re.findall(r'<form\b.*?</form>',b,re.S) if 'value="cg_topic_draft"' in f)
nonce=re.search(r'name="_wpnonce" value="([^"]+)"',form).group(1)
term=re.search(r'<option value="(\d+)"',form).group(1)
s,h,b=request('/wp-admin/admin-post.php',{'action':'cg_topic_draft','cg_term':term});assert s==403
s,h,b=request('/wp-admin/admin-post.php',{'action':'cg_topic_draft','cg_term':'0','_wpnonce':nonce});assert s==400
s,h,b=request('/wp-admin/admin-post.php',{'action':'cg_topic_draft','cg_term':term,'_wpnonce':nonce})
assert s==302 and 'action=edit' in h.get('Location','')
id=re.search(r'post=(\d+)',h['Location']).group(1)
try:
    assert wp('post','get',id,'--field=post_status')=='auto-draft'
    assert int(wp('post','meta','get',id,'_cg_primary_topic'))==int(term)
    assert 'crypto' in wp('post','term','list',id,'cg_topic','--field=slug')
finally:wp('post','delete',id,'--force')
print('PASS: reorganized backend menus, cross-type topic desk, empty-topic guidance, CSRF/input checks and attributed new draft')

assert wp('option','get','home')=='http://localhost:8088'
state=wp('eval',"$result=array();foreach(get_posts(array('post_type'=>'cg_exchange','post_status'=>'any','numberposts'=>-1)) as $p){$result[]=array('ID'=>$p->ID,'post_status'=>$p->post_status);}echo wp_json_encode($result);")
encoded=base64.b64encode(state.encode()).decode()
try:
    wp('eval',"foreach(json_decode(base64_decode('"+encoded+"'),true) as $p){wp_update_post(array('ID'=>$p['ID'],'post_status'=>'trash'));}")
    s,h,b=request('/wp-admin/edit.php?post_type=cg_exchange')
    assert s==200 and '尚未创建交易所资料' in b and '创建基础页面并保存名称' in b
finally:
    wp('eval',"foreach(json_decode(base64_decode('"+encoded+"'),true) as $p){wp_update_post($p);}")
print('PASS: empty exchange list links to initialization, preserving existing records')
