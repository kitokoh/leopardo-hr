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

Depuis #9706, elle refuse aussi un **FormRequest instancie DANS le `try`**
(`StoreXRequest::createFrom($request)` puis `->validateResolved()`, ou
`app(StoreXRequest::class)`) sous un `catch` large : la, la validation leve bien
a l'interieur du bloc.

## Ce qu'elle n'a PAS a refuser (mesure, pas intuition)

Un FormRequest **type-hinte sur la signature** de la methode n'est PAS un defaut,
meme avec un `catch (\\Throwable)` dans le corps. `FormRequestServiceProvider`
enregistre `afterResolving(ValidatesWhenResolved::class, ...)`, donc
`validateResolved()` s'execute **avant** l'entree dans la methode : le `try`
interne ne peut pas l'absorber.

Mesure sur `POST /api/v1/marketing/leads` (`StoreMarketingLeadRequest` type-hinte,
`catch (\\Throwable)` dans le corps) :

    $ curl -X POST .../api/v1/marketing/leads -d '{}'
    {"error":"VALIDATION_ERROR", ...}   HTTP=422

Un premier jet de cette garde signalait 7 faux positifs exactement pour cette
raison. La regle a ete corrigee apres la mesure, pas apres une relecture.

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


def _methods(src: str) -> dict[str, dict]:
    """nom de methode -> {signature, body, start, end}.

    La signature est conservee : c'est elle qui porte le type-hint d'un
    FormRequest (issue #9706), et les bornes permettent de retrouver la
    methode englobante d'un `try`.
    """
    found: dict[str, dict] = {}
    pattern = re.compile(
        r"(?:public|private|protected)\s+(?:static\s+)?function\s+(\w+)\s*\(([^)]*)\)[^{;]*\{"
    )
    for m in pattern.finditer(src):
        body_start = m.end() - 1
        body_end = _match_brace(src, body_start)
        found[m.group(1)] = {
            "signature": m.group(2),
            "body": src[body_start + 1 : body_end],
            "start": body_start,
            "end": body_end,
        }
    return found


def _enclosing_method(methods: dict[str, dict], offset: int) -> dict | None:
    """La methode dont le corps contient `offset`, s'il y en a une."""
    for info in methods.values():
        if info["start"] < offset < info["end"]:
            return info
    return None


# ---------------------------------------------------------------------------
# Issue #9706 — resolution des FormRequest.
#
# Un controleur type-hintant un FormRequest ne contient AUCUN `validate()` :
# la validation est declenchee par le conteneur dans
# `FormRequest::validateResolved()`, avant l'entree dans la methode. Le motif
# `try { ... } catch (\Throwable)` a donc exactement le defaut de #9687/#9698
# sans qu'aucun `->validate(` ne soit visible.
# ---------------------------------------------------------------------------

# Types scalaires / natifs : jamais des FormRequest.
NATIVE_TYPES = {
    "int", "float", "string", "bool", "array", "callable", "iterable",
    "object", "mixed", "void", "null", "self", "static", "false", "true",
    "never", "resource",
}

USE_STATEMENT = re.compile(r"^use\s+([\\\w]+)(?:\s+as\s+(\w+))?\s*;", re.M)
NAMESPACE_STATEMENT = re.compile(r"^namespace\s+([^;]+);", re.M)
CLASS_STATEMENT = re.compile(
    r"^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)(?:\s+extends\s+([\\\w]+))?",
    re.M,
)


def _use_map(src: str) -> dict[str, str]:
    """alias court -> FQCN, d'apres les `use` du fichier."""
    mapping: dict[str, str] = {}
    for m in USE_STATEMENT.finditer(src):
        fqcn = m.group(1)
        alias = m.group(2) or fqcn.rsplit("\\", 1)[-1]
        mapping[alias] = fqcn
    return mapping


