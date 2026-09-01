import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_travel_agent/core/i18n/app_strings.dart';
import 'package:leopardo_travel_agent/core/providers/core_providers.dart';
import 'package:leopardo_travel_agent/features/travel/models/travel_cash_session.dart';
import 'package:leopardo_travel_agent/features/travel/providers/travel_providers.dart';

/// Caisse PDV (TRAVEL-810/#6100) — ouverture, session courante, clôture
/// avec écart calculé serveur (critère d'acceptation).
class PdvScreen extends ConsumerStatefulWidget {
  const PdvScreen({super.key});

  @override
  ConsumerState<PdvScreen> createState() => _PdvScreenState();
}

class _PdvScreenState extends ConsumerState<PdvScreen> {
  final _openingController = TextEditingController(text: '0');
  final _actualController = TextEditingController(text: '0');
  bool _busy = false;
  String? _error;
  String? _success;

  @override
  void dispose() {
    _openingController.dispose();
    _actualController.dispose();
    super.dispose();
  }

  Future<void> _open() async {
    final l10n = AppStrings.of(
      ref.read(appPreferencesProvider).preferredLanguage,
    );
    final repository = ref.read(travelRepositoryProvider);
    setState(() {
      _busy = true;
      _error = null;
      _success = null;
    });
    try {
      await repository.openCashSession(
        openingBalanceMinor: int.tryParse(_openingController.text.trim()) ?? 0,
      );
      ref.invalidate(pdvSessionProvider);
      setState(() {
        _busy = false;
        _success = l10n.t('sessionOpen');
      });
    } catch (_) {
      setState(() {
        _busy = false;
        _error = l10n.t('pdvError');
      });
    }
  }

  Future<void> _close() async {
    final l10n = AppStrings.of(
      ref.read(appPreferencesProvider).preferredLanguage,
    );
    final repository = ref.read(travelRepositoryProvider);
    setState(() {
      _busy = true;
      _error = null;
      _success = null;
    });
    try {
      await repository.closeCashSession(
        actualBalanceMinor: int.tryParse(_actualController.text.trim()) ?? 0,
      );
      ref.invalidate(pdvSessionProvider);
      setState(() {
        _busy = false;
        _success = l10n.t('sessionClosed');
      });
    } catch (_) {
      setState(() {
        _busy = false;
        _error = l10n.t('pdvError');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppStrings.of(
      ref.watch(appPreferencesProvider).preferredLanguage,
    );
    final sessionState = ref.watch(pdvSessionProvider);
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.t('pdvTitle')),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: l10n.t('refresh'),
            onPressed: () => ref.invalidate(pdvSessionProvider),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            sessionState.when(
              loading: () => const LinearProgressIndicator(),
              error: (e, _) => Text(
                l10n.t('loadError'),
                style: AppTypography.caption.copyWith(color: AppColors.danger),
              ),
              data: (session) => session == null
                  ? _buildOpenSection(l10n, text, muted)
                  : _buildSessionSection(l10n, text, muted, session),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(
                _error!,
                style: AppTypography.caption.copyWith(color: AppColors.danger),
              ),
            ],
            if (_success != null) ...[
              const SizedBox(height: 12),
              Text(
                _success!,
                style: AppTypography.caption.copyWith(
                  color: AppColors.success,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildOpenSection(AppStrings l10n, Color text, Color muted) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          l10n.t('noOpenSession'),
          style: AppTypography.body.copyWith(color: muted),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _openingController,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            labelText: l10n.t('openingBalance'),
            border: const OutlineInputBorder(),
            isDense: true,
          ),
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: _busy ? null : _open,
          icon: const Icon(Icons.lock_open_outlined),
          label: Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Text(l10n.t('openSession')),
          ),
        ),
      ],
    );
  }

  Widget _buildSessionSection(
    AppStrings l10n,
    Color text,
    Color muted,
    TravelCashSession session,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          l10n.t('currentSession'),
          style: AppTypography.subtitle.copyWith(color: text),
        ),
        const SizedBox(height: 8),
        _InfoTile(
          label: l10n.t('status'),
          value: session.isOpen
              ? l10n.t('sessionStatus_open')
              : l10n.t('sessionStatus_closed'),
        ),
        _InfoTile(
          label: l10n.t('openingBalance'),
          value: _minor(session.openingBalanceMinor),
        ),
        if (session.isOpen) ...[
          const SizedBox(height: 16),
          TextField(
            controller: _actualController,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(
              labelText: l10n.t('actualBalance'),
              border: const OutlineInputBorder(),
              isDense: true,
            ),
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: _busy ? null : _close,
            icon: const Icon(Icons.lock_outline),
            label: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(l10n.t('closeSession')),
            ),
          ),
        ] else ...[
          _InfoTile(
            label: l10n.t('expectedBalance'),
            value: _minor(session.expectedBalanceMinor),
          ),
          _InfoTile(
            label: l10n.t('actualBalance'),
            value: _minor(session.actualBalanceMinor),
          ),
          _InfoTile(
            label: l10n.t('difference'),
            value: _minor(session.differenceMinor),
            highlight: true,
          ),
        ],
      ],
    );
  }

  String _minor(int? minor) {
    return ((minor ?? 0) / 100).toStringAsFixed(2);
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.label,
    required this.value,
    this.highlight = false,
  });

  final String label;
  final String value;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final muted = AppColors.textSecondaryFor(context);
    final text = AppColors.textPrimaryFor(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          SizedBox(
            width: 140,
            child: Text(
              label,
              style: AppTypography.caption.copyWith(color: muted),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: AppTypography.body.copyWith(
                color: highlight ? AppColors.warning : text,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
