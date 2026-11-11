import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../models/models.dart';
import 'pay_screen.dart';
import 'payment_plan_screen.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key, required this.api, required this.account});

  final ApiClient api;
  final Account account;

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  late Future<List<Bill>> _bills;
  late Future<List<PaymentReceipt>> _payments;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _bills = _loadBills();
    _payments = _loadPayments();
  }

  Future<List<Bill>> _loadBills() async {
    final response = await widget.api.get('/api/v1/accounts/${widget.account.id}/bills');
    final data = (response['data'] as List<dynamic>? ?? const []);
    return data.map((item) => Bill.fromJson(item as Map<String, dynamic>)).toList();
  }

  Future<List<PaymentReceipt>> _loadPayments() async {
    final response = await widget.api.get('/api/v1/accounts/${widget.account.id}/payments');
    final data = (response['data'] as List<dynamic>? ?? const []);
    return data.map((item) => PaymentReceipt.fromJson(item as Map<String, dynamic>)).toList();
  }

  void _refresh() => setState(_load);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.account.accountNumber, style: const TextStyle(fontSize: 16))),
      body: RefreshIndicator(
        onRefresh: () async => _refresh(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Outstanding', style: TextStyle(fontSize: 12)),
                    Text(widget.account.outstandingLabel, style: Theme.of(context).textTheme.headlineSmall),
                    const SizedBox(height: 4),
                    Text(widget.account.property ?? 'No linked property', style: const TextStyle(fontSize: 12)),
                    if (widget.account.outstandingMinor > 0) ...[
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: () async {
                            await Navigator.of(context).push(MaterialPageRoute(
                              builder: (_) => PaymentPlanScreen(api: widget.api, account: widget.account),
                            ));
                            _refresh();
                          },
                          icon: const Icon(Icons.shield_outlined, size: 16),
                          label: const Text('Municipal Debt Relief & Payment Plan (s279)'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: const Color(0xFF0D5C3A),
                            side: const BorderSide(color: Color(0xFF0D5C3A)),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
            FutureBuilder<List<Bill>>(
              future: _bills,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator()));
                }
                if (snapshot.hasError) {
                  return Text('Could not load bills: ${snapshot.error}');
                }

                final bills = snapshot.data ?? [];
                final payable = bills.where((bill) => bill.isPayable).toList();

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Bills', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                        TextButton(
                          onPressed: payable.isEmpty
                              ? null
                              : () async {
                                  await Navigator.of(context).push(MaterialPageRoute(
                                    builder: (_) => PayScreen(api: widget.api, account: widget.account, bills: payable),
                                  ));
                                  _refresh();
                                },
                          child: const Text('Pay'),
                        ),
                      ],
                    ),
                    if (bills.isEmpty) const Text('No bills on this account yet.'),
                    for (final bill in bills)
                      Card(
                        child: ListTile(
                          title: Text('${bill.billNumber} • ${bill.serviceType}'),
                          subtitle: Text('Due ${bill.dueDate ?? '—'} • ${bill.status}'),
                          trailing: Text(bill.balanceLabel, style: const TextStyle(fontWeight: FontWeight.w600)),
                        ),
                      ),
                    const SizedBox(height: 12),
                    const Text('Verified payments and receipts', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                    FutureBuilder<List<PaymentReceipt>>(
                      future: _payments,
                      builder: (context, snapshot) {
                        if (snapshot.connectionState == ConnectionState.waiting) {
                          return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()));
                        }
                        if (snapshot.hasError) {
                          return Text('Could not load payments: ${snapshot.error}');
                        }
                        final payments = snapshot.data ?? [];
                        if (payments.isEmpty) {
                          return const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('No verified payments yet.'));
                        }
                        return Column(
                          children: [
                            for (final payment in payments)
                              ListTile(
                                dense: true,
                                title: Text(payment.receiptNumber ?? payment.paymentNumber, style: const TextStyle(fontFamily: 'monospace', fontSize: 13)),
                                subtitle: Text(payment.receivedAt ?? ''),
                                trailing: Text('USD ${(payment.amountMinor / 100).toStringAsFixed(2)}'),
                              ),
                          ],
                        );
                      },
                    ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
