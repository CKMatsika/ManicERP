import 'package:flutter/material.dart';

import '../core/api_client.dart';

class LinkAccountScreen extends StatefulWidget {
  const LinkAccountScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<LinkAccountScreen> createState() => _LinkAccountScreenState();
}

class _LinkAccountScreenState extends State<LinkAccountScreen> {
  final _account = TextEditingController();
  final _code = TextEditingController();
  bool _requested = false;
  bool _busy = false;
  bool _verified = false;
  String? _message;
  String? _linkId;
  String? _error;

  @override
  void dispose() {
    _account.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _request() async {
    setState(() {
      _busy = true;
      _error = null;
      _message = null;
    });

    try {
      final response = await widget.api.post('/api/v1/account-links', {
        'account_number': _account.text.trim(),
        'relationship': 'owner',
      });

      setState(() {
        _requested = true;
        _message = response['message']?.toString();
        _linkId = response['link_id']?.toString();
      });
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _verify() async {
    if (_linkId == null) {
      setState(() => _error = 'Request a code first.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await widget.api.post('/api/v1/account-links/$_linkId/verify', {'code': _code.text.trim()});
      setState(() => _verified = true);
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Link an account')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'A verification code is sent to the contact details held on the council account. Knowing an account number alone never grants access.',
            style: TextStyle(fontSize: 12, color: Color(0xFF475569)),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _account,
            decoration: const InputDecoration(labelText: 'Account number', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _busy ? null : _request,
            child: const Text('Send verification code'),
          ),
          if (_message != null) ...[
            const SizedBox(height: 12),
            Text(_message!),
          ],
          if (_requested) ...[
            const Divider(height: 32),
            TextField(
              controller: _code,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: '6-digit code', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _busy || _verified ? null : _verify,
              child: Text(_verified ? 'Account linked' : 'Verify and link'),
            ),
          ],
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Color(0xFFB91C1C))),
          ],
          if (_verified) ...[
            const SizedBox(height: 12),
            const Text('Linked. You can now view bills and make payments for this account.'),
          ],
        ],
      ),
    );
  }
}
