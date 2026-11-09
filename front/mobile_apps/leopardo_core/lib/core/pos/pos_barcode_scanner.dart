import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

/// **Ecran de scan code-barres** partage par les deux caisses POS.
///
/// Il vivait dans `leopardo_pos_retail` uniquement ; l'app Restaurant en a
/// besoin aussi (boissons et produits emballes portent un code-barres). Le
/// deplacer ici evite d'en maintenir deux copies qui divergeraient — c'est
/// exactement ce que le garde CI `check-mobile-core-duplication.py` interdit.
///
/// Regles d'usage reel, apprises sur une caisse :
///  - **un seul scan par ouverture** : on ferme des la premiere lecture, sinon
///    la camera continue de lire le meme code et ajoute N fois l'article ;
///  - **anti-rebond** : un code identique relu dans la meme seconde est ignore
///    (les douchettes et la camera lisent plusieurs fois par seconde) ;
///  - **permission refusee** : on le dit clairement et on propose la saisie
///    manuelle, plutot que d'afficher un ecran noir muet.
///
/// Le widget ne connait ni le catalogue ni le panier : il renvoie une chaine
/// via `Navigator.pop`. C'est ce qui le rend reutilisable tel quel.
class PosBarcodeScannerScreen extends StatefulWidget {
  const PosBarcodeScannerScreen({
    super.key,
    this.title = 'Scanner un code-barres',
    this.hint = 'Placez le code-barres dans le cadre.',
    this.accentColor,
    this.cardRadius = 14,
  });

  final String title;
  final String hint;

  /// Couleur d'accent du viseur (celle du theme de l'app appelante).
  final Color? accentColor;

  final double cardRadius;

  @override
  State<PosBarcodeScannerScreen> createState() => _PosBarcodeScannerScreenState();
}

class _PosBarcodeScannerScreenState extends State<PosBarcodeScannerScreen> {
  final _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const [
      BarcodeFormat.ean13,
      BarcodeFormat.ean8,
      BarcodeFormat.upcA,
      BarcodeFormat.upcE,
      BarcodeFormat.code128,
      BarcodeFormat.code39,
      BarcodeFormat.itf14,
      BarcodeFormat.qrCode,
    ],
  );

  /// Dernier code accepte + son horodatage : sert d'anti-rebond.
  String? _lastCode;
  DateTime? _lastAt;

  /// Un scan a deja ete renvoye : on n'en accepte pas un second.
  bool _handled = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  /// Un code est-il acceptable maintenant ?
  ///
  /// Un meme code relu en moins d'une seconde est un echo de lecture, pas un
  /// nouvel article : l'ignorer evite de doubler la ligne du panier.
  bool _accept(String code) {
    final now = DateTime.now();

    if (_lastCode == code && _lastAt != null) {
      if (now.difference(_lastAt!) < const Duration(seconds: 1)) {
        return false;
      }
    }

    _lastCode = code;
    _lastAt = now;

    return true;
  }

  void _onDetect(BarcodeCapture capture) {
    if (_handled) {
      return;
    }

    for (final barcode in capture.barcodes) {
      final code = barcode.rawValue?.trim();

      if (code == null || code.isEmpty) {
        continue;
      }

      if (!_accept(code)) {
        continue;
      }

      _handled = true;
      Navigator.of(context).pop(code);

      return;
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final accent = widget.accentColor ?? scheme.primary;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          IconButton(
            tooltip: 'Lampe',
            onPressed: _controller.toggleTorch,
            icon: const Icon(Icons.flashlight_on_outlined),
          ),
          IconButton(
            tooltip: 'Changer de camera',
            onPressed: _controller.switchCamera,
            icon: const Icon(Icons.cameraswitch_outlined),
          ),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            errorBuilder: (context, error) => _ScannerError(
              error: error,
              cardRadius: widget.cardRadius,
            ),
          ),
          // Viseur : un cadre net au centre, lisible a bout de bras.
          IgnorePointer(
            child: Center(
              child: Container(
                width: 320,
                height: 200,
                decoration: BoxDecoration(
                  border: Border.all(color: accent, width: 4),
                  borderRadius: BorderRadius.circular(widget.cardRadius),
                ),
              ),
            ),
          ),
          Align(
            alignment: Alignment.bottomCenter,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              color: scheme.surface.withValues(alpha: 0.92),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    widget.hint,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w600,
                      color: scheme.onSurface,
                    ),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.keyboard),
                    label: const Text('Saisir le code a la main'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Erreur camera (permission refusee, materiel indisponible).
///
/// On explique et on propose une porte de sortie : une caisse bloquee sur un
/// ecran noir ne peut plus vendre.
class _ScannerError extends StatelessWidget {
  const _ScannerError({required this.error, required this.cardRadius});

  final MobileScannerException error;
  final double cardRadius;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    final message = switch (error.errorCode) {
      MobileScannerErrorCode.permissionDenied =>
        'Acces a la camera refuse. Autorisez la camera dans les reglages de la tablette.',
      MobileScannerErrorCode.unsupported =>
        'Cet appareil ne permet pas le scan par camera.',
      _ => 'Camera indisponible. Utilisez la saisie manuelle du code.',
    };

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.no_photography_outlined, size: 56, color: scheme.error),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 18, color: scheme.onSurface),
            ),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: () => Navigator.of(context).pop(),
              icon: const Icon(Icons.keyboard),
              label: const Text('Saisir le code a la main'),
            ),
          ],
        ),
      ),
    );
  }
}
