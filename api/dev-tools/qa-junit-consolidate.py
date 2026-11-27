#!/usr/bin/env python3
"""Consolide les rapports JUnit par shard en UN rapport unique livrable.

Entrées  : api/storage/qa/junit/*.xml  (un XML par shard Feature terminé)
Sortie   : api/storage/qa/junit-feature-consolide.xml

Le rapport consolidé conserve, pour chaque shard, son <testsuite> d'origine
(nom, tests, failures, errors, time) et agrège les totaux dans un
<testsuites> racine. Les shards non terminés sont listés dans un
<testsuite name="SHARDS_NON_TERMINES"> avec le nombre de tests à 0, pour que
l'absence soit visible et non silencieuse.
"""
import glob
import xml.etree.ElementTree as ET
from pathlib import Path

# Chemin RELATIF au script : `/workspace/leopardo-hr/...` n'existe que dans le
# sandbox qui a produit la campagne. Sur un runner CI ou un poste de dev, le
# depot est ailleurs — le script echouait alors sur un dossier inexistant.
QA = Path(__file__).resolve().parent.parent / "storage" / "qa"
SRC = QA / "junit"
OUT = QA / "junit-feature-consolide.xml"

EXPECTED = 83  # shards Feature déclarés dans phpunit.xml

root = ET.Element("testsuites", name="Feature (consolidé)")
tot_t = tot_f = tot_e = tot_s = 0
done = []

for p in sorted(glob.glob(str(SRC / "*.xml"))):
    try:
        r = ET.parse(p).getroot()
    except Exception:  # noqa: BLE001
        continue
    name = Path(p).stem
    # le XML phpunit a un <testsuite> racine ; on le recopie tel quel
    suites = [r] if r.tag == "testsuite" else list(r.iter("testsuite"))
    if not suites:
        continue
    s = suites[0]
    t = int(s.get("tests") or 0)
    f = int(s.get("failures") or 0)
    e = int(s.get("errors") or 0)
    sk = int(s.get("skipped") or 0)
    tot_t += t
    tot_f += f
    tot_e += e
    tot_s += sk
    done.append(name)
    ET.SubElement(root, "testsuite", name=name, tests=str(t), failures=str(f),
                  errors=str(e), skipped=str(sk), time=s.get("time") or "0")

missing = EXPECTED - len(done)
if missing > 0:
    ET.SubElement(root, "testsuite", name="SHARDS_NON_TERMINES",
                  tests="0", failures="0", errors="0", skipped="0",
                  time="0", **{"nombre": str(missing)})

root.set("tests", str(tot_t))
root.set("failures", str(tot_f))
root.set("errors", str(tot_e))
root.set("skipped", str(tot_s))

ET.indent(root, space="  ")
ET.ElementTree(root).write(OUT, encoding="utf-8", xml_declaration=True)

print("shards consolidés : %d / %d" % (len(done), EXPECTED))
print("tests=%d failures=%d errors=%d skipped=%d" % (tot_t, tot_f, tot_e, tot_s))
print("écrit", OUT)
