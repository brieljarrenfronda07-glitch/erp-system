names.logs='Activity logs';icons.logs='≡';
let logOffset=0,logEvent='',logSearch='',logRequest=0,logDebounce;
const activityListPage=listPage;
listPage=function(){if(page!=='logs')return activityListPage();return `<div class="page-heading"><div><span class="eyebrow">ADMINISTRATION</span><h1>Activity logs</h1><p>Sign-ins, account creation, and clinic record changes. Times shown in your local timezone.</p></div><button class="secondary" onclick="loadActivity()">↻ Refresh logs</button></div><section class="panel"><div class="table-toolbar"><div class="search-wrap">⌕ <input id="log-search" aria-label="Search activity logs" placeholder="Search user, action, or record…" value="${esc(logSearch)}"></div><select id="log-event" aria-label="Filter activity by action"><option value="">All actions</option>${['Login','Login failed','Logout','Account created','Created','Updated','Deleted'].map(event=>`<option ${logEvent===event?'selected':''}>${event}</option>`).join('')}</select></div><div id="table" class="table-scroll"><p class="empty">Loading activity…</p></div></section><p class="muted" style="font-size:11px">Logs begin when this feature is deployed. Passwords and clinical notes are never copied into logs.</p>`;};
const activityDrawTable=drawTable;
drawTable=function(){if(page!=='logs')return activityDrawTable();loadActivity();};
const activityRender=render;
render=function(){if(page==='logs'&&!isAdmin())page='dashboard';activityRender();if(page==='logs'){
 $('#log-search').oninput=e=>{logSearch=e.target.value;logOffset=0;clearTimeout(logDebounce);logDebounce=setTimeout(loadActivity,250);};
 $('#log-event').onchange=e=>{logEvent=e.target.value;logOffset=0;loadActivity();};
}};
async function loadActivity(){
 if(page!=='logs'||!isAdmin())return;
 const request=++logRequest;
 try{
  const data=await api('logs&q='+encodeURIComponent(logSearch)+'&event='+encodeURIComponent(logEvent)+'&offset='+logOffset);
  if(page!=='logs'||request!==logRequest)return;
  $('#table').innerHTML=`<table><thead><tr><th>Date & time</th><th>User</th><th>Role</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>${data.logs.map(log=>`<tr><td>${esc(new Date(log.occurred_at).toLocaleString())}</td><td>${esc(log.actor_name)}</td><td>${esc(log.actor_role||'—')}</td><td>${badge(log.event)}</td><td>${esc(log.entity)}${log.record_id?' #'+esc(log.record_id):''}</td><td>${esc(log.details)}</td></tr>`).join('')||'<tr><td colspan="6" class="empty">No activity matches these filters.</td></tr>'}</tbody></table><div class="table-bottom"><span>${data.total?data.offset+1:0}–${Math.min(data.offset+data.logs.length,data.total)} of ${data.total} events</span><div><button class="secondary" ${data.offset===0?'disabled':''} onclick="activityPage(-1)">← Previous</button> <button class="secondary" ${data.offset+data.limit>=data.total?'disabled':''} onclick="activityPage(1)">Next →</button></div></div>`;
 }catch(error){if(page==='logs'&&request===logRequest)$('#table').innerHTML=`<p class="empty" role="alert">${esc(error.message)}</p>`;}
}
function activityPage(direction){logOffset=Math.max(0,logOffset+direction*50);loadActivity();}
