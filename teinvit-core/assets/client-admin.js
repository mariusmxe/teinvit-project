(function(){
function j(x){return document.querySelector(x)}
function el(tag,attrs){var n=document.createElement(tag);if(attrs)Object.keys(attrs).forEach(function(k){if(k==='text')n.textContent=attrs[k];else n.setAttribute(k,attrs[k])});return n}
async function api(url,method,data,nonce){const r=await fetch(url,{method:method||'GET',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce||''},body:data?JSON.stringify(data):undefined});const j=await r.json();if(!r.ok)throw new Error(j.message||'error');return j}
if(window.TEINVIT_CLIENT_ADMIN){
 const C=window.TEINVIT_CLIENT_ADMIN;
 const versions=(C.versions||[]).map(v=>parseInt(v.version,10));
 const sel=j('#teinvit-active-version');
 versions.forEach(function(v){const o=el('option',{value:v,text:'v'+v});if(v===parseInt(C.settings.active_version,10))o.selected=true;sel.appendChild(o)});
 function refreshCounter(){const rem=parseInt(C.remaining,10)||0;const c=j('#teinvit-edits-counter');const save=j('#teinvit-save-version');const buy=j('#teinvit-buy-edits');if(rem===2)c.textContent='2 modificări gratuite';else if(rem===1)c.textContent='1 modificare gratuită';else c.textContent='0 modificări';save.style.display=rem>0?'inline-block':'none';buy.style.display=rem===0?'inline-block':'none'}
 refreshCounter();
 j('#teinvit-set-active').addEventListener('click',async function(){await api(C.rest+'/client-admin/'+C.token+'/set-active-version','POST',{version:parseInt(sel.value,10)},C.nonce);alert('Setat')});
 j('#teinvit-save-version').addEventListener('click',async function(){
   const fields={};document.querySelectorAll('[name^="wapf[field_"]').forEach(function(i){fields[i.name.replace('wapf[field_','').replace(']','')]=i.value});
   const inv=window.TEINVIT_INVITATION_DATA||{};
   const res=await api(C.rest+'/client-admin/'+C.token+'/save-version','POST',{invitation:inv,wapf_fields:fields},C.nonce);
   C.remaining=Math.max(0,(parseInt(C.remaining,10)||0)-1);refreshCounter();
   const o=el('option',{value:res.version,text:'v'+res.version});sel.appendChild(o);sel.value=res.version;
   alert('Versiune salvată');
 });
 const flagDefs=[['civil','Confirmare prezenta Cununie civilă'],['religious','Confirmare prezenta Ceremonie religioasă'],['party','Confirmare prezenta Petrecere'],['kids','Număr copii'],['lodging','Solicitare cazare'],['vegetarian','Meniu vegetarian'],['allergies','Alergeni'],['gifts_enabled','Doresc afișarea listei de cadouri']];
 const flagsBox=j('#teinvit-rsvp-flags');
 flagDefs.forEach(function(fd){const l=el('label');const i=el('input',{type:'checkbox','data-flag':fd[0]});if(C.settings.rsvp_flags&&C.settings.rsvp_flags[fd[0]])i.checked=true;l.appendChild(i);l.appendChild(document.createTextNode(' '+fd[1]));flagsBox.appendChild(l)});
 j('#teinvit-save-flags').addEventListener('click',async function(){const flags={};flagsBox.querySelectorAll('[data-flag]').forEach(function(i){flags[i.dataset.flag]=i.checked});await api(C.rest+'/client-admin/'+C.token+'/flags','POST',{flags:flags},C.nonce);j('#teinvit-gifts-section').style.display=flags.gifts_enabled?'block':'none';alert('Salvat')});
 function renderGifts(){const list=j('#teinvit-gifts-list');list.innerHTML='';const gifts=C.gifts||[];gifts.forEach(function(g){const row=el('div',{class:'gift-row'});row.appendChild(el('input',{type:'text','data-k':'title',value:g.title||'',placeholder:'Denumire produs'}));row.appendChild(el('input',{type:'text','data-k':'url',value:g.url||'',placeholder:'Link produs'}));row.appendChild(el('input',{type:'text','data-k':'delivery_address',value:g.delivery_address||'',placeholder:'Adresă livrare'}));list.appendChild(row)});
 j('#teinvit-gifts-counter').textContent='Ai folosit '+gifts.length+' din '+(parseInt(C.capacity,10)||0)+' cadouri';
 j('#teinvit-add-gift').style.display=gifts.length<(parseInt(C.capacity,10)||0)?'inline-block':'none';
 j('#teinvit-buy-gifts').style.display=gifts.length>=(parseInt(C.capacity,10)||0)?'inline-block':'none';
 }
 renderGifts();j('#teinvit-gifts-section').style.display=(C.settings.rsvp_flags&&C.settings.rsvp_flags.gifts_enabled)?'block':'none';
 j('#teinvit-add-gift').addEventListener('click',function(){C.gifts=C.gifts||[];if(C.gifts.length>=(parseInt(C.capacity,10)||0))return;C.gifts.push({title:'',url:'',delivery_address:''});renderGifts()});
 j('#teinvit-save-gifts').addEventListener('click',async function(){const gifts=[];document.querySelectorAll('#teinvit-gifts-list .gift-row').forEach(function(r){gifts.push({title:r.children[0].value,url:r.children[1].value,delivery_address:r.children[2].value})});await api(C.rest+'/client-admin/'+C.token+'/gifts','POST',{gifts:gifts},C.nonce);C.gifts=gifts;renderGifts();alert('Cadouri salvate')});
 api(C.rest+'/client-admin/'+C.token+'/rsvp','GET',null,C.nonce).then(function(rows){const box=j('#teinvit-rsvp-report');if(!rows.length){box.textContent='Nu există RSVP-uri.';return;}const t=el('table');const h=el('tr');['Nume invitat','Prenume invitat','Telefon invitat','Câte persoane confirmă'].forEach(function(c){h.appendChild(el('th',{text:c}))});t.appendChild(h);rows.forEach(function(r){const tr=el('tr');[r.guest_last_name,r.guest_first_name,r.phone,r.attendees_count].forEach(function(v){tr.appendChild(el('td',{text:String(v||'')}))});t.appendChild(tr)});box.appendChild(t)});
}
if(window.TEINVIT_GUEST){
 const G=window.TEINVIT_GUEST;const root=j('#teinvit-guest-rsvp');if(!root)return;
 const f=el('form',{class:'teinvit-guest-rsvp-form'});['guest_last_name','guest_first_name','phone','attendees_count'].forEach(function(k){const i=el('input',{name:k,placeholder:k,type:k==='attendees_count'?'number':'text'});if(k==='attendees_count')i.value='1';f.appendChild(i)});
 const fields={};['civil','religious','party','kids','lodging','vegetarian','allergies'].forEach(function(k){if(!G.flags[k])return;const c=el('label');const i=el('input',{type:'checkbox',name:k});c.appendChild(i);c.appendChild(document.createTextNode(' '+k));f.appendChild(c);fields[k]=i});
 const submit=el('button',{type:'submit',text:'Trimite RSVP'});f.appendChild(submit);root.appendChild(f);
 let giftChecks=[];if(G.flags.gifts_enabled){const gbox=el('div',{class:'teinvit-guest-gifts'});(G.gifts||[]).forEach(function(g){const c=el('label');const i=el('input',{type:'checkbox',value:g.id});if((G.bookedGiftIds||[]).map(Number).includes(Number(g.id)))i.disabled=true;c.appendChild(i);c.appendChild(document.createTextNode(' '+g.title));gbox.appendChild(c);giftChecks.push(i)});root.appendChild(gbox)}
 f.addEventListener('submit',async function(e){e.preventDefault();const p={guest_last_name:f.querySelector('[name=guest_last_name]').value,guest_first_name:f.querySelector('[name=guest_first_name]').value,phone:f.querySelector('[name=phone]').value,attendees_count:parseInt(f.querySelector('[name=attendees_count]').value,10)||1,fields:{},gift_ids:giftChecks.filter(i=>i.checked&&!i.disabled).map(i=>parseInt(i.value,10))};Object.keys(fields).forEach(function(k){p.fields[k]=fields[k].checked});await api(G.rest+'/invite/'+G.token+'/rsvp','POST',p,'');alert('Mulțumim!');location.reload()});
}
})();