def build_form_request_index(app_root: Path) -> dict[str, bool]:
    """FQCN -> True si la classe etend (transitivement) `FormRequest`.

    On resout les chaines d'heritage (`class X extends BaseRequest` ou
    `BaseRequest extends FormRequest`) pour ne pas dependre d'une convention
    de nommage.
    """
    parents: dict[str, str] = {}
    uses: dict[str, dict[str, str]] = {}

    for path in app_root.rglob("*.php"):
        try:
            src = path.read_text(encoding="utf-8", errors="replace")
        except OSError:
            continue
        if "class " not in src:
            continue

        ns_match = NAMESPACE_STATEMENT.search(src)
        namespace = ns_match.group(1).strip() if ns_match else ""
        cls_match = CLASS_STATEMENT.search(src)
        if not cls_match:
            continue

        short = cls_match.group(1)
        fqcn = f"{namespace}\\{short}" if namespace else short
        parents[fqcn] = cls_match.group(2) or ""
        uses[fqcn] = _use_map(src)

    resolved: dict[str, bool] = {}

    def is_form_request(fqcn: str, seen: frozenset[str] = frozenset()) -> bool:
        if fqcn in resolved:
            return resolved[fqcn]
        if fqcn in seen:
            return False  # cycle d'heritage : on ne boucle pas

        parent = parents.get(fqcn, "")
        if not parent:
            resolved[fqcn] = False
            return False

        parent_short = parent.rsplit("\\", 1)[-1]
        if parent_short == "FormRequest":
            resolved[fqcn] = True
            return True

        # Resolution du parent : `use` du fichier, sinon FQCN direct.
        parent_fqcn = uses.get(fqcn, {}).get(parent_short, parent)
        if parent_fqcn not in parents:
            # Parent hors `api/app` (vendor) : seul FormRequest compte.
            resolved[fqcn] = False
            return False

        result = is_form_request(parent_fqcn, seen | {fqcn})
        resolved[fqcn] = result
        return result

    for fqcn in parents:
        is_form_request(fqcn)

    return resolved


def _form_request_params(
    signature: str,
    use_map: dict[str, str],
    index: dict[str, bool],
) -> list[str]:
    """Parametres de la signature dont le type est un FormRequest."""
    hits: list[str] = []

    for m in re.finditer(r"([?\\A-Za-z0-9_|]+)\s+\$(\w+)", signature):
        for part in m.group(1).split("|"):
            part = part.strip().lstrip("?").strip()
            if not part or part.lower() in NATIVE_TYPES:
                continue
            fqcn = use_map.get(part, part)
            if index.get(fqcn) or index.get(part):
                hits.append(part)

    return hits


def _is_form_request_name(short: str, use_map: dict[str, str], index: dict[str, bool]) -> bool:
    """`short` designe-t-il un FormRequest ? (alias `use`, FQCN, ou nom court)."""
    fqcn = use_map.get(short, short)
    if index.get(fqcn) or index.get(short):
        return True

    # Repli sur le nom court : l'autotest et les extraits isoles n'ont pas de
    # `use`, et un FQCN peut etre importe sous un alias.
    suffix = "\\" + short
    return any(flag and name.endswith(suffix) for name, flag in index.items())


def _form_request_used_in_try(
    body: str,
    use_map: dict[str, str],
    index: dict[str, bool],
) -> list[str]:
    """FormRequest dont la validation est declenchee DANS le corps du `try`.

    Issue #9706 — distinction decisive, mesuree par appel HTTP reel :

      - un FormRequest **type-hinte sur la signature** est resolu par le
        conteneur via `afterResolving` (FormRequestServiceProvider), donc
        `validateResolved()` s'execute **AVANT** l'entree dans la methode. Un
        `try` interne ne peut pas l'absorber : l'appel renvoie bien 422.
        Verifie sur `POST /api/v1/marketing/leads` (FormRequest
        `StoreMarketingLeadRequest` + `catch (\\Throwable)` dans le corps) :
        corps vide -> **422**, pas 500. Ce n'est donc PAS un defaut.

      - un FormRequest instancie **dans** le `try` (`$req = StoreXRequest::createFrom(...)`
        puis `$req->validateResolved()`, ou `app(StoreXRequest::class)`) leve
        bien a l'interieur du `try` : la, le `catch` large l'absorbe.

    On ne signale donc que la seconde forme.
    """
    hits: list[str] = []

    # `XxxRequest::createFrom(...)`, `new XxxRequest`, `app(XxxRequest::class)`,
    # `resolve(XxxRequest::class)`.
    for m in re.finditer(
        r"(?:([A-Za-z0-9_]+)::createFrom\s*\(|new\s+([A-Za-z0-9_]+)\s*\(|"
        r"(?:app|resolve)\s*\(\s*([A-Za-z0-9_]+)::class)",
        body,
    ):
        short = next((g for g in m.groups() if g), None)
        if not short:
            continue
        if _is_form_request_name(short, use_map, index):
            hits.append(short)

    return hits


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


