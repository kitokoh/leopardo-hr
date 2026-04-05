class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final String? code;

  ApiException(this.message, {this.statusCode, this.code});

  @override
  String toString() => 'ApiException: $message (Status: $statusCode, Code: $code)';
}
