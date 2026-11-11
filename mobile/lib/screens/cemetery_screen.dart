import 'package:flutter/material.dart';
import '../core/api_client.dart';

class CemeteryScreen extends StatefulWidget {
  const CemeteryScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<CemeteryScreen> createState() => _CemeteryScreenState();
}

class _CemeteryScreenState extends State<CemeteryScreen> {
  late Future<Map<String, dynamic>> _catalogFuture;
  late Future<Map<String, dynamic>> _myReservationsFuture;

  // Reservation Form State
  String? _selectedCemeteryId;
  String _selectedSection = 'Section A - Lawn Cemetery';
  String _selectedDepth = 'double_8ft';
  final _purchaserNameController = TextEditingController();
  final _purchaserPhoneController = TextEditingController();
  final _deceasedNameController = TextEditingController();
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _catalogFuture = widget.api.get('/api/v1/cemeteries').catchError((_) => {'cemeteries': [], 'sections': []});
    _myReservationsFuture = widget.api.get('/api/v1/cemeteries/my-reservations').catchError((_) => {'reservations': [], 'interments': []});
  }

  Future<void> _submitReservation() async {
    if (_selectedCemeteryId == null || _purchaserNameController.text.isEmpty || _purchaserPhoneController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select a cemetery and provide your name and phone.')));
      return;
    }

    setState(() => _submitting = true);
    try {
      final res = await widget.api.post('/api/v1/cemeteries/reserve', {
        'cemetery_id': _selectedCemeteryId,
        'section_name': _selectedSection,
        'depth': _selectedDepth,
        'purchaser_name': _purchaserNameController.text,
        'purchaser_phone': _purchaserPhoneController.text,
        'deceased_name': _deceasedNameController.text.isNotEmpty ? _deceasedNameController.text : null,
      });

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message']?.toString() ?? 'Grave plot successfully reserved.'),
          backgroundColor: const Color(0xFF0D5C3A),
        ),
      );
      setState(() {
        _deceasedNameController.clear();
        _load();
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Reservation failed: $e')));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  void dispose() {
    _purchaserNameController.dispose();
    _purchaserPhoneController.dispose();
    _deceasedNameController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Cemeteries & Grave Register', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
        backgroundColor: const Color(0xFF475569),
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: RefreshIndicator(
        onRefresh: () async => setState(_load),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Header Info Card
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade200)),
              child: const Padding(
                padding: EdgeInsets.all(16),
                child: Row(
                  children: [
                    CircleAvatar(
                      backgroundColor: Color(0xFFF1F5F9),
                      child: Icon(Icons.church_outlined, color: Color(0xFF334155)),
                    ),
                    SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Municipal Cemetery Services', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                          Text(
                            'Urban Councils Act [Cap 29:15] Section 188 • Perpetual Care, Grave Reservations & Burial Permits.',
                            style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Active Cemeteries in Council
            const Text('MUNICIPAL CEMETERIES & LAWN SECTIONS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B), letterSpacing: 0.8)),
            const SizedBox(height: 8),
            FutureBuilder<Map<String, dynamic>>(
              future: _catalogFuture,
              builder: (context, snapshot) {
                final cemeteries = (snapshot.data?['cemeteries'] as List<dynamic>? ?? []);
                final sections = (snapshot.data?['sections'] as List<dynamic>? ?? []);

                if (cemeteries.isEmpty && snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()));
                }

                if (_selectedCemeteryId == null && cemeteries.isNotEmpty) {
                  _selectedCemeteryId = cemeteries.first['id']?.toString();
                }

                return Column(
                  children: [
                    // Cemetery cards
                    ...cemeteries.map((item) {
                      final c = item as Map<String, dynamic>;
                      final isSelected = _selectedCemeteryId == c['id']?.toString();
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                          side: BorderSide(color: isSelected ? const Color(0xFF0D5C3A) : Colors.grey.shade200, width: isSelected ? 2 : 1),
                        ),
                        child: ListTile(
                          onTap: () => setState(() => _selectedCemeteryId = c['id']?.toString()),
                          title: Text(c['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                          subtitle: Text(c['location'] ?? '', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                          trailing: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(color: const Color(0xFFDCFCE7), borderRadius: BorderRadius.circular(6)),
                            child: Text(
                              '${c['available_plots'] ?? 0} Available',
                              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF15803D)),
                            ),
                          ),
                        ),
                      );
                    }),

                    const SizedBox(height: 12),
                    // Apply for Plot Form
                    Card(
                      elevation: 0,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade200)),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Apply for a Grave Plot / Family Reservation', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                            const SizedBox(height: 2),
                            const Text('Select depth, section, and applicant details for instant municipal reservation.', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                            const SizedBox(height: 12),

                            DropdownButtonFormField<String>(
                              initialValue: _selectedSection,
                              decoration: const InputDecoration(labelText: 'Grave Section Class', border: OutlineInputBorder(), isDense: true),
                              items: sections.map((s) {
                                return DropdownMenuItem<String>(
                                  value: s['name']?.toString() ?? '',
                                  child: Text('${s['name']} (USD \$${s['fee_usd']})', style: const TextStyle(fontSize: 12)),
                                );
                              }).toList(),
                              onChanged: (val) => setState(() => _selectedSection = val ?? 'Section A - Lawn Cemetery'),
                            ),
                            const SizedBox(height: 10),

                            DropdownButtonFormField<String>(
                              initialValue: _selectedDepth,
                              decoration: const InputDecoration(labelText: 'Excavation Depth', border: OutlineInputBorder(), isDense: true),
                              items: const [
                                DropdownMenuItem(value: 'single_6ft', child: Text('Single Depth (6 Feet)')),
                                DropdownMenuItem(value: 'double_8ft', child: Text('Double Depth (8 Feet - Reopenable for Spouse)')),
                                DropdownMenuItem(value: 'triple_10ft', child: Text('Family Vault Depth (10 Feet)')),
                              ],
                              onChanged: (val) => setState(() => _selectedDepth = val ?? 'double_8ft'),
                            ),
                            const SizedBox(height: 10),

                            Row(
                              children: [
                                Expanded(
                                  child: TextField(
                                    controller: _purchaserNameController,
                                    decoration: const InputDecoration(labelText: 'Purchaser / Family Name *', border: OutlineInputBorder(), isDense: true),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: TextField(
                                    controller: _purchaserPhoneController,
                                    keyboardType: TextInputType.phone,
                                    decoration: const InputDecoration(labelText: 'Contact Phone *', border: OutlineInputBorder(), isDense: true),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),

                            TextField(
                              controller: _deceasedNameController,
                              decoration: const InputDecoration(labelText: 'Deceased Name (If Immediate Interment)', border: OutlineInputBorder(), isDense: true),
                            ),
                            const SizedBox(height: 14),

                            SizedBox(
                              width: double.infinity,
                              child: ElevatedButton.icon(
                                onPressed: _submitting ? null : _submitReservation,
                                icon: _submitting ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.bookmark_added, size: 16),
                                label: Text(_submitting ? 'Processing Application...' : 'RESERVE MUNICIPAL GRAVE PLOT NOW', style: const TextStyle(fontWeight: FontWeight.bold)),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF334155),
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                );
              },
            ),

            const SizedBox(height: 16),
            // My Reserved Plots & Burial Orders
            const Text('MY FAMILY GRAVE RESERVATIONS & BURIAL ORDERS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B), letterSpacing: 0.8)),
            const SizedBox(height: 8),
            FutureBuilder<Map<String, dynamic>>(
              future: _myReservationsFuture,
              builder: (context, snapshot) {
                final reservations = (snapshot.data?['reservations'] as List<dynamic>? ?? []);
                final interments = (snapshot.data?['interments'] as List<dynamic>? ?? []);

                if (reservations.isEmpty && interments.isEmpty) {
                  return const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Center(
                        child: Text('No active cemetery plot reservations or burial certificates recorded for your account.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                      ),
                    ),
                  );
                }

                return Column(
                  children: [
                    ...reservations.map((item) {
                      final r = item as Map<String, dynamic>;
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: Color(0xFFE2E8F0),
                            child: Icon(Icons.location_on, color: Color(0xFF475569)),
                          ),
                          title: Text(r['plot_number'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                          subtitle: Text('${r['cemetery_name']} • ${r['section']} • Depth: ${r['depth']}', style: const TextStyle(fontSize: 11)),
                          trailing: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(color: const Color(0xFFFEF3C7), borderRadius: BorderRadius.circular(4)),
                            child: Text(
                              (r['status'] ?? '').toString().toUpperCase(),
                              style: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Color(0xFFB45309)),
                            ),
                          ),
                        ),
                      );
                    }),
                    ...interments.map((item) {
                      final i = item as Map<String, dynamic>;
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: Color(0xFFDCFCE7),
                            child: Icon(Icons.verified, color: Color(0xFF15803D)),
                          ),
                          title: Text(i['interment_order_number'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                          subtitle: Text('Deceased: ${i['deceased_name']} • Date: ${i['date_of_interment']}', style: const TextStyle(fontSize: 11)),
                          trailing: const Text('BURIAL ORDER', style: TextStyle(fontSize: 9, fontWeight: FontWeight.bold, color: Color(0xFF15803D))),
                        ),
                      );
                    }),
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
