import 'package:dio/dio.dart';

import '../../../core/models/currency.dart';
import '../../../core/models/shift.dart';
import '../../../core/network/api_exception.dart';

class ShiftOpenFormData {
  const ShiftOpenFormData({
    required this.currencies,
    required this.currentShiftOpen,
    required this.canCloseCurrent,
  });

  final List<Currency> currencies;
  final bool currentShiftOpen;
  final bool canCloseCurrent;
}

class ShiftDetails {
  const ShiftDetails({required this.shift, required this.profitLoss});

  final Shift shift;
  final Map<String, dynamic> profitLoss;
}

class ShiftsRepository {
  ShiftsRepository(this._dio);

  final Dio _dio;

  Future<List<Shift>> list() async {
    try {
      final response = await _dio.get('/shifts');
      final items = response.data['data'] as List<dynamic>;
      return items.map((e) => Shift.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<ShiftOpenFormData> openFormData() async {
    try {
      final response = await _dio.get('/shifts/open-form-data');
      final data = response.data as Map<String, dynamic>;
      return ShiftOpenFormData(
        currencies: (data['currencies'] as List<dynamic>)
            .map((e) => Currency.fromJson(e as Map<String, dynamic>))
            .toList(),
        currentShiftOpen: data['current_shift_open'] as bool,
        canCloseCurrent: data['can_close_current'] as bool,
      );
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Shift> open(Map<int, String> openingBalances) async {
    try {
      final response = await _dio.post('/shifts', data: {
        'opening_balances': openingBalances.map((key, value) => MapEntry(key.toString(), value)),
      });
      return Shift.fromJson(response.data['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<ShiftDetails> show(int shiftId) async {
    try {
      final response = await _dio.get('/shifts/$shiftId');
      final data = response.data as Map<String, dynamic>;
      return ShiftDetails(
        shift: Shift.fromJson(data['shift'] as Map<String, dynamic>),
        profitLoss: data['profit_loss'] as Map<String, dynamic>,
      );
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<Shift> close(int shiftId) async {
    try {
      final response = await _dio.post('/shifts/$shiftId/close');
      return Shift.fromJson(response.data['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }
}
