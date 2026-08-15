# Implementation Plan: Mobile settings parsing

1. Remplacer le pattern `((response.data ?? {})['data'] as Map).cast<String, dynamic>()` par `extractDataMap(response.data)` dans les 3 repositories settings (6 sites).
2. Ajouter/étendre les tests unitaires repositories : cas enveloppe `data` présente + absente.
3. Vérifier que le garde CI mobile (analyse statique) couvre ces fichiers.
