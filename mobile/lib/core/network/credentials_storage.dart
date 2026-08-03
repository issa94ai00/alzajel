import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SavedCredentials {
  const SavedCredentials({required this.email, required this.password});

  final String email;
  final String password;
}

class CredentialsStorage {
  CredentialsStorage() : _storage = const FlutterSecureStorage();

  final FlutterSecureStorage _storage;
  static const _emailKey = 'remembered_email';
  static const _passwordKey = 'remembered_password';

  Future<SavedCredentials?> read() async {
    final email = await _storage.read(key: _emailKey);
    final password = await _storage.read(key: _passwordKey);
    if (email == null || password == null) return null;
    return SavedCredentials(email: email, password: password);
  }

  Future<void> save({required String email, required String password}) async {
    await _storage.write(key: _emailKey, value: email);
    await _storage.write(key: _passwordKey, value: password);
  }

  Future<void> clear() async {
    await _storage.delete(key: _emailKey);
    await _storage.delete(key: _passwordKey);
  }
}