def analyse_source(
    src: str,
    form_request_index: dict[str, bool] | None = None,
) -> list[tuple[int, str]]:
    """Retourne [(ligne, message)] des violations trouvees dans une source PHP.

    `form_request_index` (issue #9706) est l'index FQCN -> est-un-FormRequest
    construit par `build_form_request_index()`. Sans lui, seule la forme
    `->validate()` est detectee (comportement de #9699).
    """
    clean = _strip_php_comments(src)
    methods = _methods(clean)
    use_map = _use_map(clean)
    violations: list[tuple[int, str]] = []

    for offset, body, catches in _try_blocks(clean):
        line = clean[:offset].count("\n") + 1

        # Le try contient-il une validation, directement ou via $this->methode() ?
        direct = bool(VALIDATE_CALL.search(body))
        via: list[str] = []
        if not direct:
            for called in set(re.findall(r"\$this->(\w+)\s*\(", body)):
                if called in methods and VALIDATE_CALL.search(methods[called]["body"]):
                    via.append(called)

        # Issue #9706 : un FormRequest instancie DANS le try leve bien a
        # l'interieur (contrairement au type-hint de signature, resolu avant
        # l'entree dans la methode — mesure HTTP : 422, pas 500).
        form_request_params: list[str] = []
        if not direct and not via and form_request_index:
            form_request_params = _form_request_used_in_try(body, use_map, form_request_index)

        if not direct and not via and not form_request_params:
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
            if direct:
                where = "directement"
            elif via:
                where = f"via $this->{via[0]}()"
            else:
                where = (
                    f"par le FormRequest {form_request_params[0]} instancie DANS le try "
                    f"(validateResolved leve a l'interieur du bloc)"
                )
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
    # ------------------------------------------------------------------
    # Issue #9706 — FormRequest.
    #
    # Distinction MESUREE par appel HTTP reel (POST /api/v1/marketing/leads,
    # FormRequest type-hinte + catch (\Throwable) dans le corps) : corps vide
    # -> 422, PAS 500. `validateResolved()` tourne dans `afterResolving`, donc
    # AVANT l'entree dans la methode : un try interne ne peut pas l'absorber.
    # Le type-hint de signature n'est donc PAS un defaut.
    # ------------------------------------------------------------------
    (
        "FormRequest type-hinte : PAS un defaut (resolu avant la methode)",
        """
        class C {
            public function store(StoreEmployeeRequest $request) {
                try {
                    $employee = $this->service->create($request->validated());
                } catch (\\Throwable $e) {
                    return 500;
                }
            }
        }
        """,
        False,
    ),
    (
        "FormRequest instancie DANS le try : catch large absorbe",
        """
        class C {
            public function store(Request $request) {
                try {
                    $form = StoreEmployeeRequest::createFrom($request);
                    $form->validateResolved();
                    $employee = $this->service->create($form->validated());
                } catch (\\Throwable $e) {
                    return 500;
                }
            }
        }
        """,
        True,
    ),
    (
        "FormRequest instancie DANS le try : catch cible accepte",
        """
        class C {
            public function store(Request $request) {
                try {
                    $form = StoreEmployeeRequest::createFrom($request);
                    $form->validateResolved();
                } catch (ValidationException|HttpExceptionInterface $e) {
                    throw $e;
                } catch (\\Throwable $e) {
                    return 500;
                }
            }
        }
        """,
        False,
    ),
    (
        "FormRequest instancie DANS le try : catch large qui relance accepte",
        """
        class C {
            public function store(Request $request) {
                try {
                    $form = StoreEmployeeRequest::createFrom($request);
                    $form->validateResolved();
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
        "type-hint non-FormRequest : ne doit pas compter",
        """
        class C {
            public function store(SomeService $service) {
                try {
                    $service->run();
                } catch (\\Throwable $e) {
                    return 500;
                }
            }
        }
        """,
        False,
    ),
]

# Index minimal pour l'autotest : seules les classes citees sont des FormRequest.
SELF_TEST_FORM_REQUESTS = {
    "App\\Http\\Requests\\StoreEmployeeRequest": True,
    "App\\Services\\SomeService": False,
}


def self_test() -> int:
    failures = 0
    for name, src, should_fail in SELF_TEST_CASES:
        found = analyse_source(src, SELF_TEST_FORM_REQUESTS)
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

    # Issue #9706 : index des FormRequest, construit une seule fois sur api/app.
    form_request_index = build_form_request_index(DEFAULT_SCAN_DIR)
    if args.verbose:
        form_requests = sum(1 for v in form_request_index.values() if v)
        print(f"FormRequest indexes : {form_requests}")

    for path in files:
        try:
            src = path.read_text(encoding="utf-8", errors="replace")
        except OSError as exc:
            print(f"ERREUR — lecture impossible {path} : {exc}", file=sys.stderr)
            return 2
        if "catch" not in src:
            continue
        checked += 1
        for line, message in analyse_source(src, form_request_index):
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
