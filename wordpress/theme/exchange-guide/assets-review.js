document.querySelectorAll('.cg-review-eligibility-form').forEach(form=>{
 form.addEventListener('submit',async event=>{
  event.preventDefault();const result=form.querySelector('.cg-review-eligibility-result');result.textContent='正在检查…';
  try{
   const response=await fetch(form.dataset.endpoint,{method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({post:Number(form.dataset.post),country:form.elements.country.value.trim().toUpperCase(),confirmed:form.elements.confirmed.checked})});
   const data=await response.json();if(!response.ok){throw new Error(data.message||'入口不可用');}
   const url=new URL(data.url);if(url.origin!==location.origin){throw new Error('入口地址异常');}
   const link=document.createElement('a');link.href=url.href;link.target='_blank';link.rel='noopener noreferrer sponsored nofollow';link.className='cg-button';link.textContent=data.label;result.replaceChildren(link);
  }catch(error){result.textContent=error.message;}
 });
});
