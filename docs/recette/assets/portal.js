
(function(){
  // --- Filtre du tableau des cas d'usage (accueil) ---
  var search = document.getElementById('uc-search');
  var typeSel = document.getElementById('uc-type');
  var statusSel = document.getElementById('uc-status');
  var table = document.getElementById('uc-table');
  var empty = document.getElementById('uc-empty');
  function applyFilter(){
    if(!table) return;
    var q = (search && search.value || '').toLowerCase().trim();
    var t = typeSel && typeSel.value || '';
    var s = statusSel && statusSel.value || '';
    var rows = table.querySelectorAll('tbody tr');
    var visible = 0;
    rows.forEach(function(tr){
      var text = tr.textContent.toLowerCase();
      var ok = (!q || text.indexOf(q) !== -1)
            && (!t || tr.getAttribute('data-type') === t)
            && (!s || tr.getAttribute('data-status') === s);
      tr.style.display = ok ? '' : 'none';
      if(ok) visible++;
    });
    if(empty) empty.hidden = visible !== 0;
  }
  if(search) search.addEventListener('input', applyFilter);
  if(typeSel) typeSel.addEventListener('change', applyFilter);
  if(statusSel) statusSel.addEventListener('change', applyFilter);

  // --- Cartes "type" : cliquer filtre le tableau ---
  document.querySelectorAll('[data-filter-type]').forEach(function(card){
    card.addEventListener('click', function(e){
      e.preventDefault();
      if(typeSel){ typeSel.value = card.getAttribute('data-filter-type'); applyFilter(); }
      var target = document.getElementById('tous');
      if(target) target.scrollIntoView({behavior:'smooth'});
    });
  });

  // --- Évaluation locale par cas d'usage ---
  var panel = document.querySelector('.eval-panel');
  if(!panel) return;
  var uc = panel.getAttribute('data-uc');
  var KEY = 'lhr-recette-eval-' + uc;
  var noteEl = panel.querySelector('.eval-note');
  var obsEl = panel.querySelector('.eval-obs');
  var statusEl = panel.querySelector('.eval-status');
  var saveBtn = panel.querySelector('.eval-save');
  var exportBtn = panel.querySelector('.eval-export');

  function load(){
    try{
      var raw = localStorage.getItem(KEY);
      if(!raw) return;
      var data = JSON.parse(raw);
      if(data.note != null) noteEl.value = data.note;
      if(data.obs != null) obsEl.value = data.obs;
      if(statusEl) statusEl.textContent = 'Évaluation locale chargée.';
    }catch(e){}
  }
  function save(){
    try{
      localStorage.setItem(KEY, JSON.stringify({note: noteEl.value, obs: obsEl.value, at: new Date().toISOString()}));
      if(statusEl) statusEl.textContent = 'Enregistré localement ✔';
    }catch(e){
      if(statusEl) statusEl.textContent = 'Stockage indisponible (navigation privée ?).';
    }
  }
  function exportAll(){
    var out = {};
    for(var i=0;i<localStorage.length;i++){
      var k = localStorage.key(i);
      if(k && k.indexOf('lhr-recette-eval-') === 0){
        try{ out[k.replace('lhr-recette-eval-','')] = JSON.parse(localStorage.getItem(k)); }catch(e){}
      }
    }
    var blob = new Blob([JSON.stringify(out, null, 2)], {type:'application/json'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'evaluations-recette-leopardo.json';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
  }
  if(saveBtn) saveBtn.addEventListener('click', save);
  if(exportBtn) exportBtn.addEventListener('click', exportAll);
  load();
})();
