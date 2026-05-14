# ADR 0003 - GitHub Actions comme source de verite

## Statut

Acceptee.

## Contexte

Le poste local Windows peut manquer de runtimes, de navigateurs ou de services Docker actifs. Les tests complets doivent tout de meme rester reproductibles avant merge.

## Decision

GitHub Actions est la source de verite pour merger vers `main`. Le local sert aux validations rapides et aux corrections ciblees ; le merge attend les checks requis verts sur PR.

## Consequences

Les PR doivent rester petites, avec une intention claire. En cas de rouge CI, on lit `gh run view --log-failed`, on corrige l'erreur exacte, puis on repousse. Les statuts externes non applicatifs deja documentes, comme certains echecs Vercel sans logs de build applicatif, ne bloquent pas si les GitHub Actions requis sont verts.

## Regles operationnelles

- Ne jamais pousser directement sur `main`.
- Une branche par sujet, avec changelog et scenarios si le comportement change.
- Ne pas masquer un test rouge par `continue-on-error` sans repropagation explicite.
