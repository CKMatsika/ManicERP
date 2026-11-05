import 'dart:math';

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../models/models.dart';

/// Creates a server-side payment intent. The app never marks a payment as
/// complete: a receipt exists only after the provider confirms settlement to
/// the backend through a signed webhook.
class PayScreen extends StatefulWidget {
  const PayScreen({super.key, required this.api, required this.account, required this.bills});

  final ApiClient api;
  final Account account;
  final List<Bill> bills;

  @override
  State<PayScreen> createState() => _PayScreenState();
}

class _PayScreenState extends State<PayScreen> {
  String? _billId;
  final _amount = TextEditingController();
  bool _busy = false;
  String? _error;
  Map<String, dynamic>? _intent;

  @override
  void initState() {
    super.initState();
    if (widget.bills.isNotEmpty) {
      _billId = widget.bills.first.id;
    }
  }

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  String _idempotencyKey() {
    final random = Random.secure();
    return 'mobile-${DateTime.now().millisecondsSinceEpoch}-${random.nextInt(1 << 32)}';
  }

  Future<void> _createIntent() async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final body = <String, dynamic>{
        'account_id': widget.account.id,
        'idempotency_key': _idempotencyKey(),
      };

      if (_billId != null) {
        body['bill_id'] = _billId;
      } else {
        body['amount'] = _amount.text.trim();
      }

      final response = await widget.api.post('/api/v1/payments/intents', body);
      setState(() => _intent = response['data'] as Map<String, dynamic>?);
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pay')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Sandbox payment adapter. No card or mobile-money details are collected and no real money moves. A receipt is issued only after the server verifies settlement.',
            style: TextStyle(fontSize: 12, color: Color(0xFF92400E)),
          ),
          const SizedBox(height: 16),
          for (final bill in widget.bills)
            RadioListTile<String>(
              value: bill.id,
              groupValue: _billId,
              onChanged: (value) => setState(() {
                _billId = value;
                _intent = null;
              }),
              title: Text('${bill.billNumber} • ${bill.serviceType}'),
              subtitle: Text('Balance ${bill.balanceLabel}'),
            ),
          const Divider(),
          TextField(
            controller: _amount,
            enabled: _billId == null,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Other amount (USD)', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 16),
          if (_error != null)
            Container(
              padding: const EdgeInsets.all(12),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(color: const Color(0xFFFEE2E2), borderRadius: BorderRadius.circular(8)),
              child: Text(_error!, style: const TextStyle(color: Color(0xFF991B1B))),
            ),
          FilledButton(
            onPressed: _busy ? null : _createIntent,
            child: _busy ? const CircularProgressIndicator() : const Text('Create payment intent'),
          ),
          if (_intent != null) ...[
            const SizedBox(height: 20),
            Card(
              color: const Color(0xFFECFDF5),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Payment pending verification', style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    Text('Reference: ${_intent!['reference']}'),
                    Text('Status: ${_intent!['status']}'),
                    const SizedBox(height: 8),
                    const Text(
                      'Complete the sandbox confirmation in the provider flow. This screen will not show a receipt until the server has verified the settlement.',
                      style: TextStyle(fontSize: 12),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
