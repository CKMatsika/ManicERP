import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../models/models.dart';

class WaitingListScreen extends StatefulWidget {
  const WaitingListScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<WaitingListScreen> createState() => _WaitingListScreenState();
}

class _WaitingListScreenState extends State<WaitingListScreen> {
  late Future<_WaitingListData> _data;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _data = _fetchData();
  }

  Future<_WaitingListData> _fetchData() async {
    final results = await Future.wait([
      widget.api.get('/api/v1/waiting-list').catchError((_) => {'data': []}),
      widget.api.get('/api/v1/waiting-list/offer').catchError((_) => {'data': null}),
    ]);

    final entriesRaw = (results[0]['data'] as List<dynamic>? ?? const []);
    final entries = entriesRaw.map((e) => HousingWaitingList.fromJson(e as Map<String, dynamic>)).toList();

    final offerRaw = results[1]['data'] as Map<String, dynamic>?;
    final offer = offerRaw != null ? HousingAllocationOffer.fromJson(offerRaw) : null;

    return _WaitingListData(entries: entries, offer: offer);
  }

  void _refresh() {
    setState(() {
      _data = _fetchData();
    });
  }

  Future<void> _renew(HousingWaitingList item) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Renew Waiting List'),
        content: Text(
          'Annual renewal fee is ${item.renewalFeeLabel}. This extends your waiting list registration for 12 months and adds +5 priority points.\n\nProceed with renewal?',
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Confirm Renewal')),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _busy = true);
    try {
      final res =
          await widget.api.post('/api/v1/waiting-list/${item.id}/renew', {});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message']?.toString() ??
                'Waiting list renewed successfully!'),
            backgroundColor: const Color(0xFF0D5C3A),
          ),
        );
        _refresh();
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _acceptOffer(HousingAllocationOffer offer) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Accept Stand Allocation Offer'),
        content: Text(
          'You are accepting provisional allocation of Stand ${offer.standNumber} in ${offer.suburb} (${offer.areaSqm} m²).\n\nTotal Price: USD \$${offer.purchasePriceUsd}\nDeposit Required: USD \$${offer.depositRequiredUsd}\n\nAccept offer to proceed to deposit payment?',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0D5C3A)),
            child: const Text('Accept Offer'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _busy = true);
    try {
      final res = await widget.api.post('/api/v1/waiting-list/offer/${offer.id}/accept', {});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message']?.toString() ?? 'Offer accepted! You may now pay the deposit.'),
            backgroundColor: const Color(0xFF0D5C3A),
          ),
        );
        _refresh();
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _declineOffer(HousingAllocationOffer offer) async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Decline Stand Allocation'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Declining this offer will return your application to the active waiting list queue without penalty.',
              style: TextStyle(fontSize: 13, color: Colors.grey),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              decoration: const InputDecoration(labelText: 'Reason for declining *', border: OutlineInputBorder()),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Decline Offer'),
          ),
        ],
      ),
    );

    if (reason == null || reason.isEmpty) return;

    setState(() => _busy = true);
    try {
      final res = await widget.api.post('/api/v1/waiting-list/offer/${offer.id}/reject', {'reason': reason});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message']?.toString() ?? 'Offer declined. Returned to waiting list.'),
            backgroundColor: Colors.blueGrey,
          ),
        );
        _refresh();
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _payDeposit(HousingAllocationOffer offer) async {
    setState(() => _busy = true);
    try {
      final res = await widget.api.post('/api/v1/waiting-list/offer/${offer.id}/deposit', {
        'gateway': 'paynow',
        'payment_method': 'ecocash',
        'phone': '0774123456',
        'idempotency_key': 'stand-deposit-${offer.id}-${DateTime.now().millisecondsSinceEpoch}',
      });

      final data = res['data'] as Map<String, dynamic>;
      final checkout = data['checkout'] as Map<String, dynamic>?;

      if (!mounted) return;
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Paynow Stand Deposit'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Reference: ${data['reference']}', style: const TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              Text(checkout?['instructions']?.toString() ?? 'Follow prompts on your mobile device.'),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () async {
                  Navigator.pop(ctx);
                  // Simulate success in sandbox
                  await widget.api.post('/api/v1/sandbox/paynow/complete/${data['reference']}', {});
                  _refresh();
                },
                icon: const Icon(Icons.touch_app),
                label: const Text('Simulate Handset PIN (Sandbox)'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
          ],
        ),
      );
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _openApplicationSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _ApplicationBottomSheet(
        api: widget.api,
        onSubmitted: () {
          Navigator.pop(ctx);
          _refresh();
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Housing Waiting List & Stands'),
        actions: [
          IconButton(
              onPressed: _refresh,
              icon: const Icon(Icons.refresh),
              tooltip: 'Refresh'),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openApplicationSheet,
        icon: const Icon(Icons.add_home_work_outlined),
        label: const Text('Apply for Stand'),
        backgroundColor: const Color(0xFF0D5C3A),
        foregroundColor: Colors.white,
      ),
      body: FutureBuilder<_WaitingListData>(
        future: _data,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: Color(0xFF0D5C3A)));
          }

          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.error_outline,
                        size: 48, color: Colors.red),
                    const SizedBox(height: 12),
                    Text(snapshot.error.toString(),
                        textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    FilledButton(
                        onPressed: _refresh, child: const Text('Retry')),
                  ],
                ),
              ),
            );
          }

          final data = snapshot.data ?? _WaitingListData(entries: [], offer: null);
          final offer = data.offer;
          final list = data.entries;

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // 1. ACTIVE STAND ALLOCATION OFFER BANNER
              if (offer != null) ...[
                _OfferCard(
                  offer: offer,
                  isBusy: _busy,
                  onAccept: () => _acceptOffer(offer),
                  onDecline: () => _declineOffer(offer),
                  onPayDeposit: () => _payDeposit(offer),
                ),
                const SizedBox(height: 20),
              ],

              // 2. WAITING LIST REGISTRATION DETAILS
              if (list.isEmpty && offer == null)
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE8F5E9),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFA5D6A7)),
                  ),
                  child: Column(
                    children: [
                      const Icon(Icons.maps_home_work,
                          size: 64, color: Color(0xFF0D5C3A)),
                      const SizedBox(height: 16),
                      Text('No Active Waiting List Registration',
                          style: theme.textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      const Text(
                        'In accordance with the Urban Councils Act [Chapter 29:15], council maintains a transparent municipal housing waiting list for residential and commercial stands.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.black87),
                      ),
                      const SizedBox(height: 20),
                      FilledButton.icon(
                        onPressed: _openApplicationSheet,
                        icon: const Icon(Icons.add_circle_outline),
                        label: const Text('Register for a Municipal Stand'),
                        style: FilledButton.styleFrom(
                            backgroundColor: const Color(0xFF0D5C3A)),
                      ),
                    ],
                  ),
                )
              else
                for (final item in list) ...[
                  _WaitingListCard(
                    item: item,
                    isBusy: _busy,
                    onRenew: () => _renew(item),
                  ),
                  const SizedBox(height: 16),
                ],
            ],
          );
        },
      ),
    );
  }
}

