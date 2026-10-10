import urllib.request,urllib.error,urllib.parse,http.cookiejar,re
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
cookies=http.cookiejar.CookieJar()
opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(cookies),NoRedirect())
def request(path,data=None):
    req=urllib.request.Request('http://localhost:8088'+path,data=urllib.parse.urlencode(data).encode() if data else None)
    try:r=opener.open(req,timeout=15)
    except urllib.error.HTTPError as e:r=e
    return r.code,r.headers,r.read().decode()
request('/wp-login.php')
s,h,b=request('/wp-login.php',{'log':'smoke-admin','pwd':'local-test-only','wp-submit':'Log In','testcookie':'1','redirect_to':'http://localhost:8088/wp-admin/'})
assert s==302 and any('wordpress_logged_in' in c.name for c in cookies),'Admin login failed'
s,h,b=request('/wp-admin/admin.php?page=cg-setup');assert s==200 and '创建基础页面' in b
nonce=re.search(r'name="_wpnonce" value="([^"]+)"',b).group(1)
s,h,b=request('/wp-admin/admin-post.php',{'action':'cg_setup','_wpnonce':nonce,'cg_brand':'交易所指南','cg_set_home':'1'})
assert s==302 and 'cg_done=1' in h.get('Location',''),'Setup form did not complete'
s,h,b=request('/wp-admin/admin-post.php',{'action':'cg_setup','cg_brand':'未授权变更'})
assert s==403,'Missing setup nonce accepted'
s,h,b=request('/');assert s==200 and '自定义首页内容不会被覆盖' in b
print('PASS: packaged CMS login, initialization form, nonce protection and existing homepage preservation')
