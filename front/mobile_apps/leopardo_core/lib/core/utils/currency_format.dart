/// Formatage monétaire mobile — conventions partagées.
///
/// T086 (QA 2026-08-15) : la devise provient TOUJOURS du payload tenant
/// (summary.currency / employee.currency) — jamais de fallback codé en dur
/// (ex. 'DZD') qui afficherait une mauvaise devise aux entreprises FR/MA/SN.
library;

/// Suffixe d'affichage d'une devise : `' XOF'` si connue, chaîne vide sinon
/// (pas de symbole trompeur quand le backend n'a pas fourni la devise).
String currencySuffix(String currency) => currency.isEmpty ? '' : ' $currency';
