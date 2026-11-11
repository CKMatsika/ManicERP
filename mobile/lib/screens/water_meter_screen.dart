import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../models/models.dart';
import 'requests_screen.dart';

class WaterMeterScreen extends StatefulWidget {
  const WaterMeterScreen({super.key, required this.api, required this.accounts});

  final ApiClient api;
  final List<Account> accounts;

  @override
  State<WaterMeterScreen> createState() => _WaterMeterScreenState();
}

class _WaterMeterScreenState extends State<WaterMeterScreen> {
  bool _loading = true;
  String? _error;
  WaterMeterInfo? _meter;

  @override
  void initState() {
    super.initState();
    _fetchMeter();
  }

  Future<void> _fetchMeter() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final res = await widget.api.get('/api/v1/water/meter');
      final data = res['data'];
      WaterMeterInfo? meter;
      if (data != null && data is Map<String, dynamic>) {
        meter = WaterMeterInfo.fromJson(data);
      }

      if (mounted) {
        setState(() {
          _meter = meter;
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0FDF4).withValues(alpha: 0.5),
      appBar: AppBar(
        title: const Text('Water Meter & Consumption'),
        backgroundColor: const Color(0xFF0369A1),
        foregroundColor: Colors.white,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF0369A1)))
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
                        FilledButton(onPressed: _fetchMeter, child: const Text('Retry')),
                      ],
                    ),
                  ),
                )
              : _meter != null
                  ? _buildMeterView(_meter!)
                  : _buildNoMeterView(),
    );
  }

  Widget _buildMeterView(WaterMeterInfo meter) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Main Dial & Index Card
        Container(
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF0369A1), Color(0xFF075985)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF0369A1).withValues(alpha: 0.25),
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
                  Row(
                    children: [
                      const Icon(Icons.water_drop, color: Color(0xFF7DD3FC), size: 20),
                      const SizedBox(width: 6),
                      Text(
                        meter.meterNumber,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                    ],
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      '${meter.meterSize.toUpperCase()} ACTIVE',
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              const Text(
                'Current Volumetric Meter Index',
                style: TextStyle(color: Color(0xFFBAE6FD), fontSize: 12),
              ),
              const SizedBox(height: 4),
              Text(
                '${meter.lastReading.toStringAsFixed(3)} m³',
                style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900, letterSpacing: 1.2),
              ),
              const SizedBox(height: 4),
              Text(
                'Last read: ${meter.lastReadingDate ?? 'Recent'} • Serial: ${meter.serialNumber}',
                style: const TextStyle(color: Color(0xFFE0F2FE), fontSize: 11),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  _metricPill('Month Consumption', '${meter.latestConsumptionM3} m³'),
                  const SizedBox(width: 10),
                  _metricPill('3-Mo Average', '${meter.averageMonthlyConsumption} m³'),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // Stepped Tariff Estimation Card
        Card(
          elevation: 1,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Estimated Month Water Bill', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: const Color(0xFFE0F2FE),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Text('IBT TARIFF', style: TextStyle(color: Color(0xFF0369A1), fontSize: 10, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Potable Water Tariff:', style: TextStyle(fontSize: 13, color: Colors.grey)),
                    Text('USD ${meter.estimatedWaterUsd}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  ],
                ),
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Sewerage Surcharge (25%):', style: TextStyle(fontSize: 13, color: Colors.grey)),
                    Text('USD ${meter.estimatedSewerageUsd}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  ],
                ),
                const Divider(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total Estimated Charge:', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                    Text(
                      'USD ${meter.estimatedTotalUsd}',
                      style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: Color(0xFF0369A1)),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                const Text(
                  'Billed under municipal by-laws: Band 1 Lifeline (0-10m³ @ \$0.45) subsidized basic human right allocation.',
                  style: TextStyle(fontSize: 11, color: Colors.grey),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Report Leak Action
        Card(
          color: const Color(0xFFFEF2F2),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
            side: const BorderSide(color: Color(0xFFFECACA)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                const CircleAvatar(
                  backgroundColor: Color(0xFFF87171),
                  foregroundColor: Colors.white,
                  child: Icon(Icons.report_problem, size: 20),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Notice a leak or broken meter?', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF991B1B))),
                      Text('Report municipal bursts or pipe leaks directly to Engineering & Works.', style: TextStyle(fontSize: 11, color: Color(0xFF7F1D1D))),
                    ],
                  ),
                ),
                OutlinedButton(
                  onPressed: () {
                    Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => RequestsScreen(api: widget.api, accounts: widget.accounts),
                    ));
                  },
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFF991B1B),
                    side: const BorderSide(color: Color(0xFFEF4444)),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  ),
                  child: const Text('Report', style: TextStyle(fontSize: 12)),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Historic Meter Readings
        if (meter.history.isNotEmpty) ...[
          const Text('Historic Reading Log', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          const SizedBox(height: 8),
          for (final item in meter.history)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor: item.isBurstAlert ? const Color(0xFFFEE2E2) : const Color(0xFFE0F2FE),
                  foregroundColor: item.isBurstAlert ? const Color(0xFFDC2626) : const Color(0xFF0284C7),
                  child: Icon(item.isBurstAlert ? Icons.warning : Icons.water, size: 18),
                ),
                title: Text(
                  '${item.period} • ${item.consumption} m³ consumed',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                ),
                subtitle: Text('Index: ${item.reading} m³ • ${item.date ?? 'Recorded'}', style: const TextStyle(fontSize: 11)),
                trailing: item.isBurstAlert
                    ? const Chip(
                        label: Text('BURST ALERT', style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Colors.white)),
                        backgroundColor: Color(0xFFDC2626),
                        padding: EdgeInsets.zero,
                      )
                    : const Chip(
                        label: Text('VERIFIED', style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Color(0xFF0369A1))),
                        backgroundColor: Color(0xFFE0F2FE),
                        padding: EdgeInsets.zero,
                      ),
              ),
            ),
        ],
      ],
    );
  }

  Widget _buildNoMeterView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.water_damage_outlined, size: 54, color: Colors.grey),
            const SizedBox(height: 14),
            const Text(
              'No Water Meter Linked',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
            ),
            const SizedBox(height: 6),
            const Text(
              'There is no active municipal water meter currently associated with your linked account. Please visit the Engineering & Water Works department at Civic Centre.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey, fontSize: 13),
            ),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: _fetchMeter,
              icon: const Icon(Icons.refresh),
              label: const Text('Refresh'),
              style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0369A1)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _metricPill(String label, String value) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 10),
        decoration: BoxDecoration(
          color: Colors.black.withValues(alpha: 0.18),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(color: Color(0xFFBAE6FD), fontSize: 10)),
            const SizedBox(height: 2),
            Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
          ],
        ),
      ),
    );
  }
}
