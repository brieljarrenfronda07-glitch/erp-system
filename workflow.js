// Register a pet once; start each visit or care record from its patient profile.
names.consultations='Consultation records';names.vaccinations='Vaccine history';
const workflowOpenForm=openForm;
openForm=function(type,id=0){
 workflowOpenForm(type,id);
 const form=document.querySelector('#record-form');if(!form)return;
 const descriptions={patients:'Register this pet once. Keep their identity and owner here; select requested services when booking a visit.',appointments:'Choose the requested service for this visit. Completing an appointment tracks attendance; record treatment or vaccination details in the care history.',vaccinations:'Record a vaccine that was administered and its next due date. This is the pet’s clinical vaccine history.',consultations:'Record findings, diagnosis, treatment, and prescription instructions for a consultation.'};
 if(descriptions[type]){const p=document.createElement('p');p.className='workflow-hint';p.textContent=descriptions[type];form.querySelector('.modal-heading').after(p);}
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
 $('#modal').close();openForm(type);
 const form=document.querySelector(type==='invoices'?'#invoice-form':'#record-form');if(!form)return;
 if(type==='invoices'&&form.elements.owner){form.elements.owner.value=String(pet.data.owner);form.elements.owner.dispatchEvent(new Event('change'));}
 if(form.elements.patient)form.elements.patient.value=String(id);
}
