class ExpenseClaim {
  final int id;
  final String reference;
  final String category;
  final double amount;
  final String currency;
  final String date;
  final String? description;
  final String status;

  ExpenseClaim({
    required this.id,
    required this.reference,
    required this.category,
    required this.amount,
    required this.currency,
    required this.date,
    this.description,
    required this.status,
  });

  factory ExpenseClaim.fromJson(Map<String, dynamic> json) {
    return ExpenseClaim(
      id: json['id'] as int,
      reference: json['reference'] as String? ?? '',
      category: json['category'] as String? ?? '',
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      currency: json['currency'] as String? ?? 'DZD',
      date: json['date'] as String? ?? '',
      description: json['description'] as String?,
      status: json['status'] as String? ?? 'pending',
    );
  }
}