class _OfferCard extends StatelessWidget {
  const _OfferCard({
    required this.offer,
    required this.isBusy,
    required this.onAccept,
    required this.onDecline,
    required this.onPayDeposit,
  });

  final HousingAllocationOffer offer;
  final bool isBusy;
  final VoidCallback onAccept;
  final VoidCallback onDecline;
  final VoidCallback onPayDeposit;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          gradient: const LinearGradient(
            colors: [Color(0xFF064E3B), Color(0xFF0D5C3A)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        padding: const EdgeInsets.all(20),
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
                  child: const Icon(Icons.celebration, color: Color(0xFFD4A373), size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'COUNCIL STAND ALLOCATION OFFER',
                        style: TextStyle(color: Color(0xFFD4A373), fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 1),
                      ),
                      Text(
                        'Stand ${offer.standNumber} • ${offer.suburb}',
                        style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    offer.status.toUpperCase().replaceAll('_', ' '),
                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
            const Divider(color: Colors.white24, height: 24),
            // Stand details grid
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Stand Area', style: TextStyle(color: Colors.white70, fontSize: 11)),
                      Text('${offer.areaSqm} m²', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                    ],
                  ),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Total Purchase Price', style: TextStyle(color: Colors.white70, fontSize: 11)),
                      Text('USD \$${offer.purchasePriceUsd}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                    ],
                  ),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Deposit Required', style: TextStyle(color: Colors.white70, fontSize: 11)),
                      Text('USD \$${offer.depositRequiredUsd}', style: const TextStyle(color: Color(0xFFD4A373), fontWeight: FontWeight.bold, fontSize: 14)),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: 0.18),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.timer_outlined, color: Color(0xFFD4A373), size: 16),
                      const SizedBox(width: 6),
                      Text(
                        'Offer expires on ${offer.offerExpiresAt} (${offer.daysRemaining} days remaining)',
                        style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Standard covenants: Complete foundation slab within 12 months, building to window level within 24 months.',
                    style: TextStyle(color: Colors.white70, fontSize: 11),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            // Actions
            if (offer.isOfferIssued) ...[
              Row(
                children: [
                  Expanded(
                    flex: 3,
                    child: FilledButton.icon(
                      onPressed: isBusy ? null : onAccept,
                      icon: const Icon(Icons.check, size: 18),
                      label: const Text('Accept Stand Offer'),
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFFD4A373),
                        foregroundColor: const Color(0xFF1B3D2F),
                        textStyle: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    flex: 2,
                    child: OutlinedButton(
                      onPressed: isBusy ? null : onDecline,
                      style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: const BorderSide(color: Colors.white38)),
                      child: const Text('Decline'),
                    ),
                  ),
                ],
              ),
            ] else if (offer.isAccepted && !offer.isDepositPaid) ...[
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: isBusy ? null : onPayDeposit,
                  icon: const Icon(Icons.flash_on, size: 18),
                  label: Text('Pay Stand Deposit (\$${offer.depositRequiredUsd}) via Paynow'),
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFFD4A373),
                    foregroundColor: const Color(0xFF1B3D2F),
                    textStyle: const TextStyle(fontWeight: FontWeight.bold),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
            ] else if (offer.isDepositPaid) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.check_circle, color: Color(0xFF86EFAC), size: 18),
                    SizedBox(width: 8),
                    Text(
                      'Deposit Verified. Lease Agreement in Preparation.',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _WaitingListCard extends StatelessWidget {
  const _WaitingListCard({
    required this.item,
    required this.isBusy,
    required this.onRenew,
  });

  final HousingWaitingList item;
  final bool isBusy;
  final VoidCallback onRenew;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: item.isRenewalDue
                ? const Color(0xFFF59E0B)
                : const Color(0xFFE2E8F0),
            width: item.isRenewalDue ? 1.5 : 1,
          ),
        ),
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.waitingListNumber,
                          style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.bold, letterSpacing: 0.5)),
                      const SizedBox(height: 2),
                      Text(item.categoryLabel,
                          style: TextStyle(
                              color: Colors.grey.shade700, fontSize: 13)),
                    ],
                  ),
                ),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: item.status == 'allocated'
                        ? const Color(0xFFDCFCE7)
                        : (item.status == 'active' ? const Color(0xFFE8F5E9) : const Color(0xFFFEF3C7)),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    item.status.toUpperCase(),
                    style: TextStyle(
                      color: item.status == 'allocated' ? const Color(0xFF15803D) : const Color(0xFF1B5E20),
                      fontWeight: FontWeight.bold,
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFF0FDF4),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFBBF7D0)),
              ),
              child: Row(
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: const BoxDecoration(
                      color: Color(0xFF0D5C3A),
                      shape: BoxShape.circle,
                    ),
                    child: Center(
                      child: Text(
                        '#${item.queuePosition}',
                        style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 18),
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Current Queue Position',
                            style: theme.textTheme.labelMedium
                                ?.copyWith(color: Colors.grey.shade700)),
                        Text(
                            'Position ${item.queuePosition} of ${item.totalInQueue} applicants',
                            style: const TextStyle(
                                fontWeight: FontWeight.bold, fontSize: 14)),
                        const SizedBox(height: 2),
                        Text('${item.priorityPoints} Priority Merit Points',
                            style: const TextStyle(
                                color: Color(0xFF0D5C3A),
                                fontWeight: FontWeight.w600,
                                fontSize: 12)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                const Icon(Icons.person_outline, size: 16, color: Colors.grey),
                const SizedBox(width: 6),
                Text(item.fullName,
                    style: const TextStyle(
                        fontSize: 13, fontWeight: FontWeight.w500)),
                const Spacer(),
                const Icon(Icons.badge_outlined, size: 16, color: Colors.grey),
                const SizedBox(width: 6),
                Text(item.nationalId, style: const TextStyle(fontSize: 13)),
              ],
            ),
            if (item.preferredSuburbs.isNotEmpty) ...[
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 4,
                children: item.preferredSuburbs
                    .map((suburb) => Chip(
                          label: Text(suburb,
                              style: const TextStyle(fontSize: 11)),
                          visualDensity: VisualDensity.compact,
                          backgroundColor: const Color(0xFFF1F5F9),
                          padding: EdgeInsets.zero,
                          side: BorderSide.none,
                        ))
                    .toList(),
              ),
            ],
            const Divider(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Annual Renewal Expiry',
                        style: TextStyle(
                            color: Colors.grey.shade600, fontSize: 11)),
                    Text(item.expiryDate,
                        style: const TextStyle(
                            fontWeight: FontWeight.bold, fontSize: 13)),
                    Text(
                      '${item.daysUntilRenewal} days remaining',
                      style: TextStyle(
                        color: item.isRenewalDue
                            ? Colors.deepOrange
                            : Colors.grey.shade700,
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
                FilledButton.tonalIcon(
                  onPressed: isBusy ? null : onRenew,
                  icon: const Icon(Icons.autorenew, size: 18),
                  label: Text('Renew (${item.renewalFeeLabel})'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ApplicationBottomSheet extends StatefulWidget {
  const _ApplicationBottomSheet({required this.api, required this.onSubmitted});

  final ApiClient api;
  final VoidCallback onSubmitted;

  @override
  State<_ApplicationBottomSheet> createState() =>
      _ApplicationBottomSheetState();
}

class _ApplicationBottomSheetState extends State<_ApplicationBottomSheet> {
  final _formKey = GlobalKey<FormState>();
  final _fullName = TextEditingController();
  final _nationalId = TextEditingController();
  final _phone = TextEditingController();
  final _dependents = TextEditingController(text: '2');
  final _income = TextEditingController(text: '500');

  String _category = 'high_density';
  String _maritalStatus = 'married';
  String _employment = 'employed';
  bool _busy = false;

  final Map<String, List<String>> _categorySuburbs = {
    'high_density': ['Dangamvura', 'Chikanga', 'Hobhouse', 'Sakubva'],
    'medium_density': ['Yeovil', 'Florida', 'Fairbridge Park', 'Darlington'],
    'low_density': ['Morningside', 'Murambi', 'Palmerstone', 'Bordervale'],
    'commercial': ['Central Business District', 'Commercial Service Centres'],
  };

  @override
  void dispose() {
    _fullName.dispose();
    _nationalId.dispose();
    _phone.dispose();
    _dependents.dispose();
    _income.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _busy = true);
    try {
      await widget.api.post('/api/v1/waiting-list', {
        'full_name': _fullName.text.trim(),
        'national_id': _nationalId.text.trim().toUpperCase(),
        'phone_number': _phone.text.trim(),
        'category': _category,
        'preferred_suburbs': _categorySuburbs[_category] ?? [],
        'marital_status': _maritalStatus,
        'number_of_dependents': int.tryParse(_dependents.text.trim()) ?? 0,
        'employment_status': _employment,
        'monthly_income_minor':
            ((double.tryParse(_income.text.trim()) ?? 0) * 100).toInt(),
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Stand application added to housing waiting list!'),
            backgroundColor: Color(0xFF0D5C3A),
          ),
        );
        widget.onSubmitted();
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.only(
        top: 20,
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Housing Stand Application',
                      style:
                          TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close)),
                ],
              ),
              const Text(
                'Apply for placement on the municipal housing waiting list pursuant to Section 152 of the Urban Councils Act.',
                style: TextStyle(color: Colors.grey, fontSize: 12),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _category,
                decoration: const InputDecoration(
                    labelText: 'Stand Category *',
                    border: OutlineInputBorder()),
                items: const [
                  DropdownMenuItem(
                      value: 'high_density',
                      child: Text('High Density (200 - 300 m²)')),
                  DropdownMenuItem(
                      value: 'medium_density',
                      child: Text('Medium Density (600 - 1000 m²)')),
                  DropdownMenuItem(
                      value: 'low_density',
                      child: Text('Low Density (1500 - 4000 m²)')),
                  DropdownMenuItem(
                      value: 'commercial',
                      child: Text('Commercial / Retail Stand')),
                ],
                onChanged: (v) =>
                    setState(() => _category = v ?? 'high_density'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _fullName,
                decoration: const InputDecoration(
                    labelText: 'Full Name (as per National ID) *',
                    border: OutlineInputBorder()),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _nationalId,
                decoration: const InputDecoration(
                  labelText: 'Zimbabwean National ID *',
                  hintText: '63-1234567-X-89',
                  border: OutlineInputBorder(),
                ),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Phone Number (EcoCash / SMS) *',
                  hintText: '+263772000000',
                  border: OutlineInputBorder(),
                ),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: _maritalStatus,
                      decoration: const InputDecoration(
                          labelText: 'Marital Status',
                          border: OutlineInputBorder()),
                      items: const [
                        DropdownMenuItem(
                            value: 'married', child: Text('Married')),
                        DropdownMenuItem(
                            value: 'single', child: Text('Single')),
                        DropdownMenuItem(
                            value: 'widowed', child: Text('Widowed')),
                        DropdownMenuItem(
                            value: 'divorced', child: Text('Divorced')),
                      ],
                      onChanged: (v) =>
                          setState(() => _maritalStatus = v ?? 'married'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _dependents,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                          labelText: 'Dependents',
                          border: OutlineInputBorder()),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: _employment,
                      decoration: const InputDecoration(
                          labelText: 'Employment',
                          border: OutlineInputBorder()),
                      items: const [
                        DropdownMenuItem(
                            value: 'employed', child: Text('Employed')),
                        DropdownMenuItem(
                            value: 'self_employed',
                            child: Text('Self-Employed')),
                        DropdownMenuItem(
                            value: 'informal_trader',
                            child: Text('Informal Trader')),
                        DropdownMenuItem(
                            value: 'retired', child: Text('Retired')),
                      ],
                      onChanged: (v) =>
                          setState(() => _employment = v ?? 'employed'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _income,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                          labelText: 'Monthly Income (USD)',
                          border: OutlineInputBorder()),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: FilledButton(
                  onPressed: _busy ? null : _submit,
                  style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFF0D5C3A)),
                  child: _busy
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text('Submit Application',
                          style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _WaitingListData {
  _WaitingListData({
    required this.entries,
    required this.offer,
  });
  final List<HousingWaitingList> entries;
  final HousingAllocationOffer? offer;
}
