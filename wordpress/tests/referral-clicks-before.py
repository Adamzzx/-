import os, subprocess, json, http.client
assert os.environ.get('COMPOSE_PROJECT_NAME')=='exchange-guide-click-test'
def wp(code):
    cmd=['docker','compose','-f','wordpress/tests/compose.packages.yaml','run','--rm','-T','cli','wp','eval',code]
    r=subprocess.run(cmd,text=True,capture_output=True,check=True); return r.stdout.strip()
assert wp('echo home_url();')=='http://localhost:8088'
assert wp('echo function_exists("cg_clicks_markup")?"new":"legacy";')=='legacy', 'Install the archived 1.14.3 ZIP before reproducing the old mechanism'
assert wp('echo cg_active_offer(cg_exchange("binance"))?"1":"0";')=='1'
snapshot=wp('echo wp_json_encode(get_post_meta(cg_exchange("binance")->ID,"_cg_clicks"));')
def count(): return int(wp('echo get_post_meta(cg_exchange("binance")->ID,"_cg_clicks",true);'))
def request(path,method='GET',headers=None,body=None):
    c=http.client.HTTPConnection('localhost',8088,timeout=30); c.request(method,path,body=body,headers=headers or {});r=c.getresponse();data=r.read();out=(r.status,r.getheader('Location'),data);c.close();return out
results=[]
def scenario(name,paths,method='GET',headers=None):
    before=count(); statuses=[]
    for path in paths: statuses.append(request(path,method,headers)[0])
    delta=count()-before;results.append({'scenario':name,'delta':delta,'status':statuses});print(json.dumps(results[-1],ensure_ascii=False),flush=True)
article=None
try:
    scenario('homepage view and five reloads',['/']*6)
    scenario('platform page view',['/exchanges/binance/'])
    scenario('ordinary anonymous redirect request',['/go/binance/'])
    scenario('HEAD link check',['/go/binance/'],'HEAD')
    scenario('prefetch',['/go/binance/'],headers={'Purpose':'prefetch','Sec-Purpose':'prefetch','Sec-Fetch-Mode':'no-cors'})
    scenario('crawler link check',['/go/binance/'],headers={'User-Agent':'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'})
    scenario('repeated same redirect request',['/go/binance/','/go/binance/'])
    article=int(wp('$id=wp_insert_post(array("post_type"=>"post","post_status"=>"publish","post_title"=>"Local click baseline review","post_content"=>"Local fixture only"));update_post_meta($id,"_cg_review_exchange","binance");update_post_meta($id,"_cg_review_policy",array("enabled"=>true,"product"=>"Local test","countries"=>array("SG"),"terms"=>"https://www.binance.com/en/terms","verified"=>wp_date("Y-m-d"),"expiry"=>"2030-01-01"));echo $id;'))
    status,_,data=request('/wp-json/cg/v1/review-eligibility','POST',{'Content-Type':'application/json'},json.dumps({'post':article,'country':'SG','confirmed':True}))
    assert status==200,data
    url=json.loads(data)['url']; from urllib.parse import urlsplit
    u=urlsplit(url);scenario('eligible signed article redirect',[u.path+'?'+u.query])
    assert [r['delta'] for r in results]==[0,0,1,1,1,1,2,0]
    print('BASELINE CONFIRMED: requests count, HEAD/prefetch/bot/replays included, signed article not counted; cannot attribute production Oct28.')
finally:
    if article: wp('wp_delete_post('+str(article)+',true);')
    import base64
    encoded=base64.b64encode(snapshot.encode()).decode()
    wp('$id=cg_exchange("binance")->ID;delete_post_meta($id,"_cg_clicks");foreach(json_decode(base64_decode("'+encoded+'"),true) as $v){add_post_meta($id,"_cg_clicks",$v);}echo "restored";')
    assert wp('echo wp_json_encode(get_post_meta(cg_exchange("binance")->ID,"_cg_clicks"));')==snapshot
    print('RESTORED original local legacy counter; never requested production or external destination.')
