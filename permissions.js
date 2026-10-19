// Admin controls and a focused daily-work view for clinic staff.
const isAdmin=()=>state?.user.role==='admin';
const roleRender=render;
render=function(){
 roleRender();
 const heading=document.querySelector('.page-heading');
 if(heading){const note=document.createElement('div');note.className='role-notice';note.textContent=isAdmin()?'Administrator workspace · Manage the clinic, staff accounts, and records.':'Staff workspace · Daily patient care, appointments, and billing.';heading.after(note);}
 if(!isAdmin()){
  document.querySelector('button[onclick="exportCSV()"]')?.remove();
  if(page==='inventory')document.querySelector('.page-heading .primary')?.remove();
  document.querySelectorAll('button[onclick^="openForm(\'inventory\'"]').forEach(button=>{button.disabled=true;button.removeAttribute('onclick');});
  if(page==='dashboard'){
   const revenue=document.querySelector('.stats button[onclick="navigate(\'invoices\')"]');
   if(revenue){const count=rows('appointments').filter(r=>r.data.date===today()&&r.data.status!=='Completed'&&r.data.status!=='Cancelled').length;revenue.innerHTML=`<div>Visits to complete<span class="stat-icon peach">▦</span></div><strong>${count}</strong><small>Today’s remaining appointments<span>↗</span></small>`;revenue.onclick=()=>navigate('appointments');}
  }
 }else if(page==='users'){
  const button=document.querySelector('.page-heading .primary');if(button)button.textContent='＋ Create staff account';
 }
};
const roleDrawTable=drawTable;
drawTable=function(){roleDrawTable();if(!isAdmin()&&page==='inventory'){
 const table=document.querySelector('#table table');
 if(table)for(const row of table.rows){row.cells[4]?.remove();const actions=row.lastElementChild;if(actions){if(row.parentElement.tagName==='THEAD')actions.textContent='Access';else actions.innerHTML='<span class="muted">View only</span>';}}
}};
const roleOpenForm=openForm;
openForm=function(type,id=0){
 if(!isAdmin()&&['users','inventory'].includes(type))return toast('Administrator access required.');
 roleOpenForm(type,id);
 if(type==='users'){
  const select=document.querySelector('#record-form [name=role]');if(select)select.value='staff';
  const title=document.querySelector('.modal-heading h2');if(title)title.textContent='Create team account';
  const note=document.createElement('p');note.textContent='Staff handle daily clinic operations. Administrators also manage accounts, inventory, exports, and record deletion.';document.querySelector('#record-form .modal-heading')?.after(note);
 }
};
const roleExport=exportCSV;
exportCSV=function(){if(!isAdmin())return toast('Only administrators can export clinic records.');roleExport();};
