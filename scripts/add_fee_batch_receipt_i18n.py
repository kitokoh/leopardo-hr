#!/usr/bin/env python3
"""Ajoute les clés i18n #9793 (reçu PDF de lot + lot depuis un élève).

Écrit dans la SOURCE DE VÉRITÉ `shared/i18n/locales/*.json` ; les catalogues
générés (web, admin, mobile, backend) sont ensuite resynchronisés par les
scripts `shared/i18n/sync/*.js` — jamais édités à la main.
"""
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[1]
LOCALES = ROOT / "shared" / "i18n" / "locales"

KEYS = {
    "fr": {
        "batchReceipt": "Télécharger le reçu (PDF)",
        "batchStudentBanner": "Élève {student} — {charges} facturation(s) à encaisser.",
        "batchFromStudent": "Encaisser en lot pour cet élève",
    },
    "en": {
        "batchReceipt": "Download receipt (PDF)",
        "batchStudentBanner": "Student {student} — {charges} charge(s) to collect.",
        "batchFromStudent": "Collect in batch for this student",
    },
    "ar": {
        "batchReceipt": "تحميل الوصل (PDF)",
        "batchStudentBanner": "التلميذ {student} — {charges} فاتورة للتحصيل.",
        "batchFromStudent": "تحصيل جماعي لهذا التلميذ",
    },
    "tr": {
        "batchReceipt": "Makbuzu indir (PDF)",
        "batchStudentBanner": "Öğrenci {student} — {charges} tahsil edilecek borç.",
        "batchFromStudent": "Bu öğrenci için toplu tahsilat",
    },
}


def main() -> None:
    for locale, additions in KEYS.items():
        path = LOCALES / f"{locale}.json"
        data = json.loads(path.read_text(encoding="utf-8"))
        fee_charges = data.setdefault("edu", {}).setdefault("feeCharges", {})
        for key, value in additions.items():
            fee_charges[key] = value
        path.write_text(
            json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
        print(f"{locale}: {len(additions)} clés ajoutées")


if __name__ == "__main__":
    main()
