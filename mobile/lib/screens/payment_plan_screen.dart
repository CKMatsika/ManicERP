import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../models/models.dart';

class PaymentPlanScreen extends StatefulWidget {
  const PaymentPlanScreen({super.key, required this.api, required this.account});

  final ApiClient api;
  final Account account;

  @override
  State<PaymentPlanScreen> createState() => _PaymentPlanScreenState();
}

class _PaymentPlanScreenState extends State<PaymentPlanScreen> {
  bool _loading = true;
  String? _error;
  PaymentPlanDetails? _activePlan;
  List<PaymentPlanOption> _options = [];
  int _selectedMonths = 6;
  bool _acknowledged = false;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final activeRes = await widget.api.get('/api/v1/credit-control/active-plan');
      final activeData = activeRes['data'];
      PaymentPlanDetails? plan;
      if (activeData != null && activeData is Map<String, dynamic>) {
        plan = PaymentPlanDetails.fromJson(activeData);
      }

      final optionsRes = await widget.api.get('/api/v1/credit-control/plan-options');
      final optionsRaw = (optionsRes['data']?['options'] as List<dynamic>? ?? const []);
      final options = optionsRaw.map((e) => PaymentPlanOption.fromJson(e as Map<String, dynamic>)).toList();

      if (mounted) {
        setState(() {
          _activePlan = plan;
          _options = options;
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _loading = false;
        });
      }
    }
  }

  Future<void> _createPlan() async {
    if (!_acknowledged) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please acknowledge the statutory agreement terms.')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final res = await widget.api.post('/api/v1/credit-control/payment-plans', {
        'months': _selectedMonths,
        'notes': 'Statutory payment agreement under Urban Councils Act s279.',
      });

      if (mounted) {
        final planData = res['data'];
        if (planData != null && planData is Map<String, dynamic>) {
          setState(() {
            _activePlan = PaymentPlanDetails.fromJson(planData);
          });
        }
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            backgroundColor: Color(0xFF0D5C3A),
            content: Text('Payment plan approved! Disconnection immunity is now active.'),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(backgroundColor: Colors.red.shade700, content: Text('Error: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _showPayDialog(PaymentPlanInstallmentItem installment) async {
    String paymentMethod = 'ecocash';
    final phoneController = TextEditingController(text: '0771234567');
    bool processing = false;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (sheetContext, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Pay Installment #${installment.installmentNumber}',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                      ),
                      IconButton(onPressed: () => Navigator.pop(ctx), icon: const Icon(Icons.close)),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Due: ${installment.dueDate} • Amount: USD ${installment.amountUsd}',
                    style: TextStyle(color: Colors.grey.shade700, fontSize: 14),
                  ),
                  const Divider(height: 24),
                  const Text('Select Payment Method (Paynow Gateway)', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      _paymentChip('ecocash', 'EcoCash', Icons.phone_android, paymentMethod, (val) {
                        setModalState(() => paymentMethod = val);
                      }),
                      const SizedBox(width: 8),
                      _paymentChip('onemoney', 'OneMoney', Icons.phone_iphone, paymentMethod, (val) {
                        setModalState(() => paymentMethod = val);
                      }),
                      const SizedBox(width: 8),
                      _paymentChip('card', 'Zimswitch / Visa', Icons.credit_card, paymentMethod, (val) {
                        setModalState(() => paymentMethod = val);
                      }),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if (paymentMethod != 'card') ...[
                    TextField(
                      controller: phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: InputDecoration(
                        labelText: '$paymentMethod Mobile Number',
                        prefixIcon: const Icon(Icons.phone),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: processing
                          ? null
                          : () async {
                              setModalState(() => processing = true);
                              try {
                                final key = 'inst-${installment.id}-${DateTime.now().millisecondsSinceEpoch}';
                                final res = await widget.api.post(
                                  '/api/v1/credit-control/installments/${installment.id}/pay',
                                  {
                                    'gateway': 'paynow',
                                    'payment_method': paymentMethod,
                                    'phone': phoneController.text.trim(),
                                    'idempotency_key': key,
                                  },
                                );

                                final reference = res['data']?['reference']?.toString();
                                if (reference != null) {
                                  // Instantly settle in sandbox / demo environment
                                  await widget.api.post('/api/v1/sandbox/paynow/complete/$reference', {});
                                }

                                if (ctx.mounted) {
                                  Navigator.pop(ctx);
                                }
                                if (mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(
                                      backgroundColor: Color(0xFF0D5C3A),
                                      content: Text('Payment verified! Council receipt issued and installment marked PAID.'),
                                    ),
                                  );
                                  _fetchData();
                                }
                              } catch (err) {
                                setModalState(() => processing = false);
                                if (mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(backgroundColor: Colors.red.shade700, content: Text('Error: $err')),
                                  );
                                }
                              }
                            },
                      icon: processing
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Icon(Icons.verified),
                      label: Text(processing ? 'Processing Paynow Prompt...' : 'Pay USD ${installment.amountUsd} Now'),
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFF0D5C3A),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _paymentChip(String id, String label, IconData icon, String selected, ValueChanged<String> onSelected) {
    final isSelected = selected == id;
    return Expanded(
      child: InkWell(
        onTap: () => onSelected(id),
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
          decoration: BoxDecoration(
            color: isSelected ? const Color(0xFF0D5C3A).withValues(alpha: 0.12) : Colors.grey.shade100,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: isSelected ? const Color(0xFF0D5C3A) : Colors.grey.shade300,
              width: isSelected ? 1.8 : 1.0,
            ),
          ),
          child: Column(
            children: [
              Icon(icon, size: 20, color: isSelected ? const Color(0xFF0D5C3A) : Colors.grey.shade700),
              const SizedBox(height: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                  color: isSelected ? const Color(0xFF0D5C3A) : Colors.grey.shade800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF8),
      appBar: AppBar(
        title: const Text('Municipal Debt Relief & Plans'),
        backgroundColor: const Color(0xFF0D5C3A),
        foregroundColor: Colors.white,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF0D5C3A)))
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: Colors.red),
                        const SizedBox(height: 12),
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        FilledButton(onPressed: _fetchData, child: const Text('Retry')),
                      ],
                    ),
                  ),
                )
              : _activePlan != null
                  ? _buildActivePlanView(_activePlan!)
                  : _buildApplyPlanView(),
    );
  }

  Widget _buildActivePlanView(PaymentPlanDetails plan) {
    final progress = plan.installmentCount > 0 ? plan.paidInstallments / plan.installmentCount : 0.0;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Disconnection Immunity Card
        Container(
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF065F46), Color(0xFF047857)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF047857).withValues(alpha: 0.25),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.shield_outlined, color: Colors.white, size: 28),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'DISCONNECTION IMMUNITY ACTIVE',
                          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13, letterSpacing: 0.5),
                        ),
                        Text(
                          'Protected under Urban Councils Act [Cap 29:15] s279',
                          style: TextStyle(color: Color(0xFFD1FAE5), fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Text(
                'Settlement Reference: ${plan.planNumber}',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
              ),
              Text(
                'Council Resolution: ${plan.resolutionRef} (50% Interest Amnesty Applied)',
                style: const TextStyle(color: Color(0xFFD1FAE5), fontSize: 12),
              ),
              const SizedBox(height: 14),
              LinearProgressIndicator(
                value: progress,
                backgroundColor: Colors.white.withValues(alpha: 0.2),
                valueColor: const AlwaysStoppedAnimation<Color>(Color(0xFFFDE047)),
                minHeight: 8,
                borderRadius: BorderRadius.circular(4),
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    '${plan.paidInstallments} of ${plan.installmentCount} Installments Paid',
                    style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                  ),
                  Text(
                    '${(progress * 100).toInt()}% Complete',
                    style: const TextStyle(color: Color(0xFFFDE047), fontSize: 12, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // Financial Summary Matrix
        Card(
          elevation: 1,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Amnesty & Settlement Financials', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                const SizedBox(height: 12),
                Row(
                  children: [
                    _metricItem('Total Arrears', 'USD ${plan.totalArrearsUsd}', Colors.black87),
                    _metricItem('50% Waived', 'USD ${plan.interestWaivedUsd}', const Color(0xFF16A34A)),
                    _metricItem('Net Payable', 'USD ${plan.netPayableUsd}', const Color(0xFF0D5C3A)),
                  ],
                ),
                const Divider(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total Settled to Date:', style: TextStyle(fontSize: 13, color: Colors.grey)),
                    Text('USD ${plan.totalPaidUsd}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0D5C3A))),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Next Installment Callout
        if (plan.nextInstallment != null) ...[
          Card(
            color: const Color(0xFFFFFBEB),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
              side: const BorderSide(color: Color(0xFFFCD34D)),
            ),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Next Due Installment (#${plan.nextInstallment!.installmentNumber})',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF92400E)),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF3C7),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFF59E0B)),
                        ),
                        child: Text(
                          'Due ${plan.nextInstallment!.dueDate}',
                          style: const TextStyle(color: Color(0xFFB45309), fontSize: 11, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'USD ${plan.nextInstallment!.amountUsd}',
                    style: const TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: Color(0xFF78350F)),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: () => _showPayDialog(plan.nextInstallment!),
                      icon: const Icon(Icons.flash_on, size: 18),
                      label: const Text('Pay Installment via Paynow (EcoCash / Card)'),
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFFD97706),
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
        ],

        // Schedule of Installments
        const Text('Complete Installment Schedule', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
        const SizedBox(height: 8),
        for (final item in plan.installments)
          Card(
            margin: const EdgeInsets.only(bottom: 8),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: item.isPaid ? const Color(0xFFDCFCE7) : Colors.grey.shade100,
                foregroundColor: item.isPaid ? const Color(0xFF16A34A) : Colors.grey.shade700,
                child: Icon(item.isPaid ? Icons.check : Icons.calendar_today, size: 18),
              ),
              title: Text(
                'Installment #${item.installmentNumber} • USD ${item.amountUsd}',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
              subtitle: Text(
                item.isPaid ? 'Paid on ${item.paidAt?.split('T').first ?? ''}' : 'Due date: ${item.dueDate}',
                style: TextStyle(fontSize: 12, color: item.isPaid ? const Color(0xFF16A34A) : Colors.grey.shade600),
              ),
              trailing: item.isPaid
                  ? const Chip(
                      label: Text('PAID', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF16A34A))),
                      backgroundColor: Color(0xFFDCFCE7),
                      padding: EdgeInsets.zero,
                    )
                  : OutlinedButton(
                      onPressed: () => _showPayDialog(item),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFF0D5C3A),
                        side: const BorderSide(color: Color(0xFF0D5C3A)),
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      ),
                      child: const Text('Pay', style: TextStyle(fontSize: 12)),
                    ),
            ),
          ),
      ],
    );
  }

  Widget _buildApplyPlanView() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Amnesty Banner
        Container(
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF0D5C3A), Color(0xFF144A32)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
          ),
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFFDE047),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  'COUNCIL RESOLUTION FC/89/2026',
                  style: TextStyle(color: Color(0xFF78350F), fontSize: 10, fontWeight: FontWeight.bold),
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                '50% Interest Amnesty & Disconnection Immunity',
                style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 6),
              const Text(
                'Demo City Council is offering a 50% write-off on accumulated interest for ratepayers who commit to a structured payment agreement under Section 279 of the Urban Councils Act.',
                style: TextStyle(color: Color(0xFFE2E8F0), fontSize: 12),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // Outstanding Balance
        Card(
          elevation: 1,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Current Total Arrears', style: TextStyle(color: Colors.grey, fontSize: 12)),
                    const SizedBox(height: 2),
                    Text(widget.account.outstandingLabel, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 20)),
                  ],
                ),
                const Icon(Icons.account_balance_wallet_outlined, size: 32, color: Color(0xFF0D5C3A)),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        const Text('Choose Repayment Plan Duration', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
        const SizedBox(height: 10),

        for (final opt in _options)
          _buildOptionCard(opt),

        const SizedBox(height: 16),

        // Legal Acknowledgment
        Card(
          color: const Color(0xFFEFF6FF),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Checkbox(
                  value: _acknowledged,
                  onChanged: (val) => setState(() => _acknowledged = val ?? false),
                  activeColor: const Color(0xFF0D5C3A),
                ),
                Expanded(
                  child: Text(
                    'I hereby acknowledge the municipal arrears under Section 279 of the Urban Councils Act [Chapter 29:15] and agree to the payment plan terms. I understand that adherence protects my premises from disconnection.',
                    style: TextStyle(fontSize: 12, color: Colors.blue.shade900),
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            onPressed: (_submitting || !_acknowledged) ? null : _createPlan,
            icon: _submitting
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Icon(Icons.verified_user),
            label: Text(_submitting ? 'Registering Agreement...' : 'Sign Agreement & Activate Immunity'),
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFF0D5C3A),
              padding: const EdgeInsets.symmetric(vertical: 14),
              textStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
          ),
        ),
        const SizedBox(height: 24),
      ],
    );
  }

  Widget _buildOptionCard(PaymentPlanOption opt) {
    final isSelected = _selectedMonths == opt.months;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: isSelected ? 3 : 1,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(
          color: isSelected ? const Color(0xFF0D5C3A) : Colors.grey.shade200,
          width: isSelected ? 2 : 1,
        ),
      ),
      child: InkWell(
        onTap: () => setState(() => _selectedMonths = opt.months),
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Icon(
                        isSelected ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                        color: isSelected ? const Color(0xFF0D5C3A) : Colors.grey,
                        size: 22,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '${opt.months} Months Plan',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: const Color(0xFFDCFCE7),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      'Save USD ${opt.interestWaiverUsd}',
                      style: const TextStyle(color: Color(0xFF15803D), fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
              const Divider(),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Monthly Installment', style: TextStyle(color: Colors.grey, fontSize: 11)),
                      Text('USD ${opt.monthlyInstallmentUsd} / mo', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0D5C3A))),
                    ],
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      const Text('Net Payable', style: TextStyle(color: Colors.grey, fontSize: 11)),
                      Text('USD ${opt.netPayableUsd}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _metricItem(String label, String value, Color color) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontSize: 11, color: Colors.grey)),
          const SizedBox(height: 2),
          Text(value, style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: color)),
        ],
      ),
    );
  }
}
