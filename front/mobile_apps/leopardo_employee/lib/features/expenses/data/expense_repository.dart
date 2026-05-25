import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/expense_claim.dart';

class ExpenseRepository {
  final ApiClient apiClient;

  ExpenseRepository(this.apiClient);

  Future<List<ExpenseClaim>> getMyClaims() async {
    final response = await apiClient.dio.get('/expense-claims');
    final items = response.data['data'] as List;
    return items.map((e) => ExpenseClaim.fromJson(e)).toList();
  }

  Future<void> submitClaim({
    required String category,
    required double amount,
    required String date,
    String? description,
  }) async {
    await apiClient.dio.post(
      '/expense-claims',
      data: {
        'category': category,
        'amount': amount,
        'date': date,
        if (description != null) 'description': description,
      },
    );
  }
}
