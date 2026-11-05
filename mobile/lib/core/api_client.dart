import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

/// Versioned Laravel API client shared by every mobile screen.
///
/// The token is kept in memory here and persisted by [SessionStore] using
/// platform secure storage. Provider API keys never exist on the device.
class ApiClient {
  ApiClient({required this.baseUrl, http.Client? client}) : _client = client ?? http.Client();

  final String baseUrl;
  final http.Client _client;
  String? _token;

  static const String apiVersion = 'v1';

  String? get token => _token;

  void setToken(String? token) {
    _token = token;
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Future<Map<String, dynamic>> get(String path) async {
    return _send(() => _client.get(Uri.parse('$baseUrl$path'), headers: _headers));
  }

  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) async {
    return _send(() => _client.post(
          Uri.parse('$baseUrl$path'),
          headers: _headers,
          body: jsonEncode(body),
        ));
  }

  Future<Map<String, dynamic>> delete(String path) async {
    return _send(() => _client.delete(Uri.parse('$baseUrl$path'), headers: _headers));
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
    required String councilCode,
    String deviceName = 'ManicERP mobile',
  }) async {
    final data = await post('/api/${ApiClient.apiVersion}/auth/login', {
      'email': email,
      'password': password,
      'council_code': councilCode,
      'device_name': deviceName,
    });

    final token = data['token'] as String?;
    if (token == null) {
      throw const ApiException('Sign-in did not return a token.', 500);
    }
    _token = token;

    return data;
  }

  Future<Map<String, dynamic>> _send(Future<http.Response> Function() request) async {
    late http.Response response;

    try {
      response = await request().timeout(const Duration(seconds: 25));
    } on SocketException {
      throw const ApiException('You appear to be offline. Check your connection and retry.', 0);
    } on HttpException {
      throw const ApiException('The server could not be reached. Please retry.', 0);
    }

    final decoded = response.body.isEmpty ? <String, dynamic>{} : jsonDecode(response.body);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded is Map<String, dynamic> ? decoded : {'data': decoded};
    }

    if (response.statusCode == 401) {
      _token = null;
    }

    final message = decoded is Map<String, dynamic>
        ? (decoded['message']?.toString() ?? 'Request failed.')
        : 'Request failed.';

    throw ApiException(message, response.statusCode);
  }
}

class ApiException implements Exception {
  const ApiException(this.message, this.statusCode);

  final String message;
  final int statusCode;

  bool get isUnauthenticated => statusCode == 401;

  @override
  String toString() => message;
}
