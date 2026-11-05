import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/session_store.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api, required this.session, required this.onSignedIn});

  final ApiClient api;
  final SessionStore session;
  final VoidCallback onSignedIn;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _council = TextEditingController(text: 'DEMO');
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _council.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final data = await widget.api.login(
        email: _email.text.trim(),
        password: _password.text,
        councilCode: _council.text.trim().toUpperCase(),
        deviceName: 'ManicERP mobile',
      );

      final council = data['council'] as Map<String, dynamic>? ?? {};

      await widget.session.save(
        token: widget.api.token ?? '',
        councilCode: council['code']?.toString() ?? _council.text.trim().toUpperCase(),
        councilName: council['name']?.toString() ?? 'Council',
      );

      if (mounted) {
        widget.onSignedIn();
      }
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('ManicERP', style: Theme.of(context).textTheme.labelLarge?.copyWith(color: const Color(0xFF0F766E))),
                    const SizedBox(height: 4),
                    Text('Municipal services on your phone', style: Theme.of(context).textTheme.headlineSmall),
                    const SizedBox(height: 24),
                    if (_error != null) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(color: const Color(0xFFFEE2E2), borderRadius: BorderRadius.circular(8)),
                        child: Text(_error!, style: const TextStyle(color: Color(0xFF991B1B))),
                      ),
                      const SizedBox(height: 12),
                    ],
                    TextFormField(
                      controller: _council,
                      textCapitalization: TextCapitalization.characters,
                      decoration: const InputDecoration(labelText: 'Council code', border: OutlineInputBorder()),
                      validator: (value) => (value == null || value.trim().isEmpty) ? 'Enter your council code' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.username],
                      decoration: const InputDecoration(labelText: 'Email address', border: OutlineInputBorder()),
                      validator: (value) => (value == null || !value.contains('@')) ? 'Enter a valid email address' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _password,
                      obscureText: true,
                      autofillHints: const [AutofillHints.password],
                      decoration: const InputDecoration(labelText: 'Password', border: OutlineInputBorder()),
                      validator: (value) => (value == null || value.isEmpty) ? 'Enter your password' : null,
                    ),
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: _busy
                          ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Text('Sign in'),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'Accounts must be issued by your council. Account numbers alone never grant access; linking requires a verification code.',
                      style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
