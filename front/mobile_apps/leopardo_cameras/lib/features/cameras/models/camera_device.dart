/// Caméra du tenant (contrat `Camera` de api/openapi.yaml, module
/// api/app/Modules/Cameras — BC-19 DEVICE, #7426).
///
/// RGPD / sécurité (#7426, AC 4) : l'API n'expose JAMAIS l'URL RTSP ni ses
/// identifiants au client — seuls `stream_url` (WebRTC MediaMTX) et un
/// `stream_token` JWT à durée limitée transitent. Rien n'est journalisé ni
/// persisté côté mobile.
class CameraDevice {
  const CameraDevice({
    required this.id,
    required this.name,
    this.location,
    this.isActive = false,
    this.sortOrder,
    this.thumbnailUrl,
    this.streamUrl,
    this.streamToken,
    this.tokenExpiresAt,
    this.createdAt,
  });

  final int id;
  final String name;
  final String? location;
  final bool isActive;
  final int? sortOrder;
  final String? thumbnailUrl;
  final String? streamUrl;
  final String? streamToken;
  final String? tokenExpiresAt;
  final String? createdAt;

  factory CameraDevice.fromJson(Map<String, dynamic> json) {
    return CameraDevice(
      id: json['id'] as int,
      name: (json['name'] as String?) ?? '',
      location: json['location'] as String?,
      isActive: json['is_active'] == true,
      sortOrder: json['sort_order'] as int?,
      thumbnailUrl: json['thumbnail_url'] as String?,
      streamUrl: json['stream_url'] as String?,
      streamToken: json['stream_token'] as String?,
      tokenExpiresAt: json['token_expires_at'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

/// Limite du plan renvoyée par GET /cameras (`plan_limit`).
class CameraPlanLimit {
  const CameraPlanLimit({this.maxCameras, this.currentCount = 0});

  final int? maxCameras;
  final int currentCount;

  factory CameraPlanLimit.fromJson(Map<String, dynamic> json) {
    return CameraPlanLimit(
      maxCameras: json['max_cameras'] as int?,
      currentCount: (json['current_count'] as int?) ?? 0,
    );
  }
}

/// Mur des caméras : liste + limite plan (GET /cameras).
class CameraWall {
  const CameraWall({this.cameras = const [], this.planLimit});

  final List<CameraDevice> cameras;
  final CameraPlanLimit? planLimit;
}
