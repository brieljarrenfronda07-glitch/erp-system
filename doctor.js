const isDoctor=()=>state?.user.role==='doctor';
const doctorRender=render;
render=function(){
 if(isDoctor()&&['invoices','users','logs'].includes(page))page='dashboard';
 doctorRender();if(!isDoctor())return;
 document.querySelectorAll('.nav-item').forEach(button=>{if(['invoices','users','logs'].some(p=>button.getAttribute('onclick')?.includes("'"+p+"'")))button.remove();});
 const note=document.querySelector('.role-notice');if(note)note.textContent='Doctor workspace · Patient history, consultations, prescriptions, vaccinations, and diagnostic reports.';
 document.querySelectorAll('button[onclick]').forEach(button=>{const action=button.getAttribute('onclick');if(["openForm('invoices'","openForm('owners'","openForm('patients')"].some(prefix=>action.startsWith(prefix)))button.remove();});
};
const doctorDrawTable=drawTable;
drawTable=function(){doctorDrawTable();if(isDoctor()&&page==='owners'){
 document.querySelectorAll('#table .actions').forEach(cell=>cell.innerHTML='<span class="muted">View only</span>');
}};
const doctorOpenForm=openForm;
openForm=function(type,id=0){
 if(isDoctor()&&(['invoices','users','owners','inventory'].includes(type)||(type==='patients'&&!id)))return toast('This action is managed by staff or administrators.');
 doctorOpenForm(type,id);
 if(type==='users'){const role=document.querySelector('#record-form [name=role]');if(role&&!Array.from(role.options).some(o=>o.value==='doctor'))role.add(new Option('doctor','doctor'));const button=document.querySelector('#record-form .primary');if(button)button.textContent='Create account';}
 if(isDoctor()){
  const form=document.querySelector('#record-form');if(!form)return;
  if(form.elements.vet){form.elements.vet.value=state.user.name;if(['consultations','diagnostics'].includes(type))form.elements.vet.readOnly=true;}
  for(const button of form.querySelectorAll('button'))if(button.textContent.includes('continue to billing'))button.remove();
 }
};
const doctorHistory=patientHistory;
patientHistory=function(id){doctorHistory(id);if(isDoctor())document.querySelectorAll('.patient-care-actions button').forEach(button=>{if(button.getAttribute('onclick')?.includes("'invoices'"))button.remove();});};
const doctorBillVisit=billVisit;
billVisit=function(id){if(isDoctor())return toast('Billing is managed by staff or administrators.');doctorBillVisit(id);};
const doctorPrintRecord=printRecord;
printRecord=function(type,id){if(isDoctor()&&type==='invoices')return toast('Billing is managed by staff or administrators.');doctorPrintRecord(type,id);};
const doctorVisitTable=drawTable;
drawTable=function(){doctorVisitTable();if(isDoctor()&&page==='appointments')document.querySelectorAll('#table .actions button').forEach(button=>{if(['Create bill','View bill'].includes(button.textContent))button.remove();});};
