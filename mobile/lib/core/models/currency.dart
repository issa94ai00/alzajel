class ExchangeRateInfo {
  const ExchangeRateInfo({
    required this.buyRate,
    required this.sellRate,
    this.updatedAt,
  });

  final double buyRate;
  final double sellRate;
  final DateTime? updatedAt;

  factory ExchangeRateInfo.fromJson(Map<String, dynamic> json) {
    return ExchangeRateInfo(
      buyRate: double.parse(json['buy_rate'].toString()),
      sellRate: double.parse(json['sell_rate'].toString()),
      updatedAt: json['updated_at'] != null
          ? DateTime.tryParse(json['updated_at'] as String)
          : null,
    );
  }
}

class Currency {
  const Currency({
    required this.id,
    required this.code,
    required this.name,
    required this.symbol,
    required this.isBase,
    required this.isActive,
    this.exchangeRate,
  });

  final int id;
  final String code;
  final String name;
  final String symbol;
  final bool isBase;
  final bool isActive;
  final ExchangeRateInfo? exchangeRate;

  factory Currency.fromJson(Map<String, dynamic> json) {
    final rate = json['exchange_rate'];
    return Currency(
      id: json['id'] as int,
      code: json['code'] as String,
      name: json['name'] as String,
      symbol: json['symbol'] as String? ?? '',
      isBase: json['is_base'] as bool? ?? false,
      isActive: json['is_active'] as bool? ?? true,
      exchangeRate: (rate is Map<String, dynamic>)
          ? ExchangeRateInfo.fromJson(rate)
          : null,
    );
  }
}
