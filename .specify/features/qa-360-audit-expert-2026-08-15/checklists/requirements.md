# Specification Quality Checklist: QA 360° Audit Expert 2026-08-15

## Coverage
- [x] Chaque manquement du registre a un ID, une sévérité, une surface, une preuve fichier:ligne
- [x] Chaque manquement NOUVEAU a une tâche T### correspondante
- [x] Dé-duplication effectuée contre les issues ouvertes + PRs récentes (protocole #2400)
- [x] Les constats DÉJÀ COUVERTS sont tracés sans nouvelle issue

## Spec quality
- [x] User stories avec scénarios d'acceptation testables
- [x] Critères de succès mesurables
- [x] Dépendances entre tâches explicitées (indépendantes → parallélisables)

## Tasks quality
- [x] Chaque tâche : ID, priorité, story, description avec chemin de fichier
- [x] Format `- [ ] T### [P#] [US#] ...` conforme au template
- [ ] Issues GitHub créées pour toutes les T### (à faire)
- [ ] Implémentation en PRs unitaires `Closes #<issue>` (à faire)
