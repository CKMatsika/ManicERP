import 'package:flutter/material.dart';

import 'core/api_client.dart';
import 'core/session_store.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';

/// API base URL. Pass with --dart-define=API_BASE_URL=https://erp.example.org
/// The default targets the Android emulator's host loopback.
const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000',
);

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
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0F766E)),
        useMaterial3: true,
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
