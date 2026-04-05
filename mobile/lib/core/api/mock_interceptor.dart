import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';

class MockInterceptor extends Interceptor {
  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    // Determine the path to return mock data for
    String mockFile = '';

    if (options.path.contains('/auth/login')) {
      mockFile = 'mock_auth_login.json';
    } else if (options.path.contains('/auth/me')) {
      // Create a small mock inline or use a file
      return handler.resolve(Response(
        requestOptions: options,
        data: {
          "data": {
            "id": 101,
            "first_name": "Hamid",
            "last_name": "Djebari",
            "email": "h.djebari@techcorp.dz",
            "status": "active",
            "company": {"id": "1", "name": "Mock Corp"}
          }
        },
        statusCode: 200,
      ));
    } else if (options.path.contains('/attendance/check-in')) {
      mockFile = 'mock_attendance_today_B_checked_in.json';
    } else if (options.path.contains('/attendance/check-out')) {
      return handler.resolve(Response(
        requestOptions: options,
        data: {
          "data": {
            "id": 5432, "date": "2026-04-15", "check_in": "2026-04-15T07:55:12Z", "check_out": "2026-04-15T17:05:00Z", "status": "ontime"
          }
        },
        statusCode: 200,
      ));
    } else if (options.path.contains('/attendance/today')) {
      // Toggle logic or just return checked in for demonstration
      mockFile = 'mock_attendance_today_A_not_checked.json';
    } else if (options.path.contains('/attendance')) {
      mockFile = 'mock_attendance_history.json';
    } else if (options.path.contains('/daily-summary')) {
      mockFile = 'mock_daily_summary.json';
    } else {
      return handler.next(options);
    }

    try {
      final jsonString = await rootBundle.loadString('assets/mock/$mockFile');
      final data = json.decode(jsonString);
      // Simulate network delay
      await Future.delayed(const Duration(seconds: 1));
      
      return handler.resolve(
        Response(
          requestOptions: options,
          data: data,
          statusCode: 200,
        ),
      );
    } catch (_) {
      // If file not found, let it fail 
      return handler.reject(DioException(
        requestOptions: options,
        response: Response(requestOptions: options, statusCode: 404),
      ));
    }
  }
}
