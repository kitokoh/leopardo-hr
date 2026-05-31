import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/expense_claim.dart';

class ExpenseRepository {
  final ApiClient apiClient;

  ExpenseRepository(this.apiClient);

  static const _actionTimeout = Duration(seconds: 10);
  static const _readTimeout = Duration(seconds: 8);

  Future<List<ExpenseClaim>> getMyClaims() async {
    final response = await apiClient.requestWithRetry(
      '/expense-claims',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => ExpenseClaim.fromJson(e)).toList();
  }

  Future<void> submitClaim({
    required String category,
    required double amount,
    required String date,
    String? description,
  }) async {
    await apiClient.requestWithRetry(
      '/expense-claims',
      method: 'POST',
      data: {
        'category': category,
        'amount': amount,
        'date': date,
        if (description != null) 'description': description,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }
}
