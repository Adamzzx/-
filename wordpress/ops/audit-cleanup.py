#!/usr/bin/env python3
"""Anonymous, read-only public audit. Uses only GET; no cache purge or submissions."""
import argparse, concurrent.futures, csv, datetime, hashlib, json, re, urllib.error, urllib.parse, urllib.request, xml.etree.ElementTree as ET
from html.parser import HTMLParser
from pathlib import Path

P=argparse.ArgumentParser();P.add_argument('--base',default='https://degendeer.com/');P.add_argument('--out',default='cleanup-audit-output');P.add_argument('--label',default='public-check');A=P.parse_args()
BASE=A.base.rstrip('/')+'/';HOST=urllib.parse.urlsplit(BASE).netloc;OUT=Path(A.out);OUT.mkdir(parents=True,exist_ok=True);RAW=OUT/(A.label+'-responses');RAW.mkdir(exist_ok=True)
UA='DegenDeer-Authorized-ReadOnly-Audit/2026-10-21'
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl): return None
OPENER=urllib.request.build_opener(NoRedirect)
def request(url,follow=True):
 req=urllib.request.Request(url,headers={'User-Agent':UA,'Accept':'text/html,application/xml,application/json,text/plain;q=0.9,*/*;q=0.8'})
 try:
  r=(urllib.request.urlopen(req,timeout=30) if follow else OPENER.open(req,timeout=30));body=r.read();return {'status':r.status,'url':r.url,'headers':dict(r.headers),'body':body.decode('utf-8',errors='replace')}
 except urllib.error.HTTPError as e:return {'status':e.code,'url':e.url,'headers':dict(e.headers),'body':e.read().decode('utf-8',errors='replace')}
 except Exception as e:return {'status':None,'url':url,'headers':{},'body':'','error':type(e).__name__+': '+str(e)}
def hdr(d,name):return next((v for k,v in d.items() if k.lower()==name.lower()),None)
class Parser(HTMLParser):
 def __init__(self):super().__init__(convert_charrefs=True);self.links=[];self.meta=[];self.scripts=[];self.iframes=[];self.forms=[];self.inputs=[];self.text=[];self.stack=[];self.current_link=None;self.current_form=None
 def handle_starttag(self,tag,attrs):
  d=dict(attrs);context=' '.join(self.stack[-5:]);
  if tag not in ['area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr']:self.stack.append(tag+('#'+d.get('id','') if d.get('id') else '')+('.'+d.get('class','').replace(' ','.') if d.get('class') else ''))
  if tag=='a' and d.get('href'):
   row={'href':d['href'],'text':'','context':context,'rel':d.get('rel','')};self.links.append(row);self.current_link=row
  if tag=='meta':self.meta.append(d)
  if tag=='link' and d.get('rel')=='canonical':self.canonical=d.get('href')
  if tag=='script':self.scripts.append({'src':d.get('src'),'id':d.get('id'),'inline':''});self.in_script=True
  if tag=='iframe':self.iframes.append(d)
  if tag=='form':self.current_form={'action':d.get('action'),'method':d.get('method','get'),'id':d.get('id'),'class':d.get('class'),'inputs':[]};self.forms.append(self.current_form)
  if tag=='input' and self.current_form is not None:self.current_form['inputs'].append({'name':d.get('name'),'type':d.get('type')})
 def handle_endtag(self,tag):
  if tag=='a':self.current_link=None
  if tag=='form':self.current_form=None
  if tag=='script':self.in_script=False
  for i in range(len(self.stack)-1,-1,-1):
   if self.stack[i].split('#')[0].split('.')[0]==tag:self.stack=self.stack[:i];break
 def handle_data(self,data):
  if getattr(self,'in_script',False):
   if self.scripts:self.scripts[-1]['inline']+=data
  elif data.strip():self.text.append(data.strip())
  if self.current_link is not None:self.current_link['text']+=data.strip()+' '

