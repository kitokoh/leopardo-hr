
(function(){
  var q = document.getElementById('q');
  var fCat = document.getElementById('f-cat');
  var fFmt = document.getElementById('f-format');
  var fSta = document.getElementById('f-status');
  var tbl = document.getElementById('tbl');
  var empty = document.getElementById('empty');
  if(!tbl) return;
  var rows = Array.prototype.slice.call(tbl.querySelectorAll('tbody tr'));
  function apply(){
    var term = (q && q.value || '').toLowerCase().trim();
    var cat = fCat && fCat.value || '';
    var fmt = fFmt && fFmt.value || '';
    var sta = fSta && fSta.value || '';
    var shown = 0;
    rows.forEach(function(tr){
      var ok = true;
      if(cat && tr.getAttribute('data-cat') !== cat) ok = false;
      if(fmt && tr.getAttribute('data-format') !== fmt) ok = false;
      if(sta && tr.getAttribute('data-status') !== sta) ok = false;
      if(term && tr.textContent.toLowerCase().indexOf(term) === -1) ok = false;
      tr.hidden = !ok;
      if(ok) shown++;
    });
    if(empty) empty.hidden = shown !== 0;
  }
  [q, fCat, fFmt, fSta].forEach(function(el){ if(el) el.addEventListener('input', apply); });
  apply();
})();

/* Indicateur de defilement des tableaux.
   Un tableau plus large que son conteneur cache des colonnes : sans indice,
   l'utilisateur croit que la donnee s'arrete la. On marque donc chaque
   conteneur qui defile vraiment, et on remesure au redimensionnement (le
   passage tablette -> paysage change la largeur disponible). */
(function(){
  function mark(){
    var wraps = document.querySelectorAll('.table-wrap');
    for(var i=0;i<wraps.length;i++){
      var w = wraps[i];
      var t = w.querySelector('table');
      if(!t) continue;
      if(t.scrollWidth > w.clientWidth + 1){ w.classList.add('is-scrollable'); }
      else { w.classList.remove('is-scrollable'); }
    }
  }
  mark();
  window.addEventListener('resize', mark);
  window.addEventListener('orientationchange', mark);
})();
