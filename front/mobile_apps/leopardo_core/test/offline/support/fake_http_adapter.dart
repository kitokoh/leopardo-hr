// ============================================================
// FakeHttpAdapter — minimal Dio HttpClientAdapter test double.
//
// Records every request and returns pre-programmed responses (or
// throws a DioException) so offline-service tests can exercise the
// online, offline-fallback, and API-failure branches deterministically
// without a real network dependency. See issue #1296.
// ============================================================

import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

class RecordedRequest {
  RecordedRequest(this.method, this.path, this.data, this.headers);

  final String method;
  final String path;
  final Object? data;
  final Map<String, dynamic> headers;
}

/// A queued response or failure for the next matching request.
class FakeHttpResponse {
  const FakeHttpResponse({
    this.statusCode = 200,
    this.data,
    this.throwError = false,
    this.errorType = DioExceptionType.connectionError,
  });

  final int statusCode;
  final Map<String, dynamic>? data;
  final bool throwError;
  final DioExceptionType errorType;
}

class FakeHttpAdapter implements HttpClientAdapter {
  final List<RecordedRequest> requests = [];

  /// Queue of responses consumed in FIFO order, one per request. When the
  /// queue is empty, requests fail with [DioExceptionType.connectionError]
  /// (simulating "server unreachable"), which mirrors what a real Dio
  /// adapter does when there's nothing listening — this is intentional so
  /// tests default to the offline branch unless a response is queued.
  final List<FakeHttpResponse> responses = [];

  void queueSuccess({Map<String, dynamic>? data, int statusCode = 200}) {
    responses.add(FakeHttpResponse(statusCode: statusCode, data: data));
  }

  void queueFailure({
    DioExceptionType type = DioExceptionType.connectionError,
  }) {
    responses.add(FakeHttpResponse(throwError: true, errorType: type));
  }

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(
      RecordedRequest(
        options.method,
        options.path,
        options.data,
        options.headers,
      ),
    );

    final next = responses.isNotEmpty
        ? responses.removeAt(0)
        : const FakeHttpResponse(throwError: true);

    if (next.throwError) {
      throw DioException(
        requestOptions: options,
        type: next.errorType,
        error:
            'FakeHttpAdapter: no response queued or explicit failure requested',
      );
    }

    final body = jsonEncode(next.data ?? <String, dynamic>{});
    return ResponseBody.fromString(
      body,
      next.statusCode,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
