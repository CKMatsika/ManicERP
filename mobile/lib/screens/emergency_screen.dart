import 'package:flutter/material.dart';
import '../core/api_client.dart';

class EmergencyScreen extends StatefulWidget {
  const EmergencyScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<EmergencyScreen> createState() => _EmergencyScreenState();
}

class _EmergencyScreenState extends State<EmergencyScreen> {
  late Future<Map<String, dynamic>> _hotlinesFuture;
  late Future<List<dynamic>> _myIncidentsFuture;

  // Form State
  String _incidentType = 'structural_fire';
  final _addressController = TextEditingController();
  final _suburbController = TextEditingController(text: 'CBD');
  final _callerNameController = TextEditingController();
  final _callerPhoneController = TextEditingController();
  final _descController = TextEditingController();
  bool _submitting = false;
  String? _successMessage;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _hotlinesFuture = widget.api.get('/api/v1/emergency/hotlines').catchError((_) => {'hotlines': []});
    _myIncidentsFuture = widget.api.get('/api/v1/emergency/my-incidents').then((res) => (res['incidents'] as List<dynamic>? ?? [])).catchError((_) => []);
  }

  Future<void> _submitReport() async {
    if (_addressController.text.isEmpty || _callerNameController.text.isEmpty || _callerPhoneController.text.isEmpty || _descController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please complete all required fields.')));
      return;
    }

    setState(() => _submitting = true);
    try {
      final res = await widget.api.post('/api/v1/emergency/report', {
        'incident_type': _incidentType,
        'location_address': _addressController.text,
        'suburb': _suburbController.text,
        'caller_name': _callerNameController.text,
        'caller_phone': _callerPhoneController.text,
        'description': _descController.text,
      });

      if (!mounted) return;
      setState(() {
        _successMessage = res['message']?.toString() ?? 'Emergency reported successfully. First responders dispatched.';
        _addressController.clear();
        _descController.clear();
        _load();
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_successMessage!),
          backgroundColor: const Color(0xFFDC2626),
          duration: const Duration(seconds: 6),
        ),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Submission failed: $e')));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  void dispose() {
    _addressController.dispose();
    _suburbController.dispose();
    _callerNameController.dispose();
    _callerPhoneController.dispose();
    _descController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Emergency Services & 24/7 Call Centre', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
        backgroundColor: const Color(0xFF991B1B),
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: RefreshIndicator(
        onRefresh: () async => setState(_load),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Urgent SOS Alert Box
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF2F2),
                border: Border.all(color: const Color(0xFFF87171)),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Row(
                children: [
                  Icon(Icons.warning_amber_rounded, color: Color(0xFFDC2626), size: 32),
                  SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('LIFE-THREATENING EMERGENCY?', style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF991B1B), fontSize: 13)),
                        SizedBox(height: 2),
                        Text(
                          'Dial 993 (Fire) or 994 (Ambulance) immediately from any mobile or landline.',
                          style: TextStyle(fontSize: 11, color: Color(0xFF7F1D1D)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // 24/7 Hotlines Directory
            const Text('24/7 EMERGENCY & FAULT HOTLINES', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B), letterSpacing: 0.8)),
            const SizedBox(height: 8),
            FutureBuilder<Map<String, dynamic>>(
              future: _hotlinesFuture,
              builder: (context, snapshot) {
                final hotlines = (snapshot.data?['hotlines'] as List<dynamic>? ?? []);
                if (hotlines.isEmpty && snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()));
                }

                return Column(
                  children: hotlines.map((item) {
                    final h = item as Map<String, dynamic>;
                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                        side: BorderSide(color: Colors.grey.shade200),
                      ),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: const Color(0xFFFEE2E2),
                          child: Icon(
                            h['service'].toString().contains('Water') ? Icons.water_drop : h['service'].toString().contains('Police') ? Icons.local_police : Icons.phone_in_talk,
                            color: const Color(0xFFB91C1C),
                            size: 20,
                          ),
                        ),
                        title: Text(h['service'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(h['description'] ?? '', style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                            const SizedBox(height: 3),
                            Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(color: const Color(0xFFDC2626), borderRadius: BorderRadius.circular(4)),
                                  child: Text(h['short_code'] ?? '', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 10)),
                                ),
                                const SizedBox(width: 8),
                                Text(h['direct_phone'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF1E293B))),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  }).toList(),
                );
              },
            ),

            const SizedBox(height: 16),
            // Report Incident Section
            const Text('REPORT INCIDENT / REQUEST DISPATCH', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B), letterSpacing: 0.8)),
            const SizedBox(height: 8),
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.grey.shade200)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Dispatch First Responders', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    const SizedBox(height: 2),
                    const Text('Alerts the Central Council Control Room instantly with location coordinates.', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                    const SizedBox(height: 12),

                    DropdownButtonFormField<String>(
                      initialValue: _incidentType,
                      decoration: const InputDecoration(labelText: 'Emergency Category', border: OutlineInputBorder(), isDense: true),
                      items: const [
                        DropdownMenuItem(value: 'structural_fire', child: Text('Structural Fire (House / Shop)')),
                        DropdownMenuItem(value: 'veld_fire', child: Text('Veld / Bush / Grass Fire')),
                        DropdownMenuItem(value: 'ambulance_emergency', child: Text('Medical / Ambulance Emergency')),
                        DropdownMenuItem(value: 'water_burst', child: Text('Major Water Main Burst / Flooding')),
                        DropdownMenuItem(value: 'sewer_overflow', child: Text('Sewer Overflow / Manhole Hazard')),
                        DropdownMenuItem(value: 'road_hazard', child: Text('Road Accident / Fallen Tree / Live Wire')),
                      ],
                      onChanged: (val) => setState(() => _incidentType = val ?? 'structural_fire'),
                    ),
                    const SizedBox(height: 10),

                    Row(
                      children: [
                        Expanded(
                          flex: 2,
                          child: TextField(
                            controller: _addressController,
                            decoration: const InputDecoration(labelText: 'Street Address / Landmark *', border: OutlineInputBorder(), isDense: true),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: TextField(
                            controller: _suburbController,
                            decoration: const InputDecoration(labelText: 'Suburb / Ward *', border: OutlineInputBorder(), isDense: true),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),

                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _callerNameController,
                            decoration: const InputDecoration(labelText: 'Your Name *', border: OutlineInputBorder(), isDense: true),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: TextField(
                            controller: _callerPhoneController,
                            keyboardType: TextInputType.phone,
                            decoration: const InputDecoration(labelText: 'Phone Number *', border: OutlineInputBorder(), isDense: true),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),

                    TextField(
                      controller: _descController,
                      maxLines: 2,
                      decoration: const InputDecoration(labelText: 'Brief Description of Hazard *', border: OutlineInputBorder(), isDense: true),
                    ),
                    const SizedBox(height: 14),

                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _submitting ? null : _submitReport,
                        icon: _submitting ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.send_rounded, size: 16),
                        label: Text(_submitting ? 'Dispatching First Responders...' : 'DISPATCH FIRST RESPONDERS NOW', style: const TextStyle(fontWeight: FontWeight.bold)),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFDC2626),
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

            const SizedBox(height: 16),
            // My Reported Incidents
            const Text('MY REPORTED INCIDENTS & DISPATCH STATUS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B), letterSpacing: 0.8)),
            const SizedBox(height: 8),
            FutureBuilder<List<dynamic>>(
              future: _myIncidentsFuture,
              builder: (context, snapshot) {
                final list = snapshot.data ?? [];
                if (list.isEmpty) {
                  return const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Center(
                        child: Text('No active emergency incidents logged under your profile.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                      ),
                    ),
                  );
                }

                return Column(
                  children: list.map((item) {
                    final inc = item as Map<String, dynamic>;
                    final isClosed = inc['status'] == 'closed';
                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: isClosed ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2),
                          child: Icon(isClosed ? Icons.check_circle : Icons.emergency, color: isClosed ? const Color(0xFF16A34A) : const Color(0xFFDC2626)),
                        ),
                        title: Text(inc['incident_number'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('${inc['location_address']} • ${inc['suburb']}', style: const TextStyle(fontSize: 11)),
                            Text('Dispatched: ${inc['appliances_dispatched']}', style: const TextStyle(fontSize: 10, color: Color(0xFF64748B))),
                          ],
                        ),
                        trailing: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: isClosed ? const Color(0xFFDCFCE7) : const Color(0xFFFEF3C7),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            (inc['status'] ?? '').toString().toUpperCase(),
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.bold,
                              color: isClosed ? const Color(0xFF15803D) : const Color(0xFFB45309),
                            ),
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
