import 'currency.dart';
import 'user.dart';

class ExchangeTransaction {
  const ExchangeTransaction({
    required this.id,
    required this.shiftId,
    required this.type,
    required this.amount,
    required this.rate,
    required this.total,
    required this.profit,
    this.customerName,
    this.customerPhone,
    this.notes,
    this.createdAt,
    this.currency,
    this.counterCurrency,
    this.user,
  });

  final int id;
  final int shiftId;
  final String type;
  final double amount;
  final double rate;
  final double total;
  final double profit;
  final String? customerName;
  final String? customerPhone;
  final String? notes;
  final DateTime? createdAt;
  final Currency? currency;
  final Currency? counterCurrency;
  final AppUser? user;

  bool get isBuy => type == 'buy';

  factory ExchangeTransaction.fromJson(Map<String, dynamic> json) {
    return ExchangeTransaction(
      id: json['id'] as int,
      shiftId: json['shift_id'] as int,
      type: json['type'] as String,
      amount: double.parse(json['amount'].toString()),
      rate: double.parse(json['rate'].toString()),
      total: double.parse(json['total'].toString()),
      profit: double.parse(json['profit'].toString()),
      customerName: json['customer_name'] as String?,
      customerPhone: json['customer_phone'] as String?,
      notes: json['notes'] as String?,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'] as String)
          : null,
      currency: json['currency'] is Map<String, dynamic>
          ? Currency.fromJson(json['currency'] as Map<String, dynamic>)
          : null,
      counterCurrency: json['counter_currency'] is Map<String, dynamic>
          ? Currency.fromJson(json['counter_currency'] as Map<String, dynamic>)
          : null,
      user: json['user'] is Map<String, dynamic>
          ? AppUser.fromJson(json['user'] as Map<String, dynamic>)
          : null,
    );
  }
}
