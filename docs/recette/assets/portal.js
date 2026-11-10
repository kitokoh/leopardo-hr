
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

  // =========================================================================
  // NOTES HUMAINES — lecture du fichier versionné + écriture via l'API GitHub
  // =========================================================================
  // Le jeton personnel est saisi par l'utilisateur et conservé UNIQUEMENT dans
  // son navigateur (localStorage). Il n'est jamais écrit dans le dépôt ni dans
  // le code, et n'est envoyé qu'à api.github.com.
  var TOKEN_KEY = 'lhr-recette-gh-token';
  var AUTHOR_KEY = 'lhr-recette-gh-author';
  var NOTES_URL = document.body.getAttribute('data-notes-url') || 'notes-humaines.json';
  var notesCache = null;

  function b64encode(str){
    var bytes = new TextEncoder().encode(str);
    var bin = '';
    for(var i=0;i<bytes.length;i++) bin += String.fromCharCode(bytes[i]);
    return btoa(bin);
  }
  function b64decode(b64){
    var bin = atob(String(b64).replace(/\s/g,''));
    var bytes = new Uint8Array(bin.length);
    for(var i=0;i<bin.length;i++) bytes[i] = bin.charCodeAt(i);
    return new TextDecoder().decode(bytes);
  }
  function escHtml(s){
    return String(s).replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }
  function fetchNotes(){
    return fetch(NOTES_URL + '?t=' + Date.now(), {cache:'no-store'})
      .then(function(r){ if(!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function(j){ notesCache = j; return j; });
  }
  function paintHumanCells(notes){
    document.querySelectorAll('[data-human-note]').forEach(function(td){
      var n = (notes && notes[td.getAttribute('data-human-note')]) || {};
      var note = n.note_humaine;
      td.innerHTML = (note === null || note === undefined || note === '')
        ? '<span class="muted">—</span>'
        : '<span class="note-badge">' + escHtml(note) + '/5</span>';
    });
    document.querySelectorAll('[data-human-obs]').forEach(function(td){
      var n = (notes && notes[td.getAttribute('data-human-obs')]) || {};
      var obs = n.observation_humaine || '';
      var meta = '';
      if(n.auteur || n.date_modification){
        var bits = [];
        if(n.auteur) bits.push('par ' + escHtml(n.auteur));
        if(n.date_modification) bits.push('le ' + escHtml(String(n.date_modification).slice(0,10)));
        meta = '<div class="human-meta muted small">' + bits.join(' ') + '</div>';
      }
      td.innerHTML = (obs ? escHtml(obs)
        : '<span class="muted">Non renseignée — éditable ci-dessous.</span>') + meta;
    });
  }

  // Rafraîchit les cellules humaines sur TOUTES les pages (fiches + récapitulatif).
  // En cas d'échec (hors-ligne), le rendu serveur généré par build_portal.py reste affiché.
  fetchNotes().then(function(j){ paintHumanCells(j.notes || {}); }).catch(function(){});

  var panel = document.querySelector('.eval-panel');
  if(!panel) return;

  var uc = panel.getAttribute('data-uc');
  var REPO = panel.getAttribute('data-repo');
  var BRANCH = panel.getAttribute('data-branch');
  var PATH = panel.getAttribute('data-path');
  var noteEl = panel.querySelector('.eval-note');
  var obsEl = panel.querySelector('.eval-obs');
  var statusEl = panel.querySelector('.eval-status');
  var metaEl = panel.querySelector('.eval-meta');
  var saveBtn = panel.querySelector('.eval-save');
  var tokenEl = panel.querySelector('.gh-token');
  var authorEl = panel.querySelector('.gh-author');
  var tokenSave = panel.querySelector('.token-save');
  var tokenClear = panel.querySelector('.token-clear');
  var tokenStatus = panel.querySelector('.token-status');

  function getToken(){ try{ return localStorage.getItem(TOKEN_KEY) || ''; }catch(e){ return ''; } }
  function getAuthor(){ try{ return localStorage.getItem(AUTHOR_KEY) || ''; }catch(e){ return ''; } }
  function setStatus(msg, kind){
    if(!statusEl) return;
    statusEl.textContent = msg;
    statusEl.className = 'eval-status ' + (kind || 'muted');
  }

  function fillForm(notes){
    var n = (notes && notes[uc]) || {};
    if(n.note_humaine !== null && n.note_humaine !== undefined) noteEl.value = String(n.note_humaine);
    if(n.observation_humaine) obsEl.value = n.observation_humaine;
    if(metaEl){
      if(n.auteur || n.date_modification){
        var bits = [];
        if(n.auteur) bits.push('Dernière saisie par ' + n.auteur);
        if(n.date_modification) bits.push('le ' + String(n.date_modification).slice(0,10));
        metaEl.textContent = bits.join(' ');
      }else{
        metaEl.textContent = 'Aucune note humaine enregistrée pour ce cas.';
      }
    }
  }

  // --- Interface du jeton (stocké uniquement dans ce navigateur) ---
  if(tokenEl) tokenEl.value = getToken();
  if(authorEl) authorEl.value = getAuthor();
  if(tokenStatus) tokenStatus.textContent = getToken()
    ? 'Jeton configuré dans ce navigateur.'
    : 'Aucun jeton configuré.';
  if(tokenSave) tokenSave.addEventListener('click', function(){
    var t = (tokenEl.value || '').trim();
    if(!t){ if(tokenStatus) tokenStatus.textContent = 'Jeton vide — rien n\'a été enregistré.'; return; }
    try{
      localStorage.setItem(TOKEN_KEY, t);
      localStorage.setItem(AUTHOR_KEY, (authorEl.value || '').trim());
      if(tokenStatus) tokenStatus.textContent = 'Jeton enregistré dans ce navigateur ✔';
    }catch(e){
      if(tokenStatus) tokenStatus.textContent = 'Stockage indisponible (navigation privée ?).';
    }
  });
  if(tokenClear) tokenClear.addEventListener('click', function(){
    try{ localStorage.removeItem(TOKEN_KEY); localStorage.removeItem(AUTHOR_KEY); }catch(e){}
    if(tokenEl) tokenEl.value = '';
    if(authorEl) authorEl.value = '';
    if(tokenStatus) tokenStatus.textContent = 'Jeton effacé de ce navigateur.';
  });

  // --- API GitHub (contents) ---
  function ghHeaders(token){
    return {
      'Authorization': 'Bearer ' + token,
      'Accept': 'application/vnd.github+json',
      'X-GitHub-Api-Version': '2022-11-28'
    };
  }
  function ghGet(){
    return fetch('https://api.github.com/repos/' + REPO + '/contents/' + PATH + '?ref=' + BRANCH,
      {headers: ghHeaders(getToken()), cache: 'no-store'})
      .then(function(r){
        if(r.status === 401) throw {code:'AUTH', msg:'Jeton invalide ou expiré (401). Vérifiez votre jeton GitHub.'};
        if(r.status === 403) throw {code:'FORBIDDEN', msg:'Accès refusé (403) : le jeton n\'a pas le scope « repo » (ou « Contents: write »).'};
        if(r.status === 404) throw {code:'NOTFOUND', msg:'Fichier de notes introuvable (404) sur la branche ' + BRANCH + '.'};
        if(!r.ok) throw {code:'HTTP', msg:'Erreur GitHub ' + r.status + ' lors de la lecture du fichier.'};
        return r.json();
      });
  }
  function ghPut(contentStr, sha, message){
    return fetch('https://api.github.com/repos/' + REPO + '/contents/' + PATH, {
      method: 'PUT',
      headers: Object.assign({'Content-Type':'application/json'}, ghHeaders(getToken())),
      body: JSON.stringify({message: message, content: b64encode(contentStr), sha: sha, branch: BRANCH})
    }).then(function(r){
      if(r.status === 409) throw {code:'CONFLICT', msg:'Conflit de version (409) : le fichier a changé entre-temps.'};
      if(r.status === 401) throw {code:'AUTH', msg:'Jeton invalide ou expiré (401).'};
      if(r.status === 403) throw {code:'FORBIDDEN', msg:'Accès refusé (403) : scope « repo » / « Contents: write » requis.'};
      if(r.status === 422) throw {code:'VALIDATION', msg:'GitHub a refusé la mise à jour (422) — vérifiez le jeton et la branche.'};
      if(!r.ok) throw {code:'HTTP', msg:'Erreur GitHub ' + r.status + ' lors de l\'enregistrement.'};
      return r.json();
    });
  }

  // --- Enregistrement d'une note humaine dans le dépôt ---
  function saveNote(attempt){
    attempt = attempt || 0;
    if(!getToken()){
      setStatus('Aucun jeton GitHub configuré — ouvrez « Configurer mon jeton GitHub » ci-dessus.', 'warn');
      return;
    }
    if(!navigator.onLine){
      setStatus('Hors ligne : impossible d\'enregistrer dans le dépôt. Réessayez une fois reconnecté.', 'warn');
      return;
    }
    var noteVal = noteEl.value === '' ? null : parseInt(noteEl.value, 10);
    var obsVal = (obsEl.value || '').trim();
    var author = getAuthor() || 'testeur humain';
    setStatus('Enregistrement en cours…', 'muted');
    if(saveBtn) saveBtn.disabled = true;

    ghGet().then(function(file){
      var current;
      try{ current = JSON.parse(b64decode(file.content)); }catch(e){ current = {notes:{}}; }
      if(!current.notes) current.notes = {};
      if(!current.notes[uc]) current.notes[uc] = {};
      current.notes[uc].note_humaine = noteVal;
      current.notes[uc].observation_humaine = obsVal;
      current.notes[uc].auteur = author;
      current.notes[uc].date_modification = new Date().toISOString();
      if(current._meta) current._meta.updated_at = new Date().toISOString();
      var msg = 'docs(recette): note humaine ' + uc + ' (' +
                (noteVal === null ? '—' : noteVal + '/5') + ') par ' + author;
      return ghPut(JSON.stringify(current, null, 2) + '\n', file.sha, msg).then(function(){
        notesCache = current;
        paintHumanCells(current.notes);
        fillForm(current.notes);
        setStatus('Enregistré dans le dépôt ✔ (commit sur ' + BRANCH + ')', 'ok');
      });
    }).catch(function(err){
      if(err && err.code === 'CONFLICT' && attempt < 1){
        setStatus('Conflit détecté — relecture du fichier et nouvelle tentative…', 'warn');
        return saveNote(attempt + 1);
      }
      setStatus((err && err.msg) ? err.msg
        : 'Échec de l\'enregistrement (réseau ou API GitHub indisponible).', 'err');
    }).then(function(){ if(saveBtn) saveBtn.disabled = false; });
  }

  if(saveBtn) saveBtn.addEventListener('click', function(){ saveNote(0); });
  fetchNotes().then(function(j){ fillForm(j.notes || {}); }).catch(function(){ fillForm(null); });
})();
