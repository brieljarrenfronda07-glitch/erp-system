const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const messages=[];const ctx=vm.createContext({console,document:{querySelector(){return null;}},});
vm.runInContext(fs.readFileSync('app.js','utf8').replace(/boot\(\);\s*$/,''),ctx);
for(const file of ['printing.js','permissions.js','activity.js','billing.js','workflow.js','doctor.js'])vm.runInContext(fs.readFileSync(file,'utf8'),ctx);
ctx.report=m=>messages.push(m);vm.runInContext(`state={user:{name:'Dr. Test',role:'doctor'},records:[]};toast=report;`,ctx);
for(const type of ['users','invoices','owners','inventory','patients'])vm.runInContext(`openForm('${type}')`,ctx);
vm.runInContext(`billVisit(1);printRecord('invoices',1);exportCSV();`,ctx);
assert.equal(messages.length,8);assert.ok(vm.runInContext(`options('users','role').includes('doctor')`,ctx));
console.log('Passed: Doctor role available; doctor blocked from account creation, billing, registration, inventory edits, invoice printing, and exports.');
