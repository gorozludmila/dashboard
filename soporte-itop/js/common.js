const API = {
  async get(url, params={}) {
    const qs = new URLSearchParams();
    Object.entries(params).forEach(([k,v]) => { if(v!=='' && v!=null) qs.set(k,v); });
    const r = await fetch(url + (qs.toString()?`?${qs}`:''), {cache:'no-store'});
    const j = await r.json();
    if(!r.ok || !j.ok) throw new Error(j.error || `HTTP ${r.status}`);
    return j.data;
  }
};
function qs(id){return document.getElementById(id)}
function fmt(n){return Number(n||0).toLocaleString('es-AR')}
function objSeries(obj){return {labels:Object.keys(obj||{}),values:Object.values(obj||{})}}
function setStatus(msg, ok=true){const e=qs('estadoConexion');if(!e)return;e.textContent=msg;e.className='status '+(ok?'ok':'error')}
function filtrosActuales(){
  const ids=['desde','hasta','ministerio','organismo','tipo','estado','servicio','persona_tipo','persona']; const x={};
  ids.forEach(id=>{const e=qs(id);if(e)x[id]=e.value}); return x;
}
function fechasRapidas(){
  document.querySelectorAll('[data-periodo]').forEach(b=>b.addEventListener('click',()=>{
    const now=new Date(), desde=qs('desde'), hasta=qs('hasta'); const y=now.getFullYear(),m=now.getMonth(),d=now.getDate();
    let start;
    if(b.dataset.periodo==='semana'){const day=(now.getDay()+6)%7;start=new Date(y,m,d-day)}
    else if(b.dataset.periodo==='mes') start=new Date(y,m,1); else start=new Date(y,0,1);
    const iso=x=>x.toISOString().slice(0,10); desde.value=iso(start);hasta.value=iso(now);
    qs('btnFiltrar')?.click();
  }));
}
async function cargarOpciones(){
  const d=await API.get('api/filtros.php');
  const fill=(id,arr,label='Todos')=>{const e=qs(id);if(!e)return;const current=e.value;e.innerHTML=`<option value="">${label}</option>`+(arr||[]).map(v=>`<option>${escapeHtml(v)}</option>`).join('');e.value=current};
  fill('ministerio',d.ministerios);fill('organismo',d.organismos);fill('estado',d.estados);fill('servicio',d.servicios);
  const pt=qs('persona_tipo'),p=qs('persona');
  if(pt&&p){const rec=()=>{const arr=d.personas[pt.value||'reportado']||[];fill('persona',arr,'Todas')};pt.addEventListener('change',rec);rec();}
}
function escapeHtml(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]))}
function baseChartOptions(horizontal=false){return {responsive:true,maintainAspectRatio:false,indexAxis:horizontal?'y':'x',plugins:{legend:{position:'bottom'}},scales:{x:{beginAtZero:true,grid:{color:'rgba(0,0,0,.04)'}},y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.04)'}}}}}
function initCommon(){fechasRapidas();cargarOpciones().catch(e=>setStatus('No se pudieron cargar los filtros: '+e.message,false));qs('btnLimpiar')?.addEventListener('click',()=>{document.querySelectorAll('.toolbar input,.toolbar select').forEach(e=>e.value='');qs('btnFiltrar')?.click()});}
