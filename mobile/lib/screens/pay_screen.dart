import 'dart:math';

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../models/models.dart';

/// Handles payments via Paynow Zimbabwe (EcoCash, OneMoney, and Zimswitch/Cards).
/// Server-side verified settlements issue real receipts.
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
  final _phone = TextEditingController(text: '0774123456');

  String _paymentMethod = 'ecocash'; // ecocash, onemoney, card
  bool _busy = false;
  bool _simulating = false;
  String? _error;
  Map<String, dynamic>? _intent;
  Map<String, dynamic>? _settledReceipt;

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
    _phone.dispose();
    super.dispose();
  }

  String _idempotencyKey() {
    final random = Random.secure();
    return 'paynow-${DateTime.now().millisecondsSinceEpoch}-${random.nextInt(1 << 32)}';
  }

  Future<void> _createIntent() async {
    setState(() {
      _busy = true;
      _error = null;
      _settledReceipt = null;
    });

    try {
      final body = <String, dynamic>{
        'account_id': widget.account.id,
        'gateway': 'paynow',
        'payment_method': _paymentMethod,
        'phone': _phone.text.trim(),
        'idempotency_key': _idempotencyKey(),
      };

      if (_billId != null && _billId != 'custom') {
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

  Future<void> _simulateSuccess() async {
    final ref = _intent?['reference']?.toString();
    if (ref == null) return;

    setState(() => _simulating = true);
    try {
      final response = await widget.api.post('/api/v1/sandbox/paynow/complete/$ref', {});
      if (mounted) {
        setState(() {
          _settledReceipt = response;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Payment verified! Official receipt issued.'),
            backgroundColor: Color(0xFF0D5C3A),
          ),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _simulating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final checkout = _intent?['checkout'] as Map<String, dynamic>?;

    return Scaffold(
      appBar: AppBar(title: const Text('Council Payments (Paynow)')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Banner
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFF0FDF4),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: const Color(0xFF86EFAC)),
            ),
            child: Row(
              children: [
                const Icon(Icons.shield_outlined, color: Color(0xFF0D5C3A)),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Instant council settlement via Paynow (EcoCash, OneMoney, Zimswitch & Cards).',
                    style: TextStyle(fontSize: 12, color: Colors.green.shade900, fontWeight: FontWeight.w500),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const Text('Select Bill or Service to Pay', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          const SizedBox(height: 8),
          RadioGroup<String>(
            groupValue: _billId,
            onChanged: (value) => setState(() {
              _billId = value;
              _intent = null;
              _settledReceipt = null;
            }),
            child: Column(
              children: [
                for (final bill in widget.bills)
                  RadioListTile<String>(
                    value: bill.id,
                    title: Text('${bill.billNumber} • ${bill.serviceType}'),
                    subtitle: Text('Balance ${bill.balanceLabel}'),
                  ),
                const RadioListTile<String>(
                  value: 'custom',
                  title: Text('Other / Custom Amount'),
                  subtitle: Text('Pay an advance or custom rates amount'),
                ),
              ],
            ),
          ),
          if (_billId == null || _billId == 'custom') ...[
            const SizedBox(height: 8),
            TextField(
              controller: _amount,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'Amount (USD)',
                hintText: 'e.g. 25.00',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.attach_money),
              ),
            ),
          ],
          const SizedBox(height: 18),
          const Text('Choose Payment Channel', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          const SizedBox(height: 10),
          Row(
            children: [
              _buildChannelOption('ecocash', 'EcoCash', Icons.phone_android, const Color(0xFF0D5C3A)),
              const SizedBox(width: 8),
              _buildChannelOption('onemoney', 'OneMoney', Icons.cell_tower, const Color(0xFFE65100)),
              const SizedBox(width: 8),
              _buildChannelOption('card', 'Visa/Mastercard', Icons.credit_card, const Color(0xFF1565C0)),
            ],
          ),
          if (_paymentMethod != 'card') ...[
            const SizedBox(height: 16),
            TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: '$_paymentMethod Mobile Number (USSD Push Prompt)',
                hintText: '077... or 071...',
                border: const OutlineInputBorder(),
                prefixIcon: const Icon(Icons.dialpad),
              ),
            ),
          ],
          const SizedBox(height: 16),
          if (_error != null)
            Container(
              padding: const EdgeInsets.all(12),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(color: const Color(0xFFFEE2E2), borderRadius: BorderRadius.circular(8)),
              child: Text(_error!, style: const TextStyle(color: Color(0xFF991B1B))),
            ),
          SizedBox(
            height: 48,
            child: FilledButton.icon(
              onPressed: _busy ? null : _createIntent,
              icon: const Icon(Icons.payment),
              label: _busy ? const CircularProgressIndicator(color: Colors.white) : const Text('Authorize with Paynow'),
              style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0D5C3A)),
            ),
          ),
          if (_intent != null) ...[
            const SizedBox(height: 20),
            Card(
              elevation: 3,
              color: const Color(0xFFF0FDF4),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.pending_actions, color: Color(0xFF0D5C3A)),
                        const SizedBox(width: 8),
                        Text(
                          _settledReceipt != null ? 'Payment Succeeded!' : 'Paynow Authorization Prompt',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                      ],
                    ),
                    const Divider(height: 20),
                    Text('Reference: ${_intent!['reference']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                    if (checkout?['paynow_reference'] != null)
                      Text('Paynow Ref: ${checkout!['paynow_reference']}', style: TextStyle(color: Colors.grey.shade700)),
                    Text('Amount: USD ${(int.parse(_intent!['amount_minor'].toString()) / 100).toStringAsFixed(2)}'),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFBBF7D0)),
                      ),
                      child: Text(
                        checkout?['instructions']?.toString() ?? 'Follow prompts on your mobile device.',
                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
                      ),
                    ),
                    if (_settledReceipt != null) ...[
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFDCFCE7),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFF22C55E)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Row(
                              children: [
                                Icon(Icons.check_circle, color: Color(0xFF15803D), size: 18),
                                SizedBox(width: 6),
                                Text('Verified Municipal Receipt Issued', style: TextStyle(color: Color(0xFF15803D), fontWeight: FontWeight.bold)),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text('Payment Number: ${_settledReceipt!['payment_number']}'),
                            Text('Receipt Number: ${_settledReceipt!['receipt_number']}'),
                          ],
                        ),
                      ),
                    ] else ...[
                      const SizedBox(height: 16),
                      // Testing sandbox simulator button
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: _simulating ? null : _simulateSuccess,
                          icon: _simulating ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.touch_app),
                          label: const Text('Simulate Handset PIN Confirmation (Sandbox)'),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildChannelOption(String key, String label, IconData icon, Color color) {
    final isSelected = _paymentMethod == key;
    return Expanded(
      child: InkWell(
        onTap: () => setState(() => _paymentMethod = key),
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          decoration: BoxDecoration(
            color: isSelected ? color.withValues(alpha: 0.12) : Colors.grey.shade50,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: isSelected ? color : Colors.grey.shade300,
              width: isSelected ? 2 : 1,
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, color: isSelected ? color : Colors.grey.shade600, size: 24),
              const SizedBox(height: 4),
              Text(
                label,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                  color: isSelected ? color : Colors.black87,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
