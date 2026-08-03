import 'currency.dart';
import 'user.dart';

class ShiftAccount {
  const ShiftAccount({
    required this.currency,
    required this.openingBalance,
    required this.currentBalance,
    this.closingBalance,
  });

  final Currency? currency;
  final double openingBalance;
  final double currentBalance;
  final double? closingBalance;

  factory ShiftAccount.fromJson(Map<String, dynamic> json) {
    return ShiftAccount(
      currency: json['currency'] is Map<String, dynamic>
          ? Currency.fromJson(json['currency'] as Map<String, dynamic>)
          : null,
      openingBalance: double.parse(json['opening_balance'].toString()),
      currentBalance: double.parse(json['current_balance'].toString()),
      closingBalance: json['closing_balance'] != null
          ? double.parse(json['closing_balance'].toString())
          : null,
    );
  }
}

class Shift {
  const Shift({
    required this.id,
    required this.status,
    required this.businessDate,
    this.openedAt,
    this.closedAt,
    this.notes,
    this.openedBy,
    this.closedBy,
    this.shiftAccounts = const [],
  });

  final int id;
  final String status;
  final String businessDate;
  final DateTime? openedAt;
  final DateTime? closedAt;
  final String? notes;
  final AppUser? openedBy;
  final AppUser? closedBy;
  final List<ShiftAccount> shiftAccounts;

  bool get isOpen => status == 'open';

  factory Shift.fromJson(Map<String, dynamic> json) {
    return Shift(
      id: json['id'] as int,
      status: json['status'] as String,
      businessDate: json['business_date'] as String? ?? '',
      openedAt: json['opened_at'] != null
          ? DateTime.tryParse(json['opened_at'] as String)
          : null,
      closedAt: json['closed_at'] != null
          ? DateTime.tryParse(json['closed_at'] as String)
          : null,
      notes: json['notes'] as String?,
      openedBy: (json['opened_by'] is Map<String, dynamic> &&
              (json['opened_by'] as Map).isNotEmpty)
          ? AppUser.fromJson(json['opened_by'] as Map<String, dynamic>)
          : null,
      closedBy: (json['closed_by'] is Map<String, dynamic> &&
              (json['closed_by'] as Map).isNotEmpty)
          ? AppUser.fromJson(json['closed_by'] as Map<String, dynamic>)
          : null,
      shiftAccounts: (json['shift_accounts'] as List<dynamic>? ?? [])
          .map((e) => ShiftAccount.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
