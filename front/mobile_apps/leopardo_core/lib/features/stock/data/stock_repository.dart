import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/stock_models.dart';

/// Verticale servie par le repository de stock.
///
/// Les deux verticales exposent les MEMES gestes metier mais pas les memes
/// chemins : Retail sous `/retail/stock/*`, restaurant sous
/// `/restaurant/stock-levels` + `/restaurant/inventory-movements`. Le
/// repository est le seul endroit qui connait cette difference — les ecrans
/// appellent `levels()`, `movements()`, `recordMovement()` sans savoir pour
/// quelle verticale ils tournent.
enum StockVertical {
  retail,
  restaurant;

  String get _levelsPath => this == StockVertical.retail
      ? '/retail/stock/levels'
      : '/restaurant/stock-levels';

  String get _movementsPath => this == StockVertical.retail
      ? '/retail/stock/movements'
      : '/restaurant/inventory-movements';

  String get _alertsPath => this == StockVertical.retail
      ? '/retail/stock/alerts'
      : '/restaurant/stock/alerts';
}

/// Contrat de lecture/ecriture du stock, independant du client HTTP.
///
/// L'ecran partage ([StockScreen]) ne depend QUE de cette interface : le POS
/// tablette a son propre client Dio (base URL, file offline, cache Hive) et ne
/// peut pas passer par `ApiClient` de leopardo_core. Sans cette abstraction,
/// la seule alternative serait de dupliquer l'ecran — exactement ce que la
/// garde `check-mobile-duplicated-drift.sh` interdit.
abstract class StockDataSource {
  Future<List<StockLevel>> levels({
    int? locationId,
    int? productId,
    bool belowThresholdOnly,
    int perPage,
  });

  Future<List<StockMovement>> movements({
    int? locationId,
    int? productId,
    String? reasonCode,
    int perPage,
  });

  Future<List<StockAlert>> alerts({int? locationId, int perPage});

  Future<StockMovement> recordMovement({
    required int locationId,
    required int productId,
    required double quantityDelta,
    required StockMovementReason reason,
    int? unitCostMinor,
    String? note,
    String? idempotencyKey,
  });
}

/// Repository de stock partage par les surfaces mobiles (#9272).
///
/// Une seule implementation pour les deux verticales : dupliquer ce fichier
/// par app est exactement ce que la garde `check-mobile-duplicated-drift.sh`
/// interdit, et deux copies divergent toujours (une correction d'encodage ou
/// de contrat appliquee a une seule).
///
/// Contrat de lecture : les listes sont **paginees cote serveur** ; on ne
/// demande qu'une page et on ne fait aucune somme locale. Un ecran qui a
/// besoin d'un total appelle le rapport serveur.
class StockRepository implements StockDataSource {
  StockRepository(this.apiClient, {this.vertical = StockVertical.retail});

  final ApiClient apiClient;
  final StockVertical vertical;

  static const _readTimeout = Duration(seconds: 8);
  static const _actionTimeout = Duration(seconds: 10);

  /// Niveaux de stock, filtrables par emplacement et par seuil.
  @override
  Future<List<StockLevel>> levels({
    int? locationId,
    int? productId,
    bool belowThresholdOnly = false,
    int perPage = 50,
  }) async {
    final response = await apiClient.requestWithRetry(
      vertical._levelsPath,
      queryParameters: {
        if (locationId != null) 'location_id': locationId,
        if (productId != null) 'product_id': productId,
        if (belowThresholdOnly) 'below_threshold': true,
        'per_page': perPage,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );

    return extractDataList(response.data)
        .whereType<Map>()
        .map((raw) => StockLevel.fromJson(raw.cast<String, dynamic>()))
        .toList();
  }

  /// Journal des mouvements, du plus recent au plus ancien.
  @override
  Future<List<StockMovement>> movements({
    int? locationId,
    int? productId,
    String? reasonCode,
    int perPage = 50,
  }) async {
    final response = await apiClient.requestWithRetry(
      vertical._movementsPath,
      queryParameters: {
        if (locationId != null) 'location_id': locationId,
        if (productId != null) 'product_id': productId,
        if (reasonCode != null) 'reason_code': reasonCode,
        'per_page': perPage,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );

    return extractDataList(response.data)
        .whereType<Map>()
        .map((raw) => StockMovement.fromJson(raw.cast<String, dynamic>()))
        .toList();
  }

  /// Niveaux sous leur seuil d'alerte.
  @override
  Future<List<StockAlert>> alerts({int? locationId, int perPage = 50}) async {
    final response = await apiClient.requestWithRetry(
      vertical._alertsPath,
      queryParameters: {
        if (locationId != null) 'location_id': locationId,
        'per_page': perPage,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );

    return extractDataList(response.data)
        .whereType<Map>()
        .map((raw) => StockAlert.fromJson(raw.cast<String, dynamic>()))
        .toList();
  }

  /// Enregistre un mouvement de stock.
  ///
  /// [idempotencyKey] est **obligatoire pour un rejeu offline** : sans elle,
  /// une operation mise en file puis rejouee double la quantite. Le serveur
  /// Retail la deduplique par tenant (#9270) ; la verticale restaurant ne
  /// l'accepte pas encore, d'ou l'omission explicite du champ.
  ///
  /// Leve [StockWriteException] sur un refus serveur (422) en conservant le
  /// message : un ecran doit afficher le refus tel quel, jamais l'avaler.
  @override
  Future<StockMovement> recordMovement({
    required int locationId,
    required int productId,
    required double quantityDelta,
    required StockMovementReason reason,
    int? unitCostMinor,
    String? note,
    String? idempotencyKey,
  }) async {
    final wireCode = reason.wireCode;

    if (wireCode == null) {
      throw StockWriteException(
        'Ce motif ne peut pas etre saisi manuellement.',
        statusCode: null,
      );
    }

    final payload = <String, dynamic>{
      'location_id': locationId,
      'product_id': productId,
      'quantity_delta': quantityDelta,
      'reason_code': wireCode,
      if (unitCostMinor != null) 'unit_cost_minor': unitCostMinor,
      if (note != null && note.isNotEmpty) 'note': note,
      if (idempotencyKey != null && vertical == StockVertical.retail)
        'idempotency_key': idempotencyKey,
    };

    try {
      final response = await apiClient.requestWithRetry(
        vertical._movementsPath,
        method: 'POST',
        data: payload,
        maxRetriesOverride: 0,
        timeoutOverride: _actionTimeout,
      );

      final envelope = extractDataMap(response.data);
      final movement = envelope['movement'];

      if (movement is Map) {
        return StockMovement.fromJson(movement.cast<String, dynamic>());
      }

      return StockMovement.fromJson(envelope);
    } on StockWriteException {
      rethrow;
    } on ApiException catch (error) {
      // Le message serveur (422 « Stock insuffisant ») est conserve tel quel :
      // un ecran doit l'afficher, pas le remplacer par un texte generique.
      throw StockWriteException(
        error.message,
        statusCode: error.statusCode,
        cause: error,
      );
    } catch (error) {
      throw StockWriteException(
        'Le mouvement de stock a ete refuse.',
        statusCode: null,
        cause: error,
      );
    }
  }
}

/// Refus d'ecriture de stock, avec le message serveur quand il existe.
class StockWriteException implements Exception {
  StockWriteException(this.message, {this.statusCode, this.cause});

  final String message;
  final int? statusCode;
  final Object? cause;

  @override
  String toString() => message;
}
