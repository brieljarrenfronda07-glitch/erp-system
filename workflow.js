// Register a pet once; start each visit or care record from its patient profile.
names.consultations='Consultation records';names.vaccinations='Vaccine history';
const workflowOpenForm=openForm;
openForm=function(type,id=0){
 workflowOpenForm(type,id);
 const form=document.querySelector('#record-form');if(!form)return;
 const descriptions={patients:'Register this pet once. Keep their identity and owner here; select requested services when booking a visit.',appointments:'Choose the requested service for this visit. Completing an appointment tracks attendance; record treatment or vaccination details in the care history.',vaccinations:'Record a vaccine that was administered and its next due date. This is the pet’s clinical vaccine history.',consultations:'Record findings, diagnosis, treatment, and prescription instructions for a consultation.'};
 if(descriptions[type]){const p=document.createElement('p');p.className='workflow-hint';p.textContent=descriptions[type];form.querySelector('.modal-heading').after(p);}
 if(type==='appointments'){
  const record=state.records.find(r=>r.type==='appointments'&&r.id===id)?.data||{};
  let chosen=[];try{chosen=JSON.parse(record.services||'[]');}catch{}if(!Array.isArray(chosen)||!chosen.length)chosen=record.service?[record.service]:[];
  const serviceField=form.elements.service;const group=document.createElement('fieldset');group.className='visit-services';group.innerHTML=`<legend>Requested services <span class="required">*</span></legend><p>Select all services for this visit. Billing will carry these choices forward.</p>${[...new Set([...services,...chosen])].map(service=>`<label><input type="checkbox" value="${esc(service)}" ${chosen.includes(service)?'checked':''}> ${esc(service)}</label>`).join('')}`;
  serviceField.parentElement.replaceWith(group);
  const summary=document.createElement('input');summary.type='hidden';summary.name='service';form.append(summary);const selected=document.createElement('input');selected.type='hidden';selected.name='services';form.append(selected);
  const syncServices=()=>{const list=Array.from(group.querySelectorAll('input:checked')).map(input=>input.value);summary.value=list.join(', ');selected.value=JSON.stringify(list);const first=group.querySelector('input');if(first)first.setCustomValidity(list.length?'':'Select at least one service.');};group.onchange=syncServices;syncServices();
  const saveAndBill=document.createElement('button');saveAndBill.className='primary';saveAndBill.type='submit';saveAndBill.textContent='Save & continue to billing';form.querySelector('.modal-foot').append(saveAndBill);
  const originalSubmit=form.onsubmit;
  form.onsubmit=async event=>{
   const bill=event.submitter===saveAndBill;const before=new Set(rows('appointments').map(r=>r.id));const values=Object.fromEntries(new FormData(form));saveAndBill.disabled=true;
   await originalSubmit(event);saveAndBill.disabled=false;
   if(bill&&!$('#modal').open){const visit=id?state.records.find(r=>r.type==='appointments'&&r.id===id):rows('appointments').find(r=>!before.has(r.id)&&r.data.patient===values.patient&&r.data.date===values.date&&r.data.time===values.time);if(visit)billVisit(visit.id);}
  };
 }
};
const workflowPatientHistory=patientHistory;
patientHistory=function(id){
 workflowPatientHistory(id);
 const actions=document.createElement('div');actions.className='patient-care-actions';
 actions.innerHTML=`<button class="primary" onclick="startPatientCare('appointments',${id})">Book visit</button><button class="secondary" onclick="startPatientCare('consultations',${id})">Add consultation</button><button class="secondary" onclick="startPatientCare('vaccinations',${id})">Record vaccine</button><button class="secondary" onclick="startPatientCare('invoices',${id})">Create bill</button>`;
 document.querySelector('#modal .modal-heading').after(actions);
};
function startPatientCare(type,id){
 const pet=state.records.find(r=>r.type==='patients'&&r.id===id);if(!pet)return;
 if(type==='invoices'){
  const visit=rows('appointments').filter(r=>r.data.patient==id&&r.data.status!=='Cancelled'&&!rows('invoices').some(invoice=>invoice.data.appointment==r.id)).sort((a,b)=>(b.data.date+' '+b.data.time).localeCompare(a.data.date+' '+a.data.time))[0];
  if(visit){billVisit(visit.id);return;}
 }
 $('#modal').close();openForm(type);
 const form=document.querySelector(type==='invoices'?'#invoice-form':'#record-form');if(!form)return;
 if(type==='invoices'&&form.elements.owner){form.elements.owner.value=String(pet.data.owner);form.elements.owner.dispatchEvent(new Event('change'));}
 if(form.elements.patient)form.elements.patient.value=String(id);
}
function visitBillDraft(id){
 const visit=state.records.find(r=>r.type==='appointments'&&r.id===id);if(!visit)throw Error('Visit not found.');
 if(visit.data.status==='Cancelled')throw Error('Cancelled visits cannot be billed.');
 const pet=state.records.find(r=>r.type==='patients'&&r.id==visit.data.patient);if(!pet)throw Error('Patient not found.');
 let selected=[];try{selected=JSON.parse(visit.data.services||'[]');}catch{}if(!Array.isArray(selected)||!selected.length)selected=[visit.data.service];
 return {appointment:String(id),owner:String(pet.data.owner),patient:String(pet.id),date:today(),description:selected.join(', '),line_items:JSON.stringify(selected.map(service=>({service,quantity:1,price:''}))),initial:'0',takehome:'0'};
}
function billVisit(id){
 const existing=rows('invoices').find(r=>r.data.appointment==id);
 try{if(!existing)billingDraft=visitBillDraft(id);$('#modal').close();openForm('invoices',existing?.id||0);}catch(error){toast(error.message);}
}
const visitBillingTable=drawTable;
drawTable=function(){
 visitBillingTable();if(page!=='appointments')return;
 document.querySelectorAll('#table tbody tr').forEach(row=>{
  const edit=row.querySelector('button[onclick^="openForm"]');if(!edit)return;
  const id=Number(edit.getAttribute('onclick').match(/,(\d+)\)/)[1]);const visit=state.records.find(r=>r.type==='appointments'&&r.id===id);if(!visit||visit.data.status==='Cancelled')return;
  const button=document.createElement('button');button.textContent=rows('invoices').some(r=>r.data.appointment==id)?'View bill':'Create bill';button.onclick=()=>billVisit(id);row.querySelector('.actions').append(button);
 });
};
