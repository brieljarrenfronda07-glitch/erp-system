// Print layouts use clinic records, never the photographed example's patient data or signature.
const billingParticulars=['Confinement / Admission / Boarding Fee','Injectable Medicines','Oral Medicines','Emergency Fee','Surgery Fee','Anesthesia','Supplies (syringe, underpad, bowl, cannula)','IV insertion (cannula / IV Set)','Pet Food','CBC/Plt','Serum Chemistry Test','Rapid Test Kit','Fecalysis','Earmites Test','Ultrasound','Urine Analysis','Veterinary Nursing Services','Professional Fee'];
function billingCategory(item){
 if([...billingParticulars,'Take Home Medicines','Other Services'].includes(item.category))return item.category;
 const name=String(item.service||'').toLowerCase();const exact=billingParticulars.find(label=>label.toLowerCase()===name);if(exact)return exact;
 if(name.startsWith('medicine:')||name.includes('medication'))return 'Oral Medicines';
 if(name.includes('confinement')||name.includes('admission')||name.includes('boarding'))return billingParticulars[0];
 if(name.includes('surgery'))return 'Surgery Fee';if(name.includes('consultation')||name.includes('wellness')||name.includes('professional'))return 'Professional Fee';
 return 'Other Services';
}
function statementCharges(data){
 let items=[];try{items=JSON.parse(data.line_items||'[]');}catch{}if(!Array.isArray(items)||!items.length)items=parseLines(data.charges).map(([service,amount])=>({service,quantity:1,price:Number(amount)}));
 if(!items.length)items=[{service:data.description||'Professional Fee',quantity:1,price:Math.max(0,Number(data.amount||0)-Number(data.takehome||0))}];
 const lines=items.map(item=>({...item,category:billingCategory(item),cents:Math.round(Number(item.price)*100)*Number(item.quantity)}));
 const medicineCategories=['Oral Medicines','Injectable Medicines','Take Home Medicines'];const extra=Math.round(Number(data.takehome||0)*100);const itemTotal=lines.reduce((sum,item)=>sum+item.cents,0),takehome=lines.filter(item=>item.category==='Take Home Medicines').reduce((sum,item)=>sum+item.cents,0)+extra;
 return {lines,total:itemTotal+extra,main:itemTotal+extra-takehome,takehome,withoutMedicines:itemTotal-lines.filter(item=>medicineCategories.includes(item.category)).reduce((sum,item)=>sum+item.cents,0)};
}
function billingStatement(data,patient,owner){
 const charges=statementCharges(data),paid=Math.round(Number(data.initial||(data.status==='Paid'?data.amount:0)||0)*100);
 const details=items=>items.map(item=>`<div class="statement-item">${esc(item.service)} — ${esc(item.quantity)} × ${money(item.price)} = ${money(item.cents/100)}</div>`).join('');
 const row=category=>{const entries=charges.lines.filter(item=>item.category===category);return `<tr><td>${category==='CBC/Plt'?'<b>Laboratory</b><br>':''}<b>${esc(category)}</b>${entries.length?details(entries):''}</td><td>${entries.length?money(entries.reduce((sum,item)=>sum+item.cents,0)/100):''}</td></tr>`;};
 const mainRows=billingParticulars.map(row).join('')+(charges.lines.some(item=>item.category==='Other Services')?row('Other Services'):'');
 return `<article class="paper billing-paper">${clinicHeader()}<div class="identity">${line('Pet Owner',owner.name)}${line('Billing Date',data.date)}${line('Pet’s Name',patient.name)}${line('Date Admitted',data.admitted)}</div>${line('Diagnosis',data.diagnosis)}<h2>BILLING STATEMENT</h2><table class="print-table"><thead><tr><th>Particulars</th><th>Actual Charges</th></tr></thead><tbody>${mainRows}<tr class="total"><th>Total</th><td>${money(charges.main/100)}</td></tr></tbody></table><table class="print-table takehome-table"><tbody><tr><td><b>Take Home Medicines</b>${details(charges.lines.filter(item=>item.category==='Take Home Medicines'))}${Number(data.takehome)>0?'<div class="statement-item">Additional take-home medicines: '+money(data.takehome)+'</div>':''}</td><td>${money(charges.takehome/100)}</td></tr></tbody></table><table class="print-table totals"><tbody><tr><th>Initial Payment</th><td>${money(paid/100)}</td></tr><tr><th>Grand Total (with meds)</th><td>${money(charges.total/100)}</td></tr><tr><th>Grand Total (without meds)</th><td>${money(charges.withoutMedicines/100)}</td></tr><tr class="total"><th>Remaining Balance</th><td>${money(Math.max(0,charges.total-paid)/100)}</td></tr></tbody></table><div class="billing-signatures"><div><b>Prepared by:</b><div class="sign-line">${esc(data.prepared)}</div>Name & Signature</div><div><b>Conforme:</b><div class="sign-line"></div>Name & Signature (owner/representative)<p>Contact No.: ${esc(owner.phone)}</p></div></div></article>`;
}
icons.diagnostics='⚕';names.diagnostics='Diagnostic reports';singular.diagnostics='diagnostic report';
fields.patients.push('color','temperature','registration_date');
fields.consultations.push('diet','recheck','license','ptr','s2');
fields.invoices.push('patient','admitted','diagnosis','charges','takehome','initial','prepared');
fields.diagnostics=['patient','date','tested','kit','lot','results','vet','license','ptr','s2'];
const baseDrawTable=drawTable;
drawTable=function(){
 if(page==='diagnostics'){
 const data=rows(page).filter(r=>(lookup(r.data.patient)+' '+Object.values(r.data).join(' ')).toLowerCase().includes(search.toLowerCase()));
 $('#table').innerHTML=`<table><thead><tr><th>Patient</th><th>Date</th><th>Test kit</th><th>Veterinarian</th><th>Actions</th></tr></thead><tbody>${data.map(r=>`<tr><td>${esc(lookup(r.data.patient))}</td><td>${esc(r.data.date)}</td><td>${esc(r.data.kit)}</td><td>${esc(r.data.vet)}</td><td class="actions"><button onclick="openForm('diagnostics',${r.id})">Edit</button><button onclick="printRecord('diagnostics',${r.id})">Print report</button>${state.user.role==='admin'?`<button class="delete" onclick="removeRecord('diagnostics',${r.id})">Delete</button>`:''}</td></tr>`).join('')||'<tr><td colspan="5" class="empty">No diagnostic reports yet.</td></tr>'}</tbody></table>`;
 }else{baseDrawTable();if(['patients','consultations','invoices'].includes(page))$('#table').querySelectorAll('tbody tr').forEach(tr=>{const edit=tr.querySelector('button[onclick^="openForm"]');if(!edit)return;const id=Number(edit.getAttribute('onclick').match(/,(\d+)\)/)[1]);const b=document.createElement('button');b.textContent=page==='patients'?'Print patient form':page==='consultations'?'Print prescription':'Print statement';b.onclick=()=>printRecord(page,id);tr.querySelector('.actions').append(b);});}
};
const baseOpenForm=openForm;
openForm=function(type,id=0){baseOpenForm(type,id);const form=$('#record-form');const help={charges:'One charge per line: Description | Amount. Example: Injectable medicines | 1600. Itemized charges plus take-home medicines determine the invoice amount.',results:'One result per line: Test name | Result. Example: Feline Giardia | Positive.',treatment:'Prescription instructions: enter medicine, dosage, frequency, duration, and any additional instructions.',license:'Veterinarian license number. Enter the signing veterinarian’s own credentials.',takehome:'Total charge for take-home medicines (₱).',initial:'Initial payment (₱).',tested:'Date and time the sample was tested.',registration_date:'Date printed on the patient information form.'};
 const fieldLabels={color:'Color / Markings',temperature:'Temperature (°C)',registration_date:'Registration date',recheck:'Recheck schedule',license:'License No.',ptr:'PTR No.',s2:'S2 No.',admitted:'Date admitted',charges:'Itemized charges',takehome:'Take-home medicines (₱)',initial:'Initial payment (₱)',prepared:'Prepared by',tested:'Time tested',kit:'Test kit used',lot:'Lot No.',results:'Test results'};
 Object.entries(fieldLabels).forEach(([key,text])=>{if(form.elements[key])form.elements[key].parentElement.firstChild.textContent=text+' ';});
 const required={patients:['name','species','owner'],consultations:['patient','date','diagnosis'],diagnostics:['patient','date','results'],invoices:['owner','description','amount','date']}[type];
 if(required)for(const input of form.querySelectorAll('input,select,textarea')){input.required=required.includes(input.name);input.parentElement.querySelector('.required')?.remove();if(input.required){const marker=document.createElement('span');marker.className='required';marker.textContent=' *';input.before(marker);}}
 if(type==='invoices'){
  const calculate=()=>{const lines=parseLines(form.elements.charges.value);form.elements.amount.readOnly=lines.length>0;if(lines.length&&lines.every(([,n])=>n!==''&&Number.isFinite(Number(n))&&Number(n)>=0))form.elements.amount.value=(lines.reduce((sum,[,n])=>sum+Number(n),0)+Number(form.elements.takehome.value||0)).toFixed(2);};
  form.elements.charges.addEventListener('input',calculate);form.elements.takehome.addEventListener('input',calculate);calculate();
 }
 Object.entries(help).forEach(([key,text])=>{const input=form.elements[key];if(input){const hint=document.createElement('small');hint.className='field-hint';hint.textContent=text;input.parentElement.append(hint);}});
 if(type==='diagnostics'){const intro=document.createElement('p');intro.textContent='Enter the tests and results for this patient. These will appear on the printed diagnostic report.';form.querySelector('.modal-heading').after(intro);}
};
const clinicHeader=()=>`<img class="clinic-watermark" src="assets/petcare-logo-transparent.png?v=2" alt="" aria-hidden="true"><header class="letterhead"><img class="letterhead-logo" src="assets/petcare-logo-transparent.png?v=2" alt="Petcare Animal Clinic logo"><h1>PETCARE ANIMAL CLINIC</h1><div>Motorboy Building, Poblacion, Libungan, Cotabato</div><div>Mon - Sun: 8:00 am - 6:00 pm</div><div>Phone No.: 0966 246 7701</div></header>`;
const line=(name,value)=>`<div class="print-field"><b>${esc(name)}:</b> <span>${esc(value||'')}</span></div>`;
const paragraphs=text=>esc(text||'').replace(/\n/g,'<br>');
function signatory(d){return `<div class="signatory"><div class="signature-space"></div><b class="signature-name">${esc(d.vet||'Veterinarian name & signature')}</b>${line('License No.',d.license)}${line('PTR No.',d.ptr)}${line('S2 No.',d.s2)}</div>`;}
function parseLines(text){return String(text||'').split(/\r?\n/).filter(l=>l.trim()).map(l=>{const i=l.lastIndexOf('|');return i<0?[l,'']:[l.slice(0,i).trim(),l.slice(i+1).trim()];});}
async function prepareClinicPrint(){
 const button=document.querySelector('.print-toolbar .primary');
 if(button)button.disabled=true;
 try{
  await Promise.all(Array.from(document.querySelectorAll('#print-document img')).map(img=>img.decode()));
  if(document.fonts)await document.fonts.ready;
  window.print();
 }catch(error){toast('The clinic logo could not load. Refresh the page and try again.');}
 finally{if(button)button.disabled=false;}
}
function printRecord(type,id){
 const record=state.records.find(r=>r.id===id&&r.type===type);if(!record)return toast('Record not found.');
 const d=record.data,patient=type==='patients'?d:state.records.find(r=>r.type==='patients'&&r.id==d.patient)?.data||{},owner=state.records.find(r=>r.type==='owners'&&r.id==(patient.owner||d.owner))?.data||{};
 const heading=clinicHeader();const identity=`<div class="identity">${line('Owner’s Name',owner.name)}${line('Date',d.date||d.registration_date||today())}${line('Pet’s Name',patient.name)}${line('Species',patient.species)}${line('Gender',patient.sex)}</div>`;
 let body,title;
 if(type==='patients'){
 title='Patient Information';body=`<article class="paper patient-paper">${heading}<h2>Patient Information</h2><div class="identity">${line('Date',d.registration_date||today())}${line('Owner’s Name',owner.name)}${line('Contact No.',owner.phone)}${line('Pet’s Name',d.name)}${line('Species',d.species)}${line('Breed',d.breed)}${line('Sex',d.sex)}${line('Birthdate',d.birthday)}${line('Color / Markings',d.color)}${line('Weight',d.weight?d.weight+' kg':'')}${line('Temperature',d.temperature?d.temperature+' °C':'')}</div></article>`;
 }else if(type==='consultations'){
 title='Prescription';body=`<article class="paper">${heading}${identity}<div class="rx-body"><span class="rx-symbol">℞</span><div><div class="diagnosis">${paragraphs(d.diagnosis)}</div><div class="medication">${paragraphs(d.treatment)}</div>${typeof prescribedMedicines==='function'?prescribedMedicines(d).map((m,i)=>`<p><b>${i+1}. ${esc(m.name)}</b> — Qty: ${esc(m.quantity)}<br>${paragraphs(m.instructions)}</p>`).join(''):''}${d.diet?`<p><b>Diet:</b> ${paragraphs(d.diet)}</p>`:''}<div class="instructions">${paragraphs(d.notes)}</div></div></div>${signatory(d)}<div class="recheck">${line('Recheck Schedule',d.recheck)}</div></article>`;
 }else if(type==='diagnostics'){
 title='Diagnostic Report';body=`<article class="paper">${heading}<h2>DIAGNOSTIC REPORT</h2>${identity}<div class="test-details">${line('Time Tested',d.tested)}${line('Test Kit Used',d.kit)}${line('Lot No.',d.lot)}</div><table class="print-table results"><thead><tr><th>Test</th><th>Result</th></tr></thead><tbody>${parseLines(d.results).map(([test,result])=>`<tr><td>${esc(test)}</td><td class="${result.toLowerCase()==='positive'?'positive':''}">${esc(result)}</td></tr>`).join('')}</tbody></table>${signatory(d)}</article>`;
 }else{
 title='Billing Statement';body=billingStatement(d,patient,owner);
 }
 const dialog=$('#modal');dialog.innerHTML=`<div class="print-toolbar"><div><b>${title}</b><small>Preview · A4 · Choose “Save as PDF” in the print dialog</small></div><button class="secondary" onclick="$('#modal').close()">Close</button><button class="primary" onclick="prepareClinicPrint()">Print / Save PDF</button></div><div id="print-document">${body}</div>`;dialog.classList.add('print-preview');dialog.onclose=()=>{dialog.classList.remove('print-preview');dialog.onclose=null;};dialog.showModal();
}
