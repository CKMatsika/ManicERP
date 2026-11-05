import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the API token and selected council using platform secure storage.
class SessionStore {
  SessionStore({FlutterSecureStorage? storage}) : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'manicerp.token';
  static const _councilKey = 'manicerp.council';
  static const _councilNameKey = 'manicerp.council_name';

  final FlutterSecureStorage _storage;

  Future<void> save({required String token, required String councilCode, required String councilName}) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _councilKey, value: councilCode);
    await _storage.write(key: _councilNameKey, value: councilName);
  }

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<String?> readCouncil() => _storage.read(key: _councilKey);

  Future<String?> readCouncilName() => _storage.read(key: _councilNameKey);

  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _councilKey);
    await _storage.delete(key: _councilNameKey);
  }
}