def inspect(url):
 first=request(url,False);last=first
 if first['status'] in (301,302,303,307,308):last=request(url,True)
 p=Parser();
 if 'html' in str(hdr(last['headers'],'Content-Type')):p.feed(last['body'])
 path=urllib.parse.urlsplit(url).path;rawfile=RAW/(hashlib.sha256(url.encode()).hexdigest()[:16]+'.txt');rawfile.write_text(last['body'])
 return {'requested_url':url,'initial_status':first['status'],'location':hdr(first['headers'],'Location'),'final_url':last['url'],'final_status':last['status'],'canonical':getattr(p,'canonical',None),'robots_meta':[m.get('content') for m in p.meta if m.get('name','').lower() in ('robots','googlebot','bingbot')],'x_robots_tag':hdr(last['headers'],'X-Robots-Tag'),'cache_control':hdr(last['headers'],'Cache-Control'),'server':hdr(last['headers'],'Server'),'x_litespeed_cache':hdr(last['headers'],'X-LiteSpeed-Cache'),'x_hcdn_cache_status':hdr(last['headers'],'X-Hcdn-Cache-Status'),'set_cookie':hdr(last['headers'],'Set-Cookie'),'title':next((m.get('content') for m in p.meta if m.get('property')=='og:title'),None),'links':p.links,'scripts':p.scripts,'iframes':p.iframes,'forms':p.forms,'suggested_text_occurrences':last['body'].count('Suggested text:'),'body_sha256':hashlib.sha256(last['body'].encode()).hexdigest(),'response_file':str(rawfile),'text':' '.join(p.text),'error':last.get('error')}

def xml_loc(node):return node.findtext('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')
smqueue=[urllib.parse.urljoin(BASE,'sitemap_index.xml')];maps=[];entries=[];seenmaps=set()
while smqueue:
 u=smqueue.pop(0)
 if u in seenmaps:continue
 seenmaps.add(u);r=request(u);row={'url':u,'status':r['status'],'headers':r['headers'],'error':r.get('error')};maps.append(row)
 (RAW/(hashlib.sha256(u.encode()).hexdigest()[:16]+'.xml')).write_text(r['body'])
 try:root=ET.fromstring(r['body'])
 except Exception as e:row['parse_error']=str(e);continue
 if root.tag.endswith('sitemapindex'):
  for n in root:
   v=xml_loc(n)
   if v:smqueue.append(v)
 elif root.tag.endswith('urlset'):
  for n in root:
   v=xml_loc(n)
   if v:entries.append({'url':v,'sitemap':u,'lastmod':n.findtext('{http://www.sitemaps.org/schemas/sitemap/0.9}lastmod')})
urls=set(e['url'] for e in entries)
legacy_paths=['/exchanges/mexc/','/exchanges/gate/','/best/','/guides/','/home/']
seed_paths=['/','/exchanges/','/compare/','/tokenized-stocks/','/topics/crypto/','/topics/crypto/beginners/','/topics/crypto/wallets-security/','/topics/crypto/yield-products/','/exchanges/binance/','/exchanges/okx/','/exchanges/bitget/','/exchanges/bybit/','/about/','/contact/','/disclosure/','/methodology/','/privacy-policy/','/author/admin/','/robots.txt','/BingSiteAuth.xml']+legacy_paths
urls.update(urllib.parse.urljoin(BASE,x) for x in seed_paths)
api=[]
for route in ['wp-json/wp/v2/types','wp-json/wp/v2/posts?per_page=100&_fields=id,slug,link,status,title,modified,modified_gmt,template','wp-json/wp/v2/pages?per_page=100&_fields=id,slug,link,status,title,modified,modified_gmt,template']:
 u=urllib.parse.urljoin(BASE,route);r=request(u);item={'url':u,'status':r['status'],'headers':r['headers']};api.append(item)
 try:
  val=json.loads(r['body']);item['data']=val
  if isinstance(val,list):
   for x in val:
    if x.get('link'):urls.add(x['link'])
  if route=='wp-json/wp/v2/types' and isinstance(val,dict):
   for key,t in val.items():
    if key not in ['post','page','attachment'] and t.get('rest_base'):
     extra=urllib.parse.urljoin(BASE,t.get('rest_namespace','wp/v2')+'/'+t['rest_base'])
     if '/wp-json/' not in extra:extra=urllib.parse.urljoin(BASE,'wp-json/'+t.get('rest_namespace','wp/v2')+'/'+t['rest_base'])
     er=request(extra+'?per_page=100');erow={'url':extra,'status':er['status']};api.append(erow)
     try:
      ev=json.loads(er['body']);erow['data']=ev
      if isinstance(ev,list):
       for x in ev:
        if x.get('link'):urls.add(x['link'])
     except Exception as e:erow['parse_error']=str(e)
 except Exception as e:item['parse_error']=str(e)
