#!/usr/bin/env python3
"""Issue #9699 — garde CI « un catch (Throwable) n'absorbe pas une ValidationException ».

## Pourquoi cette garde existe

Le meme defaut a ete livre deux fois :

  - #9687, cote API : `App\\Core\\Auth\\Interfaces\\Api\\V1\\Controllers\\PlatformAuthController`
  - #9698, cote web : `App\\Http\\Controllers\\Web\\PlatformAuthController`

Dans les deux cas, un `catch (\\Throwable)` (garde #6974, tracage des 500
inexpliques) enveloppait un appel qui finissait par executer
`$request->validate()`. Or `Illuminate\\Validation\\ValidationException` herite
de `Throwable` : la validation etait absorbee, convertie en 500 / INTERNAL_ERROR,
et le handler global qui la traduit en 422 / redirection avec `errors` n'etait
jamais atteint. L'utilisateur ne savait pas quel champ corriger, et chaque
saisie invalide polluait les alertes 500.

C'est une **classe de bug**, pas un accident : rien n'empechait un nouveau
controleur de reintroduire le motif. Cette garde le rend impossible a livrer.

## Ce qu'elle refuse

Un `try` dont le corps contient un appel a `validate()` — **directement**, ou
**indirectement** via une methode de la meme classe (`$this->attemptLogin($request)`
ou `attemptLogin($request)`) — suivi d'un `catch (\\Throwable)` / `catch (\\Exception)`
qui ne relance pas `ValidationException`.

Le cas **indirect** est le vrai apport : c'est exactement la forme des deux bugs
livres. Un scan naif (regex sur le corps du `try`) ne les aurait jamais trouves,
puisque le `validate()` vivait dans `attemptLogin()`, une autre methode.

## Ce qu'elle accepte

  - un `catch` cible `ValidationException` (ou `ValidationException|HttpExceptionInterface`)
    place **avant** le `catch (\\Throwable)` : c'est le correctif de #9687 / #9698 ;
  - un `catch (\\Throwable)` qui relance l'exception (`throw $e;`) : le filet
    #6974 reste legitime quand il ne fait que liberer une ressource ;
  - un `catch` specifique (`QueryException`, `RuntimeException`, ...) : il
    n'attrape pas `ValidationException`.

## Usage

    python3 dev-hub/tools/check-validation-catch-guard.py            # verifie api/app
    python3 dev-hub/tools/check-validation-catch-guard.py --verbose
    python3 dev-hub/tools/check-validation-catch-guard.py --self-test

Code de sortie 0 = conforme, 1 = violation, 2 = erreur d'usage.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_SCAN_DIR = REPO_ROOT / "api" / "app"

# Un catch qui attrape TOUT (donc aussi ValidationException).
BROAD_CATCH = re.compile(r"^(?:\\?)(Throwable|Exception)(?:\s+\$\w+)?$")

# Un appel de validation qui LEVE ValidationException.
#
# `Validator::make(...)` seul ne leve rien : il faut `->validate()` pour que
# l'exception soit jetee (`->fails()` renvoie un booleen). On ne cible donc que
# les methodes qui levent — sinon on signalerait a tort les imports CSV qui
# valident ligne par ligne avec `Validator::make(...)->fails()`.
VALIDATE_CALL = re.compile(r"->validate\s*\(|->validateWithBag\s*\(")

# Un catch qui traite explicitement ValidationException (donc ne l'absorbe pas).
VALIDATION_CATCH = re.compile(r"ValidationException")

# Une relance de l'exception capturee.
RETHROW = re.compile(r"\bthrow\s+\$\w+\s*;")


def _match_brace(src: str, open_idx: int) -> int:
    """Index de l'accolade fermante correspondant a `src[open_idx] == '{'`."""
    depth = 0
    i = open_idx
    while i < len(src):
        ch = src[i]
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return len(src) - 1


def _strip_php_comments(src: str) -> str:
    """Neutralise les commentaires pour ne pas detecter du code commente.

    On preserve les sauts de ligne pour garder les numeros de ligne exacts.
    """
    out = []
    i = 0
    n = len(src)
    while i < n:
        two = src[i : i + 2]
        if two == "//" or two == "#" and src[i : i + 1] == "#":
            j = src.find("\n", i)
            if j == -1:
                j = n
            out.append(" " * (j - i))
            i = j
        elif two == "/*":
            j = src.find("*/", i + 2)
            j = n if j == -1 else j + 2
            out.append("".join(c if c == "\n" else " " for c in src[i:j]))
            i = j
        elif src[i] in "'\"":
            quote = src[i]
            j = i + 1
            while j < n:
                if src[j] == "\\":
                    j += 2
                    continue
                if src[j] == quote:
                    j += 1
                    break
                j += 1
            out.append(src[i:j])
            i = j
        else:
            out.append(src[i])
            i += 1
    return "".join(out)


def _methods(src: str) -> dict[str, str]:
    """nom de methode -> corps, pour resoudre les appels intra-classe."""
    found: dict[str, str] = {}
    pattern = re.compile(
        r"(?:public|private|protected)\s+(?:static\s+)?function\s+(\w+)\s*\([^)]*\)[^{;]*\{"
    )
    for m in pattern.finditer(src):
        body_start = m.end() - 1
        body_end = _match_brace(src, body_start)
        found[m.group(1)] = src[body_start + 1 : body_end]
    return found


def _try_blocks(src: str):
    """(offset, corps_du_try, [(type_de_catch, corps_du_catch), ...])."""
    for m in re.finditer(r"\btry\s*\{", src):
        body_start = m.end() - 1
        body_end = _match_brace(src, body_start)
        body = src[body_start + 1 : body_end]

        catches = []
        pos = body_end + 1
        while True:
            cm = re.match(r"\s*catch\s*\(\s*([^)]*)\)\s*\{", src[pos : pos + 600])
            if not cm:
                break
            ctype = cm.group(1).strip()
            cstart = pos + cm.end() - 1
            cend = _match_brace(src, cstart)
            catches.append((ctype, src[cstart + 1 : cend]))
            pos = cend + 1

        if catches:
            yield m.start(), body, catches


def analyse_source(src: str) -> list[tuple[int, str]]:
    """Retourne [(ligne, message)] des violations trouvees dans une source PHP."""
    clean = _strip_php_comments(src)
    methods = _methods(clean)
    violations: list[tuple[int, str]] = []

    for offset, body, catches in _try_blocks(clean):
        line = clean[:offset].count("\n") + 1

        # Le try contient-il une validation, directement ou via $this->methode() ?
        direct = bool(VALIDATE_CALL.search(body))
        via: list[str] = []
        if not direct:
            for called in set(re.findall(r"\$this->(\w+)\s*\(", body)):
                if called in methods and VALIDATE_CALL.search(methods[called]):
                    via.append(called)

        if not direct and not via:
            continue

        # Un catch cible ValidationException place AVANT le catch large suffit.
        guarded = False
        for ctype, cbody in catches:
            if VALIDATION_CATCH.search(ctype) or VALIDATION_CATCH.search(cbody):
                guarded = True
                break
            if not BROAD_CATCH.match(ctype.strip()):
                continue
            if RETHROW.search(cbody):
                # Le filet relance : il n'absorbe rien.
                guarded = True
                break
            where = "directement" if direct else f"via $this->{via[0]}()"
            violations.append(
                (
                    line,
                    f"catch ({ctype}) absorbe la ValidationException levee "
                    f"{where} : elle doit remonter au handler global "
                    f"(422 / redirection avec errors), pas devenir un 500",
                )
            )
            break

        if guarded:
            continue

    return violations


# ---------------------------------------------------------------------------
# Autotest : prouve que la garde attrape le motif, y compris la forme indirecte.
# ---------------------------------------------------------------------------

SELF_TEST_CASES: list[tuple[str, str, bool]] = [
    (
        "indirect (forme exacte du bug #9687/#9698)",
        """
        class C {
            public function login($request) {
                try {
                    return $this->attemptLogin($request);
                } catch (\\Throwable $e) {
                    Log::error('boom');
                    return 500;
                }
            }
            private function attemptLogin($request) {
                $v = $request->validate(['email' => 'required']);
                return 200;
            }
        }
        """,
        True,
    ),
    (
        "direct",
        """
        class C {
            public function store($request) {
                try {
                    $v = $request->validate(['a' => 'required']);
                } catch (\\Throwable $e) {
                    return 500;
                }
            }
        }
        """,
        True,
    ),
    (
        "catch (Exception) large",
        """
        class C {
            public function store($request) {
                try {
                    $v = $request->validate(['a' => 'required']);
                } catch (Exception $e) {
                    return 500;
                }
            }
        }
        """,
        True,
    ),
    (
        "correctif #9687/#9698 : catch cible avant le catch large",
        """
        class C {
            public function login($request) {
                try {
                    return $this->attemptLogin($request);
                } catch (ValidationException|HttpExceptionInterface $e) {
                    throw $e;
                } catch (\\Throwable $e) {
                    return 500;
                }
            }
            private function attemptLogin($request) {
                return $request->validate(['email' => 'required']);
            }
        }
        """,
        False,
    ),
    (
        "filet legitime : catch large qui relance",
        """
        class C {
            public function store($request) {
                try {
                    $v = $request->validate(['a' => 'required']);
                } catch (\\Throwable $e) {
                    $this->release();
                    throw $e;
                }
            }
        }
        """,
        False,
    ),
    (
        "catch specifique : n'attrape pas ValidationException",
        """
        class C {
            public function store($request) {
                try {
                    $v = $request->validate(['a' => 'required']);
                } catch (QueryException $e) {
                    return 500;
                }
            }
        }
        """,
        False,
    ),
    (
        "validate() commente : ne doit pas compter",
        """
        class C {
            public function store($request) {
                try {
                    // $request->validate(['a' => 'required']);
                    return 200;
                } catch (\\Throwable $e) {
                    return 500;
                }
            }
        }
        """,
        False,
    ),
]


def self_test() -> int:
    failures = 0
    for name, src, should_fail in SELF_TEST_CASES:
        found = analyse_source(src)
        ok = bool(found) == should_fail
        status = "OK  " if ok else "FAIL"
        print(f"  [{status}] {name} -> {len(found)} violation(s), attendu {'>=1' if should_fail else '0'}")
        if not ok:
            failures += 1
    if failures:
        print(f"\nAUTOTEST ECHEC — {failures} cas.", file=sys.stderr)
        return 1
    print(f"\nAUTOTEST OK — {len(SELF_TEST_CASES)} cas.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--verbose", action="store_true")
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--path", default=str(DEFAULT_SCAN_DIR))
    args = parser.parse_args()

    if args.self_test:
        return self_test()

    root = Path(args.path)
    if not root.is_dir():
        print(f"ERREUR — repertoire introuvable : {root}", file=sys.stderr)
        return 2

    files = sorted(root.rglob("*.php"))
    total_violations = 0
    checked = 0

    for path in files:
        try:
            src = path.read_text(encoding="utf-8", errors="replace")
        except OSError as exc:
            print(f"ERREUR — lecture impossible {path} : {exc}", file=sys.stderr)
            return 2
        if "catch" not in src:
            continue
        checked += 1
        for line, message in analyse_source(src):
            rel = path.relative_to(REPO_ROOT) if path.is_relative_to(REPO_ROOT) else path
            print(f"{rel}:{line}: {message}", file=sys.stderr)
            total_violations += 1

    if args.verbose:
        print(f"Fichiers analyses : {checked} / {len(files)}")

    if total_violations:
        print("", file=sys.stderr)
        print(
            f"ECHEC — {total_violations} catch large(s) absorbent une ValidationException.",
            file=sys.stderr,
        )
        print(
            "Correctif : ajouter un `catch (ValidationException|HttpExceptionInterface $e) { throw $e; }` "
            "AVANT le `catch (\\Throwable)`. Voir #9687 et #9698.",
            file=sys.stderr,
        )
        return 1

    print(
        f"OK — garde validation/catch : {checked} fichier(s) avec catch analyse(s), "
        f"aucune ValidationException absorbee."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
