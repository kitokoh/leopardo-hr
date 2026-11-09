import 'package:flutter/material.dart';

import '../../core/theme/retail_theme.dart';

/// Etats d'interface partages du POS Retail (#9215).
///
/// Une caisse passe sa journee dans trois etats qui ne sont pas des cas
/// d'exception : **vide** (rien a vendre / panier vide), **hors ligne**, et
/// **en erreur**. Les traiter comme des cas normaux, avec un message utile et
/// une action, evite les ecrans muets qui font croire a une panne.

/// Etat vide : dit ce qui manque ET quoi faire ensuite.
class PosEmptyState extends StatelessWidget {
  const PosEmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 56, color: scheme.outline),
            const SizedBox(height: 16),
            Text(
              title,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 19,
                fontWeight: FontWeight.w700,
                color: scheme.onSurface,
              ),
            ),
            if (message != null) ...[
              const SizedBox(height: 8),
              Text(
                message!,
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 16, color: scheme.onSurfaceVariant),
              ),
            ],
            if (action != null) ...[
              const SizedBox(height: 20),
              action!,
            ],
          ],
        ),
      ),
    );
  }
}

/// Bandeau **hors ligne** : rassure au lieu d'alarmer.
///
/// Le message dit les trois choses qui comptent pour le vendeur : la vente
/// continue, rien n'est perdu, et combien d'operations attendent le reseau.
class PosOfflineBanner extends StatelessWidget {
  const PosOfflineBanner({
    super.key,
    required this.pendingOperations,
    this.onSync,
    this.syncing = false,
  });

  final int pendingOperations;
  final VoidCallback? onSync;
  final bool syncing;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    final message = pendingOperations == 0
        ? 'Hors ligne : le catalogue est en cache, la vente continue.'
        : 'Hors ligne : $pendingOperations operation(s) en attente. '
            'Rien n\'est perdu, la synchronisation se fera au retour du reseau.';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: scheme.tertiaryContainer,
      child: Row(
        children: [
          Icon(Icons.cloud_off, color: scheme.onTertiaryContainer),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: scheme.onTertiaryContainer,
              ),
            ),
          ),
          if (onSync != null && pendingOperations > 0)
            TextButton.icon(
              onPressed: syncing ? null : onSync,
              icon: syncing
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.sync),
              label: const Text('Synchroniser'),
            ),
        ],
      ),
    );
  }
}

/// Bandeau d'erreur : message lisible a distance + bouton de fermeture.
class PosErrorBanner extends StatelessWidget {
  const PosErrorBanner({super.key, required this.message, this.onDismiss});

  final String message;
  final VoidCallback? onDismiss;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      color: scheme.errorContainer,
      child: Row(
        children: [
          Icon(Icons.error_outline, color: scheme.onErrorContainer),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: scheme.onErrorContainer,
              ),
            ),
          ),
          if (onDismiss != null)
            IconButton(
              tooltip: 'Fermer',
              onPressed: onDismiss,
              icon: Icon(Icons.close, color: scheme.onErrorContainer),
            ),
        ],
      ),
    );
  }
}

/// Indicateur d'etat reseau compact, pour la barre de titre.
class PosConnectionChip extends StatelessWidget {
  const PosConnectionChip({super.key, required this.offline, this.pending = 0});

  final bool offline;
  final int pending;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final degraded = offline || pending > 0;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: degraded ? scheme.errorContainer : scheme.primaryContainer,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            degraded ? Icons.cloud_off : Icons.cloud_done,
            size: 18,
            color: degraded ? scheme.onErrorContainer : scheme.onPrimaryContainer,
          ),
          const SizedBox(width: 8),
          Text(
            degraded ? (pending > 0 ? 'Hors ligne ($pending)' : 'Hors ligne') : 'En ligne',
            style: TextStyle(
              fontWeight: FontWeight.w600,
              color: degraded ? scheme.onErrorContainer : scheme.onPrimaryContainer,
            ),
          ),
        ],
      ),
    );
  }
}

/// Squelette de chargement : garde la mise en page stable pendant l'attente.
class PosLoadingState extends StatelessWidget {
  const PosLoadingState({super.key, this.label = 'Chargement du catalogue...'});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const CircularProgressIndicator(),
          const SizedBox(height: 16),
          Text(label, style: const TextStyle(fontSize: 16)),
        ],
      ),
    );
  }
}

/// Carte de raccourci d'action rapide (barre d'actions de la caisse).
class PosQuickAction extends StatelessWidget {
  const PosQuickAction({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
    this.enabled = true,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Semantics(
      button: true,
      enabled: enabled,
      label: label,
      child: Material(
        color: enabled ? scheme.surfaceContainerHighest : scheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(RetailTheme.cardRadius),
        child: InkWell(
          onTap: enabled ? onTap : null,
          borderRadius: BorderRadius.circular(RetailTheme.cardRadius),
          child: Container(
            constraints: const BoxConstraints(minHeight: 64, minWidth: 96),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  icon,
                  size: 24,
                  color: enabled ? scheme.onSurface : scheme.outline,
                ),
                const SizedBox(height: 6),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: enabled ? scheme.onSurface : scheme.outline,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
