#!/usr/bin/env python3
"""Libellés de moyen de paiement + titre de section du lot (#9791).

Le sélecteur affichait `cash`, `transfer`, `mobile_money` — des codes
techniques en minuscules, illisibles pour un caissier. Ces clés donnent un
libellé humain dans les 4 langues, utilisées par la page de facturation ET par
la page de lot (une seule source, donc aucun risque de divergence).
"""
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[1]
LOCALES = ROOT / "shared" / "i18n" / "locales"

KEYS = {
    "fr": {
        "batchSectionTitle": "Contexte du lot",
        "methodCash": "Espèces",
        "methodTransfer": "Virement",
        "methodCard": "Carte bancaire",
        "methodMobileMoney": "Paiement mobile",
        "methodOther": "Autre",
    },
    "en": {
        "batchSectionTitle": "Batch details",
        "methodCash": "Cash",
        "methodTransfer": "Bank transfer",
        "methodCard": "Card",
        "methodMobileMoney": "Mobile money",
        "methodOther": "Other",
    },
    "ar": {
        "batchSectionTitle": "بيانات الدفعة",
        "methodCash": "نقداً",
        "methodTransfer": "تحويل بنكي",
        "methodCard": "بطاقة بنكية",
        "methodMobileMoney": "دفع عبر الهاتف",
        "methodOther": "أخرى",
    },
    "tr": {
        "batchSectionTitle": "Parti bilgileri",
        "methodCash": "Nakit",
        "methodTransfer": "Banka havalesi",
        "methodCard": "Kredi kartı",
        "methodMobileMoney": "Mobil ödeme",
        "methodOther": "Diğer",
    },
}

for locale, entries in KEYS.items():
    path = LOCALES / f"{locale}.json"
    data = json.loads(path.read_text(encoding="utf-8"))
    data["edu"]["feeCharges"].update(entries)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"{locale}: ok")
