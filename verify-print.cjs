const fs=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const dialog={innerHTML:'',classList:{add(){},remove(){}},showModal(){}};
const context=vm.createContext({console,document:{querySelector(){return dialog;}}});
vm.runInContext(fs.readFileSync('app.js','utf8').replace(/boot\(\);\s*$/,''),context);
vm.runInContext(fs.readFileSync('printing.js','utf8'),context);
vm.runInContext(`state={records:[
 {id:1,type:'owners',data:{name:'Owner <script>',phone:'123'}},
 {id:2,type:'patients',data:{name:'Test Pet',owner:'1',species:'Cat',service:'Grooming'}},
 {id:3,type:'consultations',data:{patient:'2',diagnosis:'Test diagnosis',treatment:'Medicine instructions',vet:'Test Vet'}},
 {id:4,type:'diagnostics',data:{patient:'2',results:'Test A | Positive\\nTest B | Negative'}},
 {id:5,type:'invoices',data:{patient:'2',owner:'1',charges:'Consultation | 500\\nMedicine | 100',takehome:'50',initial:'200'}}
]};`,context);
for(const [type,id,expected] of [['patients',2,'Patient Information'],['consultations',3,'Medicine instructions'],['diagnostics',4,'positive'],['invoices',5,'₱450.00']]){
 vm.runInContext(`printRecord('${type}',${id})`,context);
 assert.ok(dialog.innerHTML.includes(expected),type+' expected content');
 assert.ok(!dialog.innerHTML.includes('Owner <script>'),type+' escapes patient data');
 assert.ok(dialog.innerHTML.includes('PETCARE ANIMAL CLINIC'),type+' letterhead');
 assert.ok(dialog.innerHTML.includes('prepareClinicPrint()'),type+' print action');
}
console.log('Passed: four print layouts, linked owner/patient data, HTML escaping, billing balance, and print actions.');
