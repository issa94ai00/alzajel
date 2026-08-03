import 'package:dio/dio.dart';

import '../../../core/models/user.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/token_storage.dart';

class AuthRepository {
  AuthRepository(this._dio, this._tokenStorage);

  final Dio _dio;
  final TokenStorage _tokenStorage;

  Future<AppUser> login({required String email, required String password}) async {
    try {
      final response = await _dio.post('/login', data: {
        'email': email,
        'password': password,
        'device_name': 'alzajel-mobile',
      });

      final token = response.data['token'] as String;
      await _tokenStorage.saveToken(token);

      return AppUser.fromJson(response.data['user'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw ApiException.fromDioException(e);
    }
  }

  Future<AppUser?> me() async {
    try {
      final response = await _dio.get('/me');
      return AppUser.fromJson(response.data['data'] as Map<String, dynamic>);
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) return null;
      throw ApiException.fromDioException(e);
    }
  }

  Future<void> logout() async {
    try {
      await _dio.post('/logout');
    } on DioException {
      // تجاهل الخطأ — سنمسح التوكن محلياً على أي حال
    } finally {
      await _tokenStorage.clearToken();
    }
  }
}
