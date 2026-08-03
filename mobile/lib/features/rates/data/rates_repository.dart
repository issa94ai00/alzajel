import 'package:dio/dio.dart';

import '../../../core/models/currency.dart';
import '../../../core/network/api_exception.dart';

class RatesRepository {
  RatesRepository(this._dio);

  final Dio _dio;

  Future<List<Currency>> list() async {
    try {
      final response = await _dio.get('/currencies');
      final items = response.data['data'] as List<dynamic>;
      return items.map((e) => Currency.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> update(int currencyId, {required String buyRate, required String sellRate}) async {
    try {
      await _dio.put('/rates/$currencyId', data: {
        'buy_rate': buyRate,
        'sell_rate': sellRate,
      });
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
