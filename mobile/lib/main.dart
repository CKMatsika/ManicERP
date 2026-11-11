import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'core/api_client.dart';
import 'core/session_store.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';

/// API base URL. Pass with --dart-define=API_BASE_URL=https://erp.example.org
/// Defaults to localhost for Web, and Android emulator loopback (10.0.2.2) for mobile.
const String _configuredApiBaseUrl = String.fromEnvironment('API_BASE_URL');
final String apiBaseUrl = _configuredApiBaseUrl.isNotEmpty
    ? _configuredApiBaseUrl
    : (kIsWeb ? 'http://127.0.0.1:8000' : 'http://10.0.2.2:8000');

void main() {
  runApp(const ManicErpApp());
}

class ManicErpApp extends StatefulWidget {
  const ManicErpApp({super.key});

  @override
  State<ManicErpApp> createState() => _ManicErpAppState();
}

class _ManicErpAppState extends State<ManicErpApp> {
  late final ApiClient _api;
  late final SessionStore _session;
  bool _loading = true;
  bool _signedIn = false;

  @override
  void initState() {
    super.initState();
    _api = ApiClient(baseUrl: apiBaseUrl);
    _session = SessionStore();
    _restore();
  }

  Future<void> _restore() async {
    final token = await _session.readToken();
    if (token != null) {
      _api.setToken(token);
    }
    if (mounted) {
      setState(() {
        _signedIn = token != null;
        _loading = false;
      });
    }
  }

  Future<void> _signOut() async {
    try {
      await _api.post('/api/v1/auth/logout', {});
    } catch (_) {
      // Revocation is best-effort offline; local credentials are still removed.
    }
    await _session.clear();
    _api.setToken(null);
    if (mounted) {
      setState(() => _signedIn = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'ManicERP',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF0D5C3A),
          primary: const Color(0xFF0D5C3A),
          secondary: const Color(0xFFD4A373),
        ),
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFF8FAF8),
        appBarTheme: const AppBarTheme(
          centerTitle: false,
          elevation: 0,
        ),
        cardTheme: const CardThemeData(
          elevation: 1,
        ),
        visualDensity: VisualDensity.standard,
      ),
      home: _loading
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : _signedIn
              ? HomeScreen(api: _api, session: _session, onSignOut: _signOut)
              : LoginScreen(
                  api: _api,
                  session: _session,
                  onSignedIn: () => setState(() => _signedIn = true),
                ),
    );
  }
}
