function prescribedMedicines(data){try{const list=JSON.parse(data.medicines||'[]');return Array.isArray(list)?list:[];}catch{return [];}}
function prescriptionDraft(id){
 const clinical=state.records.find(r=>r.type==='consultations'&&r.id==id);if(!clinical)throw Error('Prescription not found.');
 const pet=state.records.find(r=>r.type==='patients'&&r.id==clinical.data.patient);if(!pet)throw Error('Patient not found.');
 let draft={owner:String(pet.data.owner),patient:String(pet.id),date:today(),initial:'0',takehome:'0',line_items:JSON.stringify([{service:'Consultation',quantity:1,price:''}])};
 if(clinical.data.appointment)draft=visitBillDraft(Number(clinical.data.appointment));
 const references=JSON.parse(draft.prescriptions||'[]').map(Number);const items=invoiceItems(draft);if(!references.includes(Number(id)))items.push(...prescribedMedicines(clinical.data).map(m=>({service:'Medicine: '+m.name,quantity:Number(m.quantity),price:''})));
 return {...draft,diagnosis:clinical.data.diagnosis||'',prescriptions:JSON.stringify([...new Set([...references,Number(id)])]),line_items:JSON.stringify(items)};
}
function prescriptionInvoice(id){return rows('invoices').find(r=>{try{return JSON.parse(r.data.prescriptions||'[]').map(Number).includes(Number(id));}catch{return false;}});}
function billPrescription(id){
 if(isDoctor())return toast('Staff handle billing for your prescriptions.');
 try{const clinical=state.records.find(r=>r.type==='consultations'&&r.id==id);if(!clinical)throw Error('Prescription not found.');const linked=prescriptionInvoice(id);const visitBill=clinical.data.appointment?rows('invoices').find(r=>r.data.appointment==clinical.data.appointment):null;
 $('#modal').close();if(linked){openForm('invoices',linked.id);return;}if(visitBill){openForm('invoices',visitBill.id);$('#invoice-form').importPrescription(id);return;}
 billingDraft=prescriptionDraft(id);openForm('invoices');}catch(error){toast(error.message);}
}
const prescriptionOpenForm=openForm;
openForm=function(type,id=0){
 prescriptionOpenForm(type,id);if(type!=='consultations')return;
 const form=$('#record-form');if(!form)return;const data=state.records.find(r=>r.type===type&&r.id===id)?.data||{},canPrescribe=isAdmin()||isDoctor();
 const hidden=document.createElement('input');hidden.type='hidden';hidden.name='medicines';hidden.value=data.medicines||'[]';form.append(hidden);
 const visit=document.createElement('label');visit.className='full';visit.innerHTML='<span>Related visit (optional)</span><select name="appointment"></select>';form.querySelector('.form-grid').append(visit);
 const updateVisits=()=>{visit.querySelector('select').innerHTML='<option value="">Walk-in / no linked visit</option>'+rows('appointments').filter(r=>r.data.patient==form.elements.patient.value&&r.data.status!=='Cancelled').map(r=>`<option value="${r.id}" ${r.id==data.appointment?'selected':''}>${esc(r.data.date)} · ${esc(r.data.service)}</option>`).join('');};form.elements.patient.addEventListener('change',updateVisits);updateVisits();
 const section=document.createElement('section');section.className='prescription-editor';section.innerHTML=`<div class="billing-section-title"><h3>Prescribed medicines</h3>${canPrescribe?'<button type="button" class="secondary" id="add-prescribed">＋ Add medicine</button>':''}</div><p class="field-hint">The doctor enters medicine names, quantities to dispense, and directions. Staff receive these in billing and enter prices.</p><div id="prescription-items"></div>`;form.querySelector('.modal-foot').before(section);
 const sync=()=>{hidden.value=JSON.stringify(Array.from(section.querySelectorAll('.prescribed-item')).map(row=>({name:row.querySelector('[data-med=name]').value.trim(),quantity:Number(row.querySelector('[data-med=quantity]').value),instructions:row.querySelector('[data-med=instructions]').value.trim()})));};
 const add=(medicine={name:'',quantity:1,instructions:''})=>{const row=document.createElement('div');row.className='prescribed-item';row.innerHTML=`<label>Medicine name<input data-med="name" maxlength="180" required value="${esc(medicine.name)}" ${canPrescribe?'':'readonly'}></label><label>Quantity to dispense<input data-med="quantity" type="number" min="1" max="10000" step="1" required value="${esc(medicine.quantity)}" ${canPrescribe?'':'readonly'}></label><label class="full">Dosage / directions<textarea data-med="instructions" maxlength="2000" ${canPrescribe?'':'readonly'}>${esc(medicine.instructions)}</textarea></label>${canPrescribe?'<button type="button" class="secondary">Remove medicine</button>':''}`;if(canPrescribe){row.oninput=sync;row.querySelector('button').onclick=()=>{row.remove();sync();};}section.querySelector('#prescription-items').append(row);sync();};prescribedMedicines(data).forEach(add);if(canPrescribe)section.querySelector('#add-prescribed').onclick=()=>add();
};
const prescriptionDrawTable=drawTable;
drawTable=function(){prescriptionDrawTable();if(page!=='consultations'||isDoctor())return;document.querySelectorAll('#table tbody tr').forEach(row=>{const edit=row.querySelector('button[onclick^="openForm"]');if(!edit)return;const id=Number(edit.getAttribute('onclick').match(/,(\d+)\)/)[1]);const button=document.createElement('button');button.textContent=prescriptionInvoice(id)?'View medicine bill':'Bill prescription';button.onclick=()=>billPrescription(id);row.querySelector('.actions').append(button);});};
const medicineVisitDraft=visitBillDraft;
visitBillDraft=function(id){
 const draft=medicineVisitDraft(id),clinical=rows('consultations').filter(r=>r.data.appointment==id&&prescribedMedicines(r.data).length&&!prescriptionInvoice(r.id));
 const items=invoiceItems(draft);for(const record of clinical)items.push(...prescribedMedicines(record.data).map(m=>({service:'Medicine: '+m.name,quantity:Number(m.quantity),price:''})));
 return {...draft,prescriptions:JSON.stringify(clinical.map(r=>r.id)),diagnosis:clinical.map(r=>r.data.diagnosis).filter(Boolean).join('; '),line_items:JSON.stringify(items)};
};
