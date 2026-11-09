/// Ecran de **configuration de la tablette** (#9263), partage par les deux
/// caisses.
///
/// Une tablette livree sur site ne doit pas exiger de recompilation : le
/// gerant ouvre cet ecran, saisit l'URL de l'API et l'emplacement servi,
/// teste la connexion, et la caisse utilise ces valeurs des le prochain
/// demarrage.
///
/// L'acces est protege par un **PIN** : un vendeur ne doit pas pouvoir
/// detourner la caisse vers un autre serveur au milieu du service.
library;

import 'package:flutter/material.dart';

import 'pos_config.dart';

/// Callback de persistance : l'app decide ou (Hive) et comment.
typedef PosConfigSaver = Future<void> Function(PosAppConfig config);

/// Callback de test de connexion : l'app fournit le transport (Dio).
typedef PosHealthProbe = Future<PosHealthResult> Function(String apiBaseUrl);

/// Ecran de reglages de la tablette.
class PosSettingsScreen extends StatefulWidget {
  const PosSettingsScreen({
    super.key,
    required this.initialConfig,
    required this.onSave,
    required this.probe,
    this.locationLabel = 'Emplacement',
    this.pinHash,
    this.pinSalt = 'leopardo-pos',
    this.onPinChanged,
    this.printerName,
    this.onOpenPrinter,
  });

  /// Configuration courante (pre-remplit le formulaire).
  final PosAppConfig initialConfig;

  /// Persiste la configuration validee.
  final PosConfigSaver onSave;

  /// Teste la connexion au backend.
  final PosHealthProbe probe;

  /// Libelle du champ d'emplacement (« Emplacement » ou « Succursale »).
  final String locationLabel;

  /// Empreinte du PIN courant (null = PIN d'usine `0000`).
  final String? pinHash;

  /// Sel du PIN.
  final String pinSalt;

  /// Appele quand le PIN est change.
  final Future<void> Function(String newPin)? onPinChanged;

  /// Nom de l'imprimante actuellement configuree (null = aucune).
  final String? printerName;

  /// Ouvre l'ecran de choix de l'imprimante.
  ///
  /// L'acces existait uniquement depuis l'apercu du ticket : un gerant qui
  /// installe une tablette neuve devait ouvrir une vente fictive pour
  /// appairer l'imprimante. Il est ici, a sa place, derriere le meme PIN.
  final Future<void> Function()? onOpenPrinter;

  @override
  State<PosSettingsScreen> createState() => _PosSettingsScreenState();
}

class _PosSettingsScreenState extends State<PosSettingsScreen> {
  late final TextEditingController _urlController;
  late final TextEditingController _locationController;
  late final TextEditingController _pinController;

