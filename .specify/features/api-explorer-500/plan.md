## Plan technique
1. Remplacer le littéral `` `<?php` `` (ligne ~354 de `api-explorer.blade.php`) par `<?= '<?php' ?>` afin que la chaîne soit émise comme sortie HTML/JS sans être parsée par PHP.
2. Scanner `api/resources/views/**` pour tout autre `<?php` littéral dans des blocs JS/HTML (fix si trouvé).
3. Renforcer `OpenApiDocsTest` : assertion `assertSee('<?php')` sur le rendu de `/api-explorer`.
4. Aucun changement de comportement API. CHANGELOG + AGENTS.md si leçon.
