import 'package:dio/dio.dart';

import '../../../core/models/currency.dart';
import '../../../core/network/api_exception.dart';

class CurrenciesRepository {
  CurrenciesRepository(this._dio);

  final Dio _dio;

  Future<List<Currency>> list() async {
    try {
      final response = await _dio.get('/admin/currencies');
      final items = response.data['data'] as List<dynamic>;
      return items.map((e) => Currency.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> create({
    required String code,
    required String name,
    String? symbol,
    required bool isActive,
  }) async {
    try {
      await _dio.post('/admin/currencies', data: {
        'code': code.toUpperCase(),
        'name': name,
        'symbol': symbol,
        'is_active': isActive,
      });
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> update(
    int currencyId, {
    required String code,
    required String name,
    String? symbol,
    required bool isActive,
  }) async {
    try {
      await _dio.put('/admin/currencies/$currencyId', data: {
        'code': code.toUpperCase(),
        'name': name,
        'symbol': symbol,
        'is_active': isActive,
      });
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
