import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/api_client.dart';
import '../models/models.dart';

/// Service requests with an offline draft. A draft is clearly marked unsent and
/// is only submitted after the user retries while online.
class RequestsScreen extends StatefulWidget {
  const RequestsScreen({super.key, required this.api, required this.accounts});

  final ApiClient api;
  final List<Account> accounts;

  @override
  State<RequestsScreen> createState() => _RequestsScreenState();
}

class _RequestsScreenState extends State<RequestsScreen> {
  static const _draftKey = 'manicerp.service_request_draft';

  final _subject = TextEditingController();
  final _description = TextEditingController();
  String _category = 'billing_dispute';
  String? _accountId;
  String? _message;
  String? _error;
  bool _busy = false;
  bool _hasDraft = false;
  late Future<List<ServiceRequest>> _requests;

  @override
  void initState() {
    super.initState();
    _accountId = widget.accounts.isNotEmpty ? widget.accounts.first.id : null;
    _requests = _loadRequests();
    _loadDraft();
  }

  @override
  void dispose() {
    _subject.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<List<ServiceRequest>> _loadRequests() async {
    final response = await widget.api.get('/api/v1/service-requests');
    final data = (response['data'] as List<dynamic>? ?? const []);
    return data.map((item) => ServiceRequest.fromJson(item as Map<String, dynamic>)).toList();
  }

  Future<void> _loadDraft() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_draftKey);

    if (raw == null) return;

    final draft = jsonDecode(raw) as Map<String, dynamic>;

    if (!mounted) return;

    setState(() {
      _hasDraft = true;
      _subject.text = draft['subject']?.toString() ?? '';
      _description.text = draft['description']?.toString() ?? '';
      _category = draft['category']?.toString() ?? 'billing_dispute';
      _accountId = draft['account_id']?.toString();
    });
  }

  Future<void> _saveDraft() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _draftKey,
      jsonEncode({
        'account_id': _accountId,
        'category': _category,
        'subject': _subject.text.trim(),
        'description': _description.text.trim(),
      }),
    );
    setState(() => _hasDraft = true);
  }

  Future<void> _clearDraft() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_draftKey);
    setState(() => _hasDraft = false);
  }

  Future<void> _submit() async {
    if (_subject.text.trim().isEmpty || _description.text.trim().isEmpty) {
      setState(() => _error = 'Enter a subject and description.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
      _message = null;
    });

    try {
      final response = await widget.api.post('/api/v1/service-requests', {
        'account_id': _accountId,
        'category': _category,
        'subject': _subject.text.trim(),
        'description': _description.text.trim(),
      });

      final reference = (response['data'] as Map<String, dynamic>?)?['reference'];

      setState(() {
        _message = 'Request $reference submitted.';
        _subject.clear();
        _description.clear();
        _requests = _loadRequests();
      });

      await _clearDraft();
    } on ApiException catch (error) {
      if (error.statusCode == 0) {
        await _saveDraft();
        setState(() => _message = 'Offline: draft saved on this device and not yet submitted.');
      } else {
        setState(() => _error = error.message);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Service requests')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_hasDraft)
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFFFEF3C7), borderRadius: BorderRadius.circular(8)),
              child: const Text('An unsent draft is stored on this device. Retry when you are online.', style: TextStyle(fontSize: 12)),
            ),
          if (widget.accounts.isNotEmpty)
            DropdownButtonFormField<String>(
              value: _accountId,
              decoration: const InputDecoration(labelText: 'Account (optional)', border: OutlineInputBorder()),
              items: [
                for (final account in widget.accounts)
                  DropdownMenuItem(value: account.id, child: Text(account.accountNumber)),
              ],
              onChanged: (value) => setState(() => _accountId = value),
            ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: _category,
            decoration: const InputDecoration(labelText: 'Category', border: OutlineInputBorder()),
            items: const [
              DropdownMenuItem(value: 'billing_dispute', child: Text('Billing dispute')),
              DropdownMenuItem(value: 'leak', child: Text('Water leak')),
              DropdownMenuItem(value: 'water_interruption', child: Text('Water interruption')),
              DropdownMenuItem(value: 'refuse', child: Text('Refuse collection')),
              DropdownMenuItem(value: 'complaint', child: Text('Complaint')),
              DropdownMenuItem(value: 'other', child: Text('Other')),
            ],
            onChanged: (value) => setState(() => _category = value ?? 'billing_dispute'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _subject,
            decoration: const InputDecoration(labelText: 'Subject', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _description,
            minLines: 3,
            maxLines: 6,
            decoration: const InputDecoration(labelText: 'Description', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          if (_error != null) Text(_error!, style: const TextStyle(color: Color(0xFFB91C1C))),
          if (_message != null) Text(_message!),
          const SizedBox(height: 12),
          FilledButton(onPressed: _busy ? null : _submit, child: const Text('Submit request')),
          const Divider(height: 32),
          const Text('Submitted requests', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
          FutureBuilder<List<ServiceRequest>>(
            future: _requests,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()));
              }
              if (snapshot.hasError) {
                return Text('Could not load requests: ${snapshot.error}');
              }
              final requests = snapshot.data ?? [];
              if (requests.isEmpty) {
                return const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('No requests yet.'));
              }
              return Column(
                children: [
                  for (final request in requests)
                    ListTile(
                      dense: true,
                      title: Text(request.subject),
                      subtitle: Text('${request.reference} • ${request.category} • ${request.status}'),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}