results=[];seen=set();frontier=sorted(urls)
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
 while frontier:
  batch=list(dict.fromkeys(u for u in frontier if u not in seen));frontier=[];seen.update(batch)
  for row in pool.map(inspect,batch):
   results.append(row)
   for a in row['links']:
    v=urllib.parse.urljoin(row['final_url'],a['href']);sp=urllib.parse.urlsplit(v)
    params=urllib.parse.parse_qs(sp.query)
    pagination_query=bool(params) and set(params).issubset({'cg_home_page','paged'}) and all(len(x)==1 and x[0].isdigit() for x in params.values())
    pagination_path=bool(re.fullmatch(r'/page/[0-9]+/?',sp.path))
    target=urllib.parse.urlunsplit((sp.scheme,sp.netloc,sp.path,sp.query,''))
    if sp.netloc==HOST and (pagination_query or pagination_path or a.get('rel')=='next') and target not in seen:frontier.append(target)

go=[]
for slug in ['binance','okx','bitget','bybit']:
 u=urllib.parse.urljoin(BASE,'go/'+slug+'/');r=request(u,False);go.append({'url':u,'status':r['status'],'location':hdr(r['headers'],'Location'),'x_robots_tag':hdr(r['headers'],'X-Robots-Tag'),'cache_control':hdr(r['headers'],'Cache-Control')})
legacy_set=set(legacy_paths);bad_links=[]
for row in results:
 for link in row['links']:
  u=urllib.parse.urljoin(row['final_url'],link['href']);sp=urllib.parse.urlsplit(u)
  if sp.netloc==HOST and urllib.parse.unquote(sp.path).rstrip('/')+'/' in legacy_set:
   bad_links.append({'source':row['requested_url'],'target':u,'text':link['text'].strip(),'context':link['context']})
for row in results:
 row['index_allowed']=row['final_status']==200 and not any(re.search(r'\b(noindex|none)\b',str(x),re.I) for x in row['robots_meta']+[row['x_robots_tag']] if x)
 row['canonical_matches']=row['canonical'] is not None and row['canonical'].rstrip('/')==row['final_url'].rstrip('/')
 row['in_sitemap']=row['requested_url'] in set(e['url'] for e in entries)
checktime=datetime.datetime.now(datetime.timezone.utc).isoformat()
report={'checked_at_utc':checktime,'base':BASE,'anonymous':True,'only_get':True,'cache_purged':False,'production_mutated':False,'sitemaps':maps,'sitemap_entries':entries,'sitemap_unique_urls':sorted(set(e['url'] for e in entries)),'api':api,'pages':results,'legacy_inbound_links':bad_links,'go_endpoints':go}
(OUT/(A.label+'.json')).write_text(json.dumps(report,ensure_ascii=False,indent=2))
(OUT/(A.label+'-sitemap-urls.txt')).write_text('\n'.join(report['sitemap_unique_urls'])+'\n')
fields=['requested_url','initial_status','location','final_status','final_url','canonical','robots_meta','x_robots_tag','index_allowed','canonical_matches','in_sitemap','suggested_text_occurrences','error']
with (OUT/(A.label+'.csv')).open('w') as f:
 w=csv.DictWriter(f,fieldnames=fields,extrasaction='ignore',lineterminator='\n');w.writeheader();w.writerows(results)
print(json.dumps({'checked_at_utc':checktime,'sitemaps':len(maps),'entries':len(entries),'unique_urls':len(report['sitemap_unique_urls']),'pages_checked':len(results),'legacy':[ {k:r[k] for k in fields} for r in results if urllib.parse.urlsplit(r['requested_url']).path in legacy_paths],'privacy':[{'url':r['requested_url'],'suggested_text_occurrences':r['suggested_text_occurrences']} for r in results if '/privacy-policy/' in r['requested_url']],'legacy_inbound_links':bad_links,'go_endpoints':go,'sitemap_problems':[{'url':r['requested_url'],'status':r['final_status'],'robots':r['robots_meta'],'canonical':r['canonical']} for r in results if r['in_sitemap'] and (not r['index_allowed'] or not r['canonical_matches'] or r['initial_status']!=200)]},ensure_ascii=False,indent=2))
