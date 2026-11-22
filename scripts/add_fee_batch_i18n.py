#!/usr/bin/env python3
"""Ajoute les clés i18n #9791 (encaissement en lot + exports CSV) aux 4 locales.

Écrit dans la SOURCE DE VÉRITÉ `shared/i18n/locales/*.json` ; les catalogues
générés (web, admin, mobile, backend) sont ensuite resynchronisés par les
scripts `shared/i18n/sync/*.js` — jamais édités à la main.
"""
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[1]
LOCALES = ROOT / "shared" / "i18n" / "locales"

BATCH = {
    "fr": {
        "batch": "Encaissement en lot",
        "batchTitle": "Encaisser plusieurs élèves",
        "batchSubtitle": "Une classe règle le même jour : saisissez une ligne par élève, tout est enregistré d'un coup.",
        "batchOpen": "Encaisser en lot",
        "batchAddLine": "Ajouter une ligne",
        "batchRemoveLine": "Retirer",
        "batchLines": "Lignes du lot",
        "batchEmpty": "Aucune ligne. Ajoutez au moins un élève à encaisser.",
        "batchTotal": "Total du lot",
        "batchSubmit": "Encaisser le lot",
        "batchSuccess": "Lot encaissé : {count} règlement(s) pour {total}.",
        "batchInvalid": "Le lot n'a pas été enregistré : corrigez les lignes signalées.",
        "batchLineError": "Ligne {line}",
        "batchSelectCharge": "Facturation",
        "batchDuplicate": "Cette facturation est déjà présente dans le lot.",
        "batchTooLarge": "Un lot ne peut pas dépasser 200 lignes.",
        "exportCsv": "Exporter en CSV",
        "exportError": "L'export a échoué. Réessayez.",
        "errors.EDU_FEE_BATCH_INVALID": "Le lot contient des lignes invalides.",
        "errors.EDU_FEE_BATCH_DUPLICATE": "Cette facturation apparaît deux fois dans le lot.",
        "errors.EDU_FEE_BATCH_EMPTY": "Le lot doit contenir au moins une ligne.",
        "errors.EDU_FEE_BATCH_TOO_LARGE": "Un lot ne peut pas dépasser 200 lignes.",
        "errors.EDU_FEE_CHARGE_NOT_FOUND": "Cette facturation est introuvable.",
    },
    "en": {
        "batch": "Batch collection",
        "batchTitle": "Collect for several students",
        "batchSubtitle": "A whole class pays on the same day: enter one line per student, everything is recorded at once.",
        "batchOpen": "Collect in batch",
        "batchAddLine": "Add a line",
        "batchRemoveLine": "Remove",
        "batchLines": "Batch lines",
        "batchEmpty": "No lines yet. Add at least one student to collect from.",
        "batchTotal": "Batch total",
        "batchSubmit": "Collect batch",
        "batchSuccess": "Batch collected: {count} payment(s) for {total}.",
        "batchInvalid": "The batch was not saved: fix the flagged lines.",
        "batchLineError": "Line {line}",
        "batchSelectCharge": "Charge",
        "batchDuplicate": "This charge is already in the batch.",
        "batchTooLarge": "A batch cannot exceed 200 lines.",
        "exportCsv": "Export as CSV",
        "exportError": "The export failed. Please try again.",
        "errors.EDU_FEE_BATCH_INVALID": "The batch contains invalid lines.",
        "errors.EDU_FEE_BATCH_DUPLICATE": "This charge appears twice in the batch.",
        "errors.EDU_FEE_BATCH_EMPTY": "The batch must contain at least one line.",
        "errors.EDU_FEE_BATCH_TOO_LARGE": "A batch cannot exceed 200 lines.",
        "errors.EDU_FEE_CHARGE_NOT_FOUND": "This charge could not be found.",
    },
    "ar": {
        "batch": "تحصيل بالجملة",
        "batchTitle": "تحصيل لعدة تلاميذ",
        "batchSubtitle": "قسم كامل يدفع في اليوم نفسه: أدخل سطراً لكل تلميذ، ويُسجَّل الكل دفعة واحدة.",
        "batchOpen": "تحصيل بالجملة",
        "batchAddLine": "إضافة سطر",
        "batchRemoveLine": "إزالة",
        "batchLines": "أسطر الدفعة",
        "batchEmpty": "لا توجد أسطر. أضف تلميذاً واحداً على الأقل.",
        "batchTotal": "مجموع الدفعة",
        "batchSubmit": "تحصيل الدفعة",
        "batchSuccess": "تم تحصيل الدفعة: {count} دفعة بقيمة {total}.",
        "batchInvalid": "لم تُسجَّل الدفعة: صحّح الأسطر المشار إليها.",
        "batchLineError": "السطر {line}",
        "batchSelectCharge": "الفاتورة",
        "batchDuplicate": "هذه الفاتورة موجودة مسبقاً في الدفعة.",
        "batchTooLarge": "لا يمكن أن تتجاوز الدفعة 200 سطر.",
        "exportCsv": "تصدير بصيغة CSV",
        "exportError": "فشل التصدير. أعد المحاولة.",
        "errors.EDU_FEE_BATCH_INVALID": "تحتوي الدفعة على أسطر غير صالحة.",
        "errors.EDU_FEE_BATCH_DUPLICATE": "تظهر هذه الفاتورة مرتين في الدفعة.",
        "errors.EDU_FEE_BATCH_EMPTY": "يجب أن تحتوي الدفعة على سطر واحد على الأقل.",
        "errors.EDU_FEE_BATCH_TOO_LARGE": "لا يمكن أن تتجاوز الدفعة 200 سطر.",
        "errors.EDU_FEE_CHARGE_NOT_FOUND": "تعذّر العثور على هذه الفاتورة.",
    },
    "tr": {
        "batch": "Toplu tahsilat",
        "batchTitle": "Birden fazla öğrenci için tahsilat",
        "batchSubtitle": "Bir sınıf aynı gün ödüyor: öğrenci başına bir satır girin, hepsi tek seferde kaydedilir.",
        "batchOpen": "Toplu tahsilat",
        "batchAddLine": "Satır ekle",
        "batchRemoveLine": "Kaldır",
        "batchLines": "Parti satırları",
        "batchEmpty": "Henüz satır yok. En az bir öğrenci ekleyin.",
        "batchTotal": "Parti toplamı",
        "batchSubmit": "Partiyi tahsil et",
        "batchSuccess": "Parti tahsil edildi: {total} tutarında {count} ödeme.",
        "batchInvalid": "Parti kaydedilmedi: işaretli satırları düzeltin.",
        "batchLineError": "Satır {line}",
        "batchSelectCharge": "Fatura",
        "batchDuplicate": "Bu fatura partide zaten var.",
        "batchTooLarge": "Bir parti 200 satırı aşamaz.",
        "exportCsv": "CSV olarak dışa aktar",
        "exportError": "Dışa aktarma başarısız oldu. Tekrar deneyin.",
        "errors.EDU_FEE_BATCH_INVALID": "Parti geçersiz satırlar içeriyor.",
        "errors.EDU_FEE_BATCH_DUPLICATE": "Bu fatura partide iki kez görünüyor.",
        "errors.EDU_FEE_BATCH_EMPTY": "Parti en az bir satır içermelidir.",
        "errors.EDU_FEE_BATCH_TOO_LARGE": "Bir parti 200 satırı aşamaz.",
        "errors.EDU_FEE_CHARGE_NOT_FOUND": "Bu fatura bulunamadı.",
    },
}

ACCOUNTING = {
    "fr": {"exportCsv": "Exporter en CSV", "exportError": "L'export a échoué. Réessayez."},
    "en": {"exportCsv": "Export as CSV", "exportError": "The export failed. Please try again."},
    "ar": {"exportCsv": "تصدير بصيغة CSV", "exportError": "فشل التصدير. أعد المحاولة."},
    "tr": {"exportCsv": "CSV olarak dışa aktar", "exportError": "Dışa aktarma başarısız oldu. Tekrar deneyin."},
}


def set_nested(target: dict, dotted: str, value) -> None:
    parts = dotted.split(".")
    node = target
    for part in parts[:-1]:
        node = node.setdefault(part, {})
    node[parts[-1]] = value


for locale, entries in BATCH.items():
    path = LOCALES / f"{locale}.json"
    data = json.loads(path.read_text(encoding="utf-8"))
    fee_charges = data["edu"]["feeCharges"]
    for key, value in entries.items():
        set_nested(fee_charges, key, value)
    for key, value in ACCOUNTING[locale].items():
        data["edu"]["accountingEntries"][key] = value
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"{locale}: ok")
