import 'package:dio/dio.dart';

import '../../../core/network/api_exception.dart';

class ReportsRepository {
  ReportsRepository(this._dio);

  final Dio _dio;

  Future<Map<String, dynamic>> balances() async {
    try {
      final response = await _dio.get('/reports/balances');
      return response.data as Map<String, dynamic>;
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Map<String, dynamic>> daily({String? dateFrom, String? dateTo}) async {
    try {
      final response = await _dio.get('/reports/daily', queryParameters: {
        'date_from': ?dateFrom,
        'date_to': ?dateTo,
      });
      return response.data as Map<String, dynamic>;
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Map<String, dynamic>> profitLoss({String? dateFrom, String? dateTo}) async {
    try {
      final response = await _dio.get('/reports/profit-loss', queryParameters: {
        'date_from': ?dateFrom,
        'date_to': ?dateTo,
      });
      return response.data as Map<String, dynamic>;
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Map<String, dynamic>> currencyMovement({int? currencyId, String? dateFrom, String? dateTo}) async {
    try {
      final response = await _dio.get('/reports/currency-movement', queryParameters: {
        'currency_id': ?currencyId,
        'date_from': ?dateFrom,
        'date_to': ?dateTo,
      });
      return response.data as Map<String, dynamic>;
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
