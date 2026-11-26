
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
