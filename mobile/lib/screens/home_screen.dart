import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/session_store.dart';
import '../models/models.dart';
import 'account_screen.dart';
import 'cemetery_screen.dart';
import 'civic_hub_screen.dart';
import 'emergency_screen.dart';
import 'licensing_screen.dart';
import 'link_account_screen.dart';
import 'pay_screen.dart';
import 'payment_plan_screen.dart';
import 'requests_screen.dart';
import 'waiting_list_screen.dart';
import 'water_meter_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.api, required this.session, required this.onSignOut});

  final ApiClient api;
  final SessionStore session;
  final Future<void> Function() onSignOut;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<_DashboardData> _data;
  String _councilName = 'Zimbabwe Local Authority';

  @override
  void initState() {
    super.initState();
    _data = _loadDashboard();
    widget.session.readCouncilName().then((value) {
      if (mounted && value != null) setState(() => _councilName = value);
    });
  }

  Future<_DashboardData> _loadDashboard() async {
    final results = await Future.wait([
      widget.api.get('/api/v1/accounts').catchError((_) => {'data': []}),
      widget.api.get('/api/v1/waiting-list').catchError((_) => {'data': []}),
      widget.api.get('/api/v1/civic').catchError((_) => {'data': []}),
      widget.api.get('/api/v1/credit-control/active-plan').catchError((_) => {'data': null}),
    ]);

    final accountsRaw = (results[0]['data'] as List<dynamic>? ?? const []);
    final accounts = accountsRaw.map((item) => Account.fromJson(item as Map<String, dynamic>)).toList();

    final waitingRaw = (results[1]['data'] as List<dynamic>? ?? const []);
    final waitingList = waitingRaw.map((item) => HousingWaitingList.fromJson(item as Map<String, dynamic>)).toList();

    final civicRaw = (results[2]['data'] as List<dynamic>? ?? const []);
    final civicItems = civicRaw.map((item) => CivicItem.fromJson(item as Map<String, dynamic>)).toList();

    final planRaw = results[3]['data'];
    PaymentPlanDetails? activePlan;
    if (planRaw != null && planRaw is Map<String, dynamic>) {
      activePlan = PaymentPlanDetails.fromJson(planRaw);
    }

    return _DashboardData(
      accounts: accounts,
      waitingList: waitingList,
      civicItems: civicItems,
      activePlan: activePlan,
    );
  }

  void _refresh() {
    setState(() => _data = _loadDashboard());
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF8),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0D5C3A),
        foregroundColor: Colors.white,
        elevation: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              _councilName.toUpperCase(),
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, letterSpacing: 1.1),
            ),
            const Text(
              'Citizen Portal & Smart Services',
              style: TextStyle(fontSize: 11, color: Color(0xFFE2E8F0)),
            ),
          ],
        ),
        actions: [
          IconButton(onPressed: _refresh, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
          IconButton(onPressed: () => widget.onSignOut(), icon: const Icon(Icons.logout), tooltip: 'Sign out'),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => _refresh(),
        child: FutureBuilder<_DashboardData>(
          future: _data,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator(color: Color(0xFF0D5C3A)));
            }

            if (snapshot.hasError) {
              return _ErrorView(message: snapshot.error.toString(), onRetry: _refresh);
            }

            final data = snapshot.data ?? _DashboardData(accounts: [], waitingList: [], civicItems: []);
            final totalOutstandingMinor = data.accounts.fold<int>(0, (sum, acc) => sum + acc.outstandingMinor);
            final currency = data.accounts.isNotEmpty ? data.accounts.first.currency : 'USD';
            final activeWaitingList = data.waitingList.isNotEmpty ? data.waitingList.first : null;

            return ListView(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              children: [
                // 24/7 Rapid Emergency & Call Centre Hotline Strip
                InkWell(
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => EmergencyScreen(api: widget.api)),
                  ),
                  borderRadius: BorderRadius.circular(10),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFFCA5A5)),
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(6),
                          decoration: const BoxDecoration(
                            color: Color(0xFFDC2626),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.emergency, color: Colors.white, size: 18),
                        ),
                        const SizedBox(width: 12),
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('24/7 Emergency & Rapid Response Hotlines', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF991B1B))),
                              Text('Fire (993) • Ambulance (994) • Water Bursts • Control Room', style: TextStyle(fontSize: 11, color: Color(0xFF7F1D1D))),
                            ],
                          ),
                        ),
                        const Icon(Icons.chevron_right, color: Color(0xFFDC2626)),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                // Financial Overview Card
                _buildFinancialCard(totalOutstandingMinor, currency, data.accounts, data.activePlan),
                const SizedBox(height: 14),

                // Housing Waiting List Card
                _buildWaitingListCard(activeWaitingList),
                const SizedBox(height: 18),

                // Section Title
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Municipal Services', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                    Text('${data.accounts.length} linked account(s)', style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
                  ],
                ),
                const SizedBox(height: 10),

                // Feature Grid
                _buildServiceGrid(data.accounts),
                const SizedBox(height: 20),

                // Civic Announcements Banner
                if (data.civicItems.isNotEmpty) ...[
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Council Bulletins & Consultations', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
                      TextButton(
                        onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => CivicHubScreen(api: widget.api))),
                        child: const Text('View all', style: TextStyle(color: Color(0xFF0D5C3A))),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  for (final item in data.civicItems.take(2))
                    _buildCivicTeaser(item),
                ],
                const SizedBox(height: 16),
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _buildFinancialCard(
    int totalOutstandingMinor,
    String currency,
    List<Account> accounts,
    PaymentPlanDetails? activePlan,
  ) {
    final balanceLabel = '$currency ${(totalOutstandingMinor / 100).toStringAsFixed(2)}';
    final hasDebt = totalOutstandingMinor > 0;
    final hasActivePlan = activePlan != null && activePlan.isActive;

    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: hasActivePlan
              ? [const Color(0xFF065F46), const Color(0xFF047857)]
              : [const Color(0xFF0D5C3A), const Color(0xFF144A32)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0D5C3A).withValues(alpha: 0.25),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Consolidated Rates & Utilities',
                style: TextStyle(color: Color(0xFFE2E8F0), fontSize: 13, fontWeight: FontWeight.w500),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: hasActivePlan ? const Color(0xFFFDE047) : Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  hasActivePlan
                      ? 'PROTECTED (IMMUNITY ACTIVE)'
                      : hasDebt
                          ? 'PAYMENT DUE'
                          : 'UP TO DATE',
                  style: TextStyle(
                    color: hasActivePlan ? const Color(0xFF78350F) : Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            balanceLabel,
            style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          Text(
            accounts.isNotEmpty
                ? 'Account: ${accounts.first.accountNumber} • ${accounts.first.property ?? 'Municipal Rates'}'
                : 'No council property linked yet',
            style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 12),
          ),
          if (hasActivePlan) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  const Icon(Icons.shield, color: Color(0xFFFDE047), size: 18),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Plan ${activePlan.planNumber}: ${activePlan.paidInstallments}/${activePlan.installmentCount} paid'
                      '${activePlan.nextInstallment != null ? ' • Next: USD ${activePlan.nextInstallment!.amountUsd} due ${activePlan.nextInstallment!.dueDate}' : ''}',
                      style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w500),
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: accounts.isEmpty
                      ? null
                      : () async {
                          await Navigator.of(context).push(MaterialPageRoute(
                            builder: (_) => PayScreen(api: widget.api, account: accounts.first, bills: const []),
                          ));
                          _refresh();
                        },
                  icon: const Icon(Icons.flash_on, size: 18),
                  label: const Text('Pay with Paynow'),
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFFD4A373),
                    foregroundColor: const Color(0xFF261D15),
                    textStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
              if (accounts.isNotEmpty && hasDebt) ...[
                const SizedBox(width: 8),
                OutlinedButton.icon(
                  onPressed: () async {
                    await Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => PaymentPlanScreen(api: widget.api, account: accounts.first),
                    ));
                    _refresh();
                  },
                  icon: Icon(hasActivePlan ? Icons.list_alt : Icons.handshake, size: 16, color: Colors.white),
                  label: Text(
                    hasActivePlan ? 'Plan Schedule' : '50% Amnesty',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Colors.white54),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildWaitingListCard(HousingWaitingList? waitingList) {
    if (waitingList == null) {
      return Card(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        color: const Color(0xFFEFF6FF),
        child: ListTile(
          leading: const CircleAvatar(
            backgroundColor: Color(0xFF3B82F6),
            foregroundColor: Colors.white,
            child: Icon(Icons.add_home_work),
          ),
          title: const Text('Municipal Housing Waiting List', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          subtitle: const Text('Apply for residential or commercial stands under Urban Councils Act Chapter 29:15', style: TextStyle(fontSize: 12)),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => WaitingListScreen(api: widget.api))),
        ),
      );
    }

    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: InkWell(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => WaitingListScreen(api: widget.api))),
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: const Color(0xFF0D5C3A),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Center(
                  child: Text(
                    '#${waitingList.queuePosition}',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(waitingList.waitingListNumber, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                        const Spacer(),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: const Color(0xFFDCFCE7),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Text('ACTIVE', style: TextStyle(color: Color(0xFF15803D), fontSize: 10, fontWeight: FontWeight.bold)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text('${waitingList.categoryLabel} • Pos ${waitingList.queuePosition} of ${waitingList.totalInQueue}', style: TextStyle(color: Colors.grey.shade700, fontSize: 12)),
                    const SizedBox(height: 4),
                    Text(
                      'Annual renewal due in ${waitingList.daysUntilRenewal} days',
                      style: TextStyle(
                        fontSize: 11,
                        color: waitingList.isRenewalDue ? Colors.deepOrange : const Color(0xFF0D5C3A),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: Colors.grey),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildServiceGrid(List<Account> accounts) {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      childAspectRatio: 1.35,
      children: [
        _buildServiceTile(
          title: 'Licences & Permits',
          subtitle: 'Shop, Food, Liquor & Vendors',
          icon: Icons.storefront_outlined,
          color: const Color(0xFF0D5C3A),
          onTap: () async {
            await Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => LicensingScreen(api: widget.api, accounts: accounts),
            ));
            _refresh();
          },
        ),
        _buildServiceTile(
          title: 'Housing Waiting List',
          subtitle: 'Track queue & stand applications',
          icon: Icons.maps_home_work_outlined,
          color: const Color(0xFF16A34A),
          onTap: () => Navigator.of(context).push(MaterialPageRoute(
            builder: (_) => WaitingListScreen(api: widget.api),
          )),
        ),
        _buildServiceTile(
          title: 'Debt Relief & Plans',
          subtitle: '50% amnesty & immunity (s279)',
          icon: Icons.shield_outlined,
          color: const Color(0xFF047857),
          onTap: () async {
            if (accounts.isEmpty) {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please link an account first.')));
              return;
            }
            await Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => PaymentPlanScreen(api: widget.api, account: accounts.first),
            ));
            _refresh();
          },
        ),
        _buildServiceTile(
          title: 'Civic Hub',
          subtitle: 'Budget consultations & resolutions',
          icon: Icons.campaign_outlined,
          color: const Color(0xFFD97706),
          onTap: () => Navigator.of(context).push(MaterialPageRoute(
            builder: (_) => CivicHubScreen(api: widget.api),
          )),
        ),
        _buildServiceTile(
          title: 'Paynow Gateway',
          subtitle: 'EcoCash, OneMoney & Cards',
          icon: Icons.payments_outlined,
          color: const Color(0xFF2563EB),
          onTap: () async {
            if (accounts.isEmpty) {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please link an account first.')));
              return;
            }
            await Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => PayScreen(api: widget.api, account: accounts.first, bills: const []),
            ));
            _refresh();
          },
        ),
        _buildServiceTile(
          title: 'Service Faults',
          subtitle: 'Report leaks, refuse & potholes',
          icon: Icons.report_problem_outlined,
          color: const Color(0xFFDC2626),
          onTap: () => Navigator.of(context).push(MaterialPageRoute(
            builder: (_) => RequestsScreen(api: widget.api, accounts: accounts),
          )),
        ),
        _buildServiceTile(
          title: 'Water & Consumption',
          subtitle: 'Dial index & leak alerts',
          icon: Icons.water_drop_outlined,
          color: const Color(0xFF0284C7),
          onTap: () => Navigator.of(context).push(MaterialPageRoute(
            builder: (_) => WaterMeterScreen(api: widget.api, accounts: accounts),
          )),
        ),
        _buildServiceTile(
          title: 'Accounts & Bills',
          subtitle: 'Statements, links & receipts',
          icon: Icons.receipt_long_outlined,
          color: const Color(0xFF0891B2),
          onTap: () async {
            if (accounts.isEmpty) {
              await Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => LinkAccountScreen(api: widget.api),
              ));
              _refresh();
            } else {
              await Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => AccountScreen(api: widget.api, account: accounts.first),
              ));
              _refresh();
            }
          },
        ),
        _buildServiceTile(
          title: 'Emergency (24/7)',
          subtitle: 'Fire, Ambulance & Hotlines',
          icon: Icons.emergency_outlined,
          color: const Color(0xFFDC2626),
          onTap: () => Navigator.of(context).push(MaterialPageRoute(
            builder: (_) => EmergencyScreen(api: widget.api),
          )),
        ),
        _buildServiceTile(
          title: 'Cemeteries & Graves',
          subtitle: 'Plot booking & burial permits',
          icon: Icons.church_outlined,
          color: const Color(0xFF475569),
          onTap: () => Navigator.of(context).push(MaterialPageRoute(
            builder: (_) => CemeteryScreen(api: widget.api),
          )),
        ),
      ],
    );
  }

  Widget _buildServiceTile({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: color, size: 22),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  const SizedBox(height: 2),
                  Text(subtitle, style: TextStyle(color: Colors.grey.shade600, fontSize: 10), maxLines: 1, overflow: TextOverflow.ellipsis),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCivicTeaser(CivicItem item) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: ListTile(
        leading: Icon(
          item.type == 'event' ? Icons.event : (item.type == 'resolution' ? Icons.gavel : Icons.article),
          color: const Color(0xFF0D5C3A),
        ),
        title: Text(item.title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
        subtitle: Text(item.summary, style: TextStyle(color: Colors.grey.shade700, fontSize: 11), maxLines: 2, overflow: TextOverflow.ellipsis),
        trailing: const Icon(Icons.arrow_forward_ios, size: 14),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => CivicHubScreen(api: widget.api))),
      ),
    );
  }
}

class _DashboardData {
  _DashboardData({
    required this.accounts,
    required this.waitingList,
    required this.civicItems,
    this.activePlan,
  });

  final List<Account> accounts;
  final List<HousingWaitingList> waitingList;
  final List<CivicItem> civicItems;
  final PaymentPlanDetails? activePlan;
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 48, color: Colors.red),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}
