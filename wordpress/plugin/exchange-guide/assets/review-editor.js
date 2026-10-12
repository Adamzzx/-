(function(wp){
 const e=wp.element.createElement, C=wp.components;
 const labels=['现货手续费','合约手续费','交易产品','资产储备信息','KYC','支持的地区','法币入金方式','美股代币支持情况','理财产品','安全功能'];
 wp.blocks.registerBlockType('cg/review-table',{
 apiVersion:3,title:'评测核验数据表',icon:'editor-table',category:'text',attributes:{title:{type:'string',default:'核验资料'},rows:{type:'array',default:[]}},
 edit:function(props){
  const rows=props.attributes.rows||[], set=(i,key,value)=>props.setAttributes({rows:rows.map((r,n)=>n===i?Object.assign({},r,{[key]:value}):r)});
  return e('div',wp.blockEditor.useBlockProps(),
   e(C.TextControl,{label:'表格标题',value:props.attributes.title,onChange:title=>props.setAttributes({title})}),
   e('p',null,'数值只有在已核验、来源和有效核验日期齐全时才展示。审核完整性不代表系统确认了数据真实性。'),
   rows.map((r,i)=>e(C.PanelBody,{key:i,title:r.label||'未命名项目',initialOpen:i===0},
    ['label','value','conditions','sourceName','source','date'].map(key=>e(C.TextControl,{key,label:({label:'项目',value:'数值或产品说明',conditions:'适用条件',sourceName:'来源名称',source:'HTTPS 来源链接',date:'实际核验日期'})[key],type:key==='date'?'date':key==='source'?'url':'text',value:r[key]||'',onChange:v=>set(i,key,v)})),
    e(C.SelectControl,{label:'核验状态',value:r.status||'unverified',options:[{label:'未核验',value:'unverified'},{label:'已核验（人工确认）',value:'verified'},{label:'需重新核验',value:'stale'}],onChange:v=>set(i,'status',v)}),
    e(C.Button,{isDestructive:true,onClick:()=>props.setAttributes({rows:rows.filter((_,n)=>n!==i)})},'删除此行'))),
   e(C.Button,{variant:'secondary',onClick:()=>props.setAttributes({rows:rows.concat([{label:'',status:'unverified'}])})},'增加一行'),
   rows.length===0?e(C.Button,{variant:'secondary',onClick:()=>props.setAttributes({rows:labels.map(label=>({label,status:'unverified'}))})},'加入十项参数名称（不填数据）'):null);
 },save:()=>null});
})(window.wp);
