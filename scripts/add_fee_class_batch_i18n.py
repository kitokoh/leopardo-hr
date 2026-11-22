#!/usr/bin/env python3
"""Ajoute les clés i18n #9792 (lot depuis une classe + filtres) aux 4 locales.

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
        "batchFromClass": "Encaisser en lot pour cette classe",
        "batchClassBanner": "Classe {class} — {students} élève(s), {charges} facturation(s) à encaisser.",
        "batchClearClass": "Revenir à toutes les facturations",
        "batchFilters": "Filtres",
        "batchFiltersHint": "Restreindre les facturations proposées avant de composer le lot.",
        "dueFrom": "Échéance à partir du",
        "dueTo": "Échéance jusqu'au",
        "paidWithMethod": "Déjà réglé par",
        "allMethods": "Tous les moyens",
    },
    "en": {
        "batchFromClass": "Collect in batch for this class",
        "batchClassBanner": "Class {class} — {students} student(s), {charges} charge(s) to collect.",
        "batchClearClass": "Back to all charges",
        "batchFilters": "Filters",
        "batchFiltersHint": "Narrow the charges offered before building the batch.",
        "dueFrom": "Due from",
        "dueTo": "Due until",
        "paidWithMethod": "Already paid by",
        "allMethods": "All methods",
    },
    "ar": {
        "batchFromClass": "تحصيل جماعي لهذا القسم",
        "batchClassBanner": "القسم {class} — {students} تلميذًا، {charges} فاتورة للتحصيل.",
        "batchClearClass": "العودة إلى كل الفواتير",
        "batchFilters": "عوامل التصفية",
        "batchFiltersHint": "تضييق الفواتير المقترحة قبل تكوين الدفعة.",
        "dueFrom": "الاستحقاق من",
        "dueTo": "الاستحقاق حتى",
        "paidWithMethod": "دُفع مسبقًا بـ",
        "allMethods": "كل الوسائل",
    },
    "tr": {
        "batchFromClass": "Bu sınıf için toplu tahsilat",
        "batchClassBanner": "Sınıf {class} — {students} öğrenci, {charges} tahsil edilecek borç.",
        "batchClearClass": "Tüm borçlara dön",
        "batchFilters": "Filtreler",
        "batchFiltersHint": "Toplu işlemi oluşturmadan önce önerilen borçları daraltın.",
        "dueFrom": "Vade başlangıcı",
        "dueTo": "Vade bitişi",
        "paidWithMethod": "Önceden ödenen yöntem",
        "allMethods": "Tüm yöntemler",
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
