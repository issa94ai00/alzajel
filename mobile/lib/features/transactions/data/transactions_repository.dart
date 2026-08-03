import 'package:dio/dio.dart';

import '../../../core/models/currency.dart';
import '../../../core/models/exchange_transaction.dart';
import '../../../core/models/shift.dart';
import '../../../core/network/api_exception.dart';

class TransactionFilters {
  const TransactionFilters({this.currencyId, this.type, this.dateFrom, this.dateTo, this.search});

  final int? currencyId;
  final String? type;
  final String? dateFrom;
  final String? dateTo;
  final String? search;

  Map<String, dynamic> toQuery() => {
        if (currencyId != null) 'currency_id': currencyId,
        if (type != null) 'type': type,
        if (dateFrom != null) 'date_from': dateFrom,
        if (dateTo != null) 'date_to': dateTo,
        if (search != null && search!.isNotEmpty) 'search': search,
      };
}

class TransactionCreateFormData {
  const TransactionCreateFormData({required this.shift, required this.currencies});

  final Shift? shift;
  final List<Currency> currencies;
}

class TransactionsRepository {
  TransactionsRepository(this._dio);

  final Dio _dio;

  Future<List<ExchangeTransaction>> list(TransactionFilters filters) async {
    try {
      final response = await _dio.get('/transactions', queryParameters: filters.toQuery());
      final items = response.data['data'] as List<dynamic>;
      return items.map((e) => ExchangeTransaction.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<TransactionCreateFormData> createFormData() async {
    try {
      final response = await _dio.get('/transactions/create-form-data');
      final data = response.data as Map<String, dynamic>;
      return TransactionCreateFormData(
        shift: data['shift'] != null ? Shift.fromJson(data['shift'] as Map<String, dynamic>) : null,
        currencies: (data['currencies'] as List<dynamic>)
            .map((e) => Currency.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<ExchangeTransaction> create({
    required int currencyId,
    required String type,
    required String amount,
    String? rate,
    String? customerName,
    String? customerPhone,
    String? notes,
  }) async {
    try {
      final response = await _dio.post('/transactions', data: {
        'currency_id': currencyId,
        'type': type,
        'amount': amount,
        if (rate != null && rate.isNotEmpty) 'rate': rate,
        if (customerName != null && customerName.isNotEmpty) 'customer_name': customerName,
        if (customerPhone != null && customerPhone.isNotEmpty) 'customer_phone': customerPhone,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      });
      return ExchangeTransaction.fromJson(response.data['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
