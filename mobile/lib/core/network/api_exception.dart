import 'package:dio/dio.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errors});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  String? get firstFieldError {
    if (errors == null || errors!.isEmpty) return null;
    final firstList = errors!.values.first;
    if (firstList is List && firstList.isNotEmpty) return firstList.first.toString();
    return firstList.toString();
  }

  factory ApiException.fromDioException(DioException e) {
    final response = e.response;

    if (response == null) {
      return ApiException('تعذر الاتصال بالخادم. تحقق من اتصال الشبكة.');
    }

    final data = response.data;
    String message = 'حدث خطأ غير متوقع.';
    Map<String, dynamic>? errors;

    if (data is Map<String, dynamic>) {
      message = data['message'] as String? ?? message;
      if (data['errors'] is Map<String, dynamic>) {
        errors = data['errors'] as Map<String, dynamic>;
      }
    }

    if (response.statusCode == 401) {
      message = 'انتهت الجلسة، يرجى تسجيل الدخول مجدداً.';
    } else if (response.statusCode == 403) {
      message = 'لا تملك صلاحية للقيام بهذا الإجراء.';
    }

    return ApiException(message, statusCode: response.statusCode, errors: errors);
  }

  @override
  String toString() => message;
}