  bool _unlocked = false;
  String? _pinError;
  bool _testing = false;
  PosHealthResult? _health;
  String? _saveError;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _urlController =
        TextEditingController(text: widget.initialConfig.apiBaseUrl);
    _locationController = TextEditingController(
      text: widget.initialConfig.locationOrBranchId?.toString() ?? '',
    );
    _pinController = TextEditingController();
  }

  @override
  void dispose() {
    _urlController.dispose();
    _locationController.dispose();
    _pinController.dispose();
    super.dispose();
  }

  /// Verifie le PIN saisi contre l'empreinte stockee (ou le PIN d'usine).
  void _unlock() {
    final pin = _pinController.text.trim();

    if (!PosPinCode.isValid(pin)) {
      setState(() => _pinError = 'Le code doit comporter 4 a 8 chiffres.');

      return;
    }

    final stored = widget.pinHash;

    final ok = stored == null
        ? pin == PosPinCode.defaultPin
        : PosPinCode.matches(pin, storedHash: stored, salt: widget.pinSalt);

    if (!ok) {
      setState(() => _pinError = 'Code incorrect.');

      return;
    }

    setState(() {
      _unlocked = true;
      _pinError = null;
      _pinController.clear();
    });
  }

  Future<void> _testConnection() async {
    setState(() {
      _testing = true;
      _health = null;
    });

    final result = await widget.probe(_urlController.text);

    if (!mounted) {
      return;
    }

    setState(() {
      _testing = false;
      _health = result;
    });
  }

  Future<void> _save() async {
    final url = PosAppConfig.normalizeBaseUrl(_urlController.text);
    final locationText = _locationController.text.trim();
    final location = int.tryParse(locationText);

    if (url.isEmpty) {
      setState(
          () => _saveError = 'L\u2019adresse de l\u2019API est obligatoire.');

      return;
    }

    if (locationText.isNotEmpty && location == null) {
      setState(() => _saveError = 'L\u2019identifiant doit etre un nombre.');

      return;
    }

    final config = PosAppConfig(
      apiBaseUrl: url,
      locationId: widget.initialConfig.locationId != null ? location : null,
      branchId: widget.initialConfig.branchId != null ? location : null,
    );

    if (!config.isValid) {
      setState(() => _saveError =
          'Adresse invalide — exemple : https://api.leopardo.app/api/v1');

      return;
    }

    setState(() {
      _saving = true;
      _saveError = null;
    });

    await widget.onSave(config);

    if (!mounted) {
      return;
    }

    setState(() => _saving = false);
    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Reglages de la tablette'),
        leading: IconButton(
          icon: const Icon(Icons.close),
          tooltip: 'Fermer',
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: SafeArea(
        child: _unlocked ? _buildForm(scheme) : _buildPinGate(scheme),
      ),
    );
  }

  /// Porte d'entree : saisie du PIN.
  Widget _buildPinGate(ColorScheme scheme) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Icon(Icons.lock_outline, size: 56, color: scheme.primary),
              const SizedBox(height: 16),
              Text(
                'Acces protege',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                  color: scheme.onSurface,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Saisissez le code de la tablette pour modifier la configuration.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 16, color: scheme.onSurfaceVariant),
              ),
              const SizedBox(height: 24),
              TextField(
                key: const Key('pos-settings-pin'),
                controller: _pinController,
                keyboardType: TextInputType.number,
                obscureText: true,
                maxLength: PosPinCode.maxLength,
                autofocus: true,
                style: const TextStyle(fontSize: 22, letterSpacing: 8),
                textAlign: TextAlign.center,
                decoration: InputDecoration(
                  labelText: 'Code PIN',
                  errorText: _pinError,
                  border: const OutlineInputBorder(),
                  counterText: '',
                ),
                onSubmitted: (_) => _unlock(),
              ),
              const SizedBox(height: 16),
              SizedBox(
                height: 56,
                child: FilledButton.icon(
                  key: const Key('pos-settings-unlock'),
                  onPressed: _unlock,
                  icon: const Icon(Icons.lock_open),
                  label: const Text('Deverrouiller',
                      style: TextStyle(fontSize: 17)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Formulaire de configuration.
  Widget _buildForm(ColorScheme scheme) {
    final health = _health;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 640),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextField(
                key: const Key('pos-settings-url'),
                controller: _urlController,
                keyboardType: TextInputType.url,
                autocorrect: false,
                style: const TextStyle(fontSize: 17),
                decoration: const InputDecoration(
                  labelText: 'Adresse de l\u2019API',
                  hintText: 'https://api.leopardo.app/api/v1',
                  helperText:
                      'Sans slash final. Le https est ajoute si absent.',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.cloud_outlined),
                ),
              ),
              const SizedBox(height: 20),
              TextField(
                key: const Key('pos-settings-location'),
                controller: _locationController,
                keyboardType: TextInputType.number,
                style: const TextStyle(fontSize: 17),
                decoration: InputDecoration(
                  labelText: widget.locationLabel,
                  hintText: '1',
                  helperText: 'Identifiant fourni par le back-office.',
                  border: const OutlineInputBorder(),
                  prefixIcon: const Icon(Icons.storefront_outlined),
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                height: 56,
                child: OutlinedButton.icon(
                  key: const Key('pos-settings-test'),
                  onPressed: _testing ? null : _testConnection,
                  icon: _testing
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.wifi_tethering),
                  label: Text(
                    _testing ? 'Test en cours...' : 'Tester la connexion',
                    style: const TextStyle(fontSize: 17),
                  ),
                ),
              ),
              if (health != null) ...[
                const SizedBox(height: 16),
                _HealthBanner(result: health),
              ],
              if (_saveError != null) ...[
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: scheme.errorContainer,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.error_outline, color: scheme.onErrorContainer),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          _saveError!,
                          style: TextStyle(
                            fontSize: 16,
                            color: scheme.onErrorContainer,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 28),
              SizedBox(
                height: 60,
                child: FilledButton.icon(
                  key: const Key('pos-settings-save'),
                  onPressed: _saving ? null : _save,
                  icon: const Icon(Icons.save_outlined),
                  label: Text(
                    _saving ? 'Enregistrement...' : 'Enregistrer',
                    style: const TextStyle(
                        fontSize: 18, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
              const SizedBox(height: 32),
              const Divider(),
              const SizedBox(height: 16),
              Text(
                'Imprimante',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: scheme.onSurface,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                widget.printerName == null
                    ? 'Aucune imprimante configuree : les tickets ne peuvent pas etre imprimes.'
                    : 'Imprimante configuree : ${widget.printerName}.',
                style: TextStyle(fontSize: 15, color: scheme.onSurfaceVariant),
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 52,
                child: OutlinedButton.icon(
                  key: const Key('pos-settings-printer'),
                  onPressed: widget.onOpenPrinter == null
                      ? null
                      : () async {
                          await widget.onOpenPrinter!();

                          if (!mounted) {
                            return;
                          }

                          // Le nom affiche vient du parent : on reconstruit
                          // pour refleter un choix qui vient de changer.
                          setState(() {});
                        },
                  icon: const Icon(Icons.print_outlined),
                  label: Text(
                    widget.printerName == null
                        ? 'Choisir une imprimante'
                        : 'Changer d\u2019imprimante',
                    style: const TextStyle(fontSize: 16),
                  ),
                ),
              ),
              const SizedBox(height: 32),
              const Divider(),
              const SizedBox(height: 16),
              Text(
                'Code d\u2019acces',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: scheme.onSurface,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                widget.pinHash == null
                    ? 'Le code d\u2019usine est 0000. Changez-le des la mise en service.'
                    : 'Le code de cette tablette est personnalise.',
                style: TextStyle(fontSize: 15, color: scheme.onSurfaceVariant),
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 52,
                child: OutlinedButton.icon(
                  key: const Key('pos-settings-change-pin'),
                  onPressed: widget.onPinChanged == null
                      ? null
                      : () => _openPinChange(context),
                  icon: const Icon(Icons.password),
                  label: const Text('Changer le code',
                      style: TextStyle(fontSize: 16)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openPinChange(BuildContext context) async {
    final newPin = await showDialog<String>(
      context: context,
      builder: (context) => const _PinChangeDialog(),
    );

    if (newPin == null || !mounted) {
      return;
    }

    await widget.onPinChanged!(newPin);

    if (!mounted) {
      return;
    }

    // ignore: use_build_context_synchronously
    ScaffoldMessenger.of(this.context).showSnackBar(
      const SnackBar(content: Text('Code modifie.')),
    );
  }
}

/// Bandeau de resultat du test de connexion.
class _HealthBanner extends StatelessWidget {
  const _HealthBanner({required this.result});

  final PosHealthResult result;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final ok = result.isOk;

    return Container(
      key: const Key('pos-settings-health'),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: ok ? scheme.primaryContainer : scheme.errorContainer,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(
            ok ? Icons.check_circle_outline : Icons.error_outline,
            color: ok ? scheme.onPrimaryContainer : scheme.onErrorContainer,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              result.status.label,
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: ok ? scheme.onPrimaryContainer : scheme.onErrorContainer,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Dialogue de changement de PIN.
class _PinChangeDialog extends StatefulWidget {
  const _PinChangeDialog();

  @override
  State<_PinChangeDialog> createState() => _PinChangeDialogState();
}

class _PinChangeDialogState extends State<_PinChangeDialog> {
  final _first = TextEditingController();
  final _second = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _first.dispose();
    _second.dispose();
    super.dispose();
  }

  void _submit() {
    final pin = _first.text.trim();

    if (!PosPinCode.isValid(pin)) {
      setState(() => _error = '4 a 8 chiffres attendus.');

      return;
    }

    if (pin != _second.text.trim()) {
      setState(() => _error = 'Les deux saisies different.');

      return;
    }

    Navigator.of(context).pop(pin);
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Nouveau code'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            key: const Key('pos-pin-new'),
            controller: _first,
            keyboardType: TextInputType.number,
            obscureText: true,
            maxLength: PosPinCode.maxLength,
            decoration: const InputDecoration(
              labelText: 'Nouveau code',
              counterText: '',
            ),
          ),
          TextField(
            key: const Key('pos-pin-confirm'),
            controller: _second,
            keyboardType: TextInputType.number,
            obscureText: true,
            maxLength: PosPinCode.maxLength,
            decoration: InputDecoration(
              labelText: 'Confirmer',
              counterText: '',
              errorText: _error,
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Annuler'),
        ),
        FilledButton(
          key: const Key('pos-pin-save'),
          onPressed: _submit,
          child: const Text('Enregistrer'),
        ),
      ],
    );
  }
}
