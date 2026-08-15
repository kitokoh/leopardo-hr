## Plan technique
1. Audit ciblé des boutons des vues admin (grep des handlers vides / `console.log` / commentaires « TODO ») + suppression ou câblage.
2. Corriger le mojibake dans les templates (fichiers .vue concernés).
3. CrmPipelineView : passer l'id du lead dans la navigation.
4. LogsView : router si un endpoint `/admin/...logs` existe, sinon supprimer le fichier.
5. Lint + build. CHANGELOG.
