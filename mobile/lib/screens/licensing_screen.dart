import 'dart:convert';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/api_client.dart';
import '../models/models.dart';

class LicensingScreen extends StatefulWidget {
  const LicensingScreen({super.key, required this.api, required this.accounts});

  final ApiClient api;
  final List<Account> accounts;

  @override
  State<LicensingScreen> createState() => _LicensingScreenState();
}

class _LicensingScreenState extends State<LicensingScreen> {
  static const _draftKey = 'manicerp.licensing.unsent_draft';
  static const _catalogKey = 'manicerp.licensing.cached_catalog';
  List<Map<String, dynamic>> _types = [];
  List<Map<String, dynamic>> _applications = [];
  final Map<String, TextEditingController> _fields = {};
  final Map<String, PlatformFile> _files = {};
  String? _typeCode;
  String? _accountId;
  String? _applicationId;
  String? _renewalLicenceId;
  String? _error;
  String? _message;
  bool _busy = false;
  bool _offlineDraft = false;

  Map<String, dynamic>? get _selectedType => _typeFor(_typeCode);
  List<dynamic> get _requiredDocuments =>
      (_selectedType?['configuration']?['required_documents']
          as List<dynamic>?) ??
      const [];
  List<dynamic> get _configuredFields =>
      (_selectedType?['configuration']?['application_fields']
          as List<dynamic>?) ??
      const [];

  Map<String, dynamic>? _typeFor(String? code) {
    for (final type in _types) {
      if (type['code']?.toString() == code) return type;
    }
    return null;
  }

  @override
  void initState() {
    super.initState();
    _accountId = widget.accounts.isNotEmpty ? widget.accounts.first.id : null;
    _load();
  }

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_draftKey);
    final draft = raw == null ? null : jsonDecode(raw) as Map<String, dynamic>;
    final cachedRaw = prefs.getString(_catalogKey);
    final cachedTypes = cachedRaw == null
        ? <Map<String, dynamic>>[]
        : (jsonDecode(cachedRaw) as List<dynamic>).cast<Map<String, dynamic>>();
    if (mounted && cachedTypes.isNotEmpty) {
      setState(() {
        _types = cachedTypes;
        _typeCode = draft?['licence_type']?.toString() ??
            cachedTypes.first['code']?.toString();
        _accountId = draft?['account_id']?.toString() ?? _accountId;
        _applicationId = draft?['application_id']?.toString();
        _renewalLicenceId = draft?['licence_id']?.toString();
        _offlineDraft = draft != null && draft['sent'] != true;
        final values = (draft?['form'] as Map<String, dynamic>?) ?? {};
        for (final field in _configuredFields) {
          final name = field['name'].toString();
          _fields.putIfAbsent(
              name,
              () =>
                  TextEditingController(text: values[name]?.toString() ?? ''));
        }
        final storedFiles = (draft?['files'] as Map<String, dynamic>?) ?? {};
        for (final entry in storedFiles.entries) {
          final metadata = entry.value as Map<String, dynamic>;
          final path = metadata['path']?.toString();
          if (path != null) {
            _files[entry.key] = PlatformFile(
                name: metadata['name']?.toString() ?? 'document',
                size: 0,
                path: path);
          }
        }
      });
    }
    try {
      final results = await Future.wait([
        widget.api.get('/api/v1/licensing/types'),
        widget.api.get('/api/v1/licensing/applications'),
      ]);
      final types = (results[0]['data'] as List<dynamic>? ?? const [])
          .cast<Map<String, dynamic>>();
      final applications = (results[1]['data'] as List<dynamic>? ?? const [])
          .cast<Map<String, dynamic>>();
      await prefs.setString(_catalogKey, jsonEncode(types));
      if (!mounted) return;
      setState(() {
        _types = types;
        _applications = applications;
        _typeCode = draft?['licence_type']?.toString() ??
            (types.isNotEmpty ? types.first['code'].toString() : null);
        _accountId = draft?['account_id']?.toString() ?? _accountId;
        _applicationId = draft?['application_id']?.toString();
        _renewalLicenceId = draft?['licence_id']?.toString();
        _offlineDraft = draft != null;
        final values = (draft?['form'] as Map<String, dynamic>?) ?? {};
        for (final field in _configuredFields) {
          final name = field['name'].toString();
          _fields.putIfAbsent(
              name,
              () =>
                  TextEditingController(text: values[name]?.toString() ?? ''));
        }
        final storedFiles = (draft?['files'] as Map<String, dynamic>?) ?? {};
        for (final entry in storedFiles.entries) {
          final metadata = entry.value as Map<String, dynamic>;
          final path = metadata['path']?.toString();
          if (path != null) {
            _files[entry.key] = PlatformFile(
                name: metadata['name']?.toString() ?? 'document',
                size: 0,
                path: path);
          }
        }
      });
    } on ApiException catch (exception) {
      if (mounted) {
        setState(() {
          _error = exception.message;
          if (draft != null && draft['sent'] != true) {
            _message = 'Offline: this saved device draft remains UNSENT.';
          }
        });
      }
    }
  }

  Map<String, String> _form() => {
        for (final field in _configuredFields)
          field['name'].toString():
              _fields[field['name'].toString()]?.text.trim() ?? ''
      };

  Future<Map<String, dynamic>> _saveLocal() async {
    final prefs = await SharedPreferences.getInstance();
    final previous = prefs.getString(_draftKey);
    final oldDraft = previous == null
        ? <String, dynamic>{}
        : jsonDecode(previous) as Map<String, dynamic>;
    final draft = <String, dynamic>{
      'account_id': _accountId,
      'licence_type': _typeCode,
      'application_id': _applicationId,
      'licence_id': _renewalLicenceId,
      'form': _form(),
      'idempotency_key': oldDraft['idempotency_key'] ??
          'mobile-licence-${DateTime.now().microsecondsSinceEpoch}',
      'files': {
        for (final entry in _files.entries)
          entry.key: {'path': entry.value.path, 'name': entry.value.name}
      },
      'sent': false,
    };
    await prefs.setString(_draftKey, jsonEncode(draft));
    if (mounted) setState(() => _offlineDraft = true);
    return draft;
  }

  Future<void> _pickFile(String code) async {
    final result = await FilePicker.platform
        .pickFiles(withData: false, allowMultiple: false);
    if (result == null ||
        result.files.isEmpty ||
        result.files.single.path == null) {
      return;
    }
    setState(() => _files[code] = result.files.single);
    await _saveLocal();
  }

  Future<void> _syncAndSubmit() async {
    if (_accountId == null || _typeCode == null) {
      setState(
          () => _error = 'Link an account and choose a licence type first.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _message = null;
    });
    final draft = await _saveLocal();
    try {
      final saved = await widget.api.post('/api/v1/licensing/drafts', {
        'account_id': _accountId,
        'licence_type': _typeCode,
        'application_id': _applicationId,
        'licence_id': _renewalLicenceId,
        'form': draft['form'],
      });
      final data = saved['data'] as Map<String, dynamic>;
      _applicationId = data['id'].toString();
      for (final document in _requiredDocuments) {
        final code = document['code'].toString();
        final file = _files[code];
        if (file?.path == null) continue;
        await widget.api.upload(
            '/api/v1/licensing/applications/$_applicationId/documents',
            filePath: file!.path!,
            fileName: file.name,
            fields: {'account_id': _accountId!, 'document_type': code});
      }
      final submitted =
          await widget.api.post('/api/v1/licensing/applications', {
        'account_id': _accountId,
        'licence_type': _typeCode,
        'application_id': _applicationId,
        'idempotency_key': draft['idempotency_key'],
        'form': draft['form'],
      });
      final application = submitted['data'] as Map<String, dynamic>;
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_draftKey);
      if (!mounted) return;
      setState(() {
        _offlineDraft = false;
        _message =
            'Application ${application['reference']} submitted to the council.';
        _applications = [
          application,
          ..._applications.where((item) => item['id'] != application['id'])
        ];
      });
    } on ApiException catch (exception) {
      if (mounted) {
        setState(() => _message = exception.statusCode == 0
            ? 'Offline: this draft is saved on this device and remains UNSENT. Retry sync when online.'
            : null);
      }
      if (exception.statusCode != 0 && mounted) {
        setState(() => _error = exception.message);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _createPayment(
      Map<String, dynamic> application, Map<String, dynamic> charge) async {
    try {
      final result = await widget.api
          .post('/api/v1/licensing/applications/${application['id']}/payment', {
        'charge_id': charge['id'],
        'idempotency_key':
            'licence-payment-${application['id']}-${charge['id']}',
      });
      final data = result['data'] as Map<String, dynamic>;
      setState(() => _message =
          'Payment intent ${data['reference']} is pending. A payment redirect is not confirmation. Refresh after the server verifies settlement and allocation.');
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    }
  }

  Future<void> _requestRefund(Map<String, dynamic> application,
      Map<String, dynamic> payment, String note) async {
    try {
      await widget.api
          .post('/api/v1/licensing/applications/${application['id']}/refunds', {
        'payment_id': payment['id'],
        'charge_id': payment['charge_id'],
        'amount_minor': payment['amount_minor'],
        'customer_note': note,
        'idempotency_key':
            'licence-refund-${application['id']}-${payment['id']}',
      });
      setState(() => _message =
          'Refund request submitted. Settlement is handled by the council refund workflow.');
      await _load();
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    }
  }

  Future<void> _downloadCertificate(Map<String, dynamic> certificate) async {
    try {
      final bytes = await widget.api.download(
          '/api/v1/licensing/certificates/${certificate['id']}/download');
      final path = await FilePicker.platform.saveFile(
        dialogTitle: 'Save licence certificate',
        fileName: '${certificate['reference']}.pdf',
        bytes: Uint8List.fromList(bytes),
      );
      if (mounted && path != null) {
        setState(() => _message = 'Certificate saved to $path.');
      }
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    }
  }

  Future<String?> _askRefundReason() async {
    final controller = TextEditingController();
    final result = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
              title: const Text('Request fee refund'),
              content: TextField(
                  controller: controller,
                  autofocus: true,
                  maxLength: 1000,
                  decoration: const InputDecoration(labelText: 'Reason')),
              actions: [
                TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Cancel')),
                FilledButton(
                    onPressed: () => Navigator.pop(context, controller.text),
                    child: const Text('Submit request')),
              ],
            ));
    controller.dispose();
    return result;
  }

  Future<void> _startRenewal(Map<String, dynamic> application) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_draftKey);
    setState(() {
      _renewalLicenceId = application['licence_id'].toString();
      _typeCode = application['type_code'].toString();
      _applicationId = null;
      _offlineDraft = false;
      for (final controller in _fields.values) {
        controller.clear();
      }
      _message = 'A renewal attempt will be sent as a separate application.';
    });
  }

  Future<void> _askGuidance() async {
    final question =
        '${_selectedType?['name'] ?? 'Licence'} requirements and application fields';
    try {
      final response = await widget.api.get(
          '/api/v1/licensing/guidance?q=${Uri.encodeQueryComponent(question)}');
      final result = response['data'] as Map<String, dynamic>;
      final sources = result['sources'] as List<dynamic>? ?? const [];
      final details = sources
          .map((source) =>
              '${source['title']} v${source['version']} • ${source['authority_label']} • ${source['section_ref'] ?? 'section unspecified'}\n${source['excerpt']}')
          .join('\n\n');
      if (!mounted) return;
      await showDialog<void>(
          context: context,
          builder: (context) => AlertDialog(
                title: const Text('Licence guidance only'),
                content: SingleChildScrollView(
                    child: Text(
                        '${result['answer'] ?? 'No generated answer is available.'}\n\n${result['disclaimer'] ?? ''}\n\n${details.isEmpty ? 'No approved configuration matched.' : details}\n\nThis cannot determine eligibility, approve an application or confirm payment.')),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text('Close'))
                ],
              ));
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    }
  }

  Future<void> _uploadResponseDocument(
      Map<String, dynamic> application, String code) async {
    final result = await FilePicker.platform
        .pickFiles(withData: false, allowMultiple: false);
    if (result == null || result.files.single.path == null) return;
    final file = result.files.single;
    try {
      await widget.api.upload(
          '/api/v1/licensing/applications/${application['id']}/documents',
          filePath: file.path!,
          fileName: file.name,
          fields: {
            'account_id': application['account_id'].toString(),
            'document_type': code
          });
      setState(() => _message = 'Response document uploaded privately.');
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    }
  }

  Future<void> _sendResponse(Map<String, dynamic> application) async {
    try {
      await widget.api
          .post('/api/v1/licensing/applications/${application['id']}/respond', {
        'account_id': application['account_id'],
        'form': application['form_data'] ?? <String, String>{},
      });
      setState(() => _message = 'Response sent to the council for review.');
      await _load();
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    }
  }

  Future<void> _clearDraft() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_draftKey);
    setState(() {
      _offlineDraft = false;
      _files.clear();
      _applicationId = null;
      _renewalLicenceId = null;
      _fields.forEach((_, controller) => controller.clear());
    });
  }

  @override
  Widget build(BuildContext context) {
    final type = _selectedType;
    return Scaffold(
      appBar: AppBar(title: const Text('Licences and renewals'), actions: [
        IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh')
      ]),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        if (_offlineDraft)
          const Card(
              color: Color(0xFFFFF4D4),
              child: Padding(
                  padding: EdgeInsets.all(12),
                  child: Text(
                      'UNSENT DRAFT: saved only on this device. It is not an application and no payment has been made.'))),
        if (widget.accounts.isEmpty)
          const Text('Link and verify a council account before applying.'),
        if (widget.accounts.isNotEmpty && _types.isNotEmpty) ...[
          DropdownButtonFormField<String>(
              initialValue: _accountId,
              decoration: const InputDecoration(
                  labelText: 'Verified linked account',
                  border: OutlineInputBorder()),
              items: [
                for (final account in widget.accounts)
                  DropdownMenuItem(
                      value: account.id, child: Text(account.accountNumber))
              ],
              onChanged: (value) => setState(() => _accountId = value)),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
              initialValue: _typeCode,
              decoration: const InputDecoration(
                  labelText: 'Licence type', border: OutlineInputBorder()),
              items: [
                for (final item in _types)
                  DropdownMenuItem(
                      value: item['code'].toString(),
                      child: Text(item['name'].toString()))
              ],
              onChanged: (value) => setState(() {
                    _typeCode = value;
                    _applicationId = null;
                    _renewalLicenceId = null;
                  })),
          const SizedBox(height: 8),
          if (type != null) ...[
            Container(
              margin: const EdgeInsets.symmetric(vertical: 8),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF0FDF4),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFF86EFAC)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.verified, size: 16, color: Color(0xFF0D5C3A)),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          type['configuration']?['authority']?.toString() ?? 'Municipal Statutory Authority',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF0D5C3A)),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    runSpacing: 4,
                    children: [
                      if (type['configuration']?['charges']?['application_minor'] != null)
                        Chip(
                          label: Text('Fee: USD ${(int.parse(type['configuration']['charges']['application_minor'].toString()) / 100).toStringAsFixed(2)}', style: const TextStyle(fontSize: 11)),
                          backgroundColor: Colors.white,
                          visualDensity: VisualDensity.compact,
                        ),
                      Chip(
                        label: Text('Validity: ${type['configuration']?['validity_days'] ?? 365} Days', style: const TextStyle(fontSize: 11)),
                        backgroundColor: Colors.white,
                        visualDensity: VisualDensity.compact,
                      ),
                      if (type['configuration']?['inspection']?['required'] == true)
                        const Chip(
                          label: Text('Physical Inspection Required', style: TextStyle(fontSize: 11, color: Color(0xFFB45309))),
                          backgroundColor: Color(0xFFFEF3C7),
                          visualDensity: VisualDensity.compact,
                        ),
                    ],
                  ),
                ],
              ),
            ),
            Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                    onPressed: _askGuidance,
                    icon: const Icon(Icons.help_outline),
                    label: const Text('Ask about approved requirements'))),
            for (final field in _configuredFields)
              Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: TextField(
                      controller: _fields.putIfAbsent(field['name'].toString(),
                          () => TextEditingController()),
                      decoration: InputDecoration(
                          labelText:
                              '${field['label']}${field['required'] == true ? ' *' : ''}',
                          border: const OutlineInputBorder()))),
            for (final document in _requiredDocuments)
              ListTile(
                  title: Text(document['label'].toString()),
                  subtitle: Text(_files[document['code']]?.name ??
                      'Required private document'),
                  trailing: IconButton(
                      onPressed: () => _pickFile(document['code'].toString()),
                      icon: const Icon(Icons.attach_file),
                      tooltip: 'Choose document')),
            Row(children: [
              Expanded(
                  child: OutlinedButton(
                      onPressed: _saveLocal,
                      child: const Text('Save unsent draft'))),
              const SizedBox(width: 8),
              Expanded(
                  child: FilledButton(
                      onPressed: _busy ? null : _syncAndSubmit,
                      child: Text(_busy ? 'Syncing…' : 'Upload and submit')))
            ]),
            if (_offlineDraft)
              TextButton(
                  onPressed: _clearDraft,
                  child: const Text('Discard local draft')),
          ],
        ],
        if (_error != null)
          Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(_error!, style: const TextStyle(color: Colors.red))),
        if (_message != null)
          Padding(
              padding: const EdgeInsets.only(top: 12), child: Text(_message!)),
        const Divider(height: 32),
        const Text('Applications and certificates',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17)),
        for (final application in _applications)
          Card(
              child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                            '${application['reference']} • ${application['type']}',
                            style:
                                const TextStyle(fontWeight: FontWeight.w600)),
                        Text(
                            '${application['attempt_type']} • ${application['status']}'),
                        Text(
                            'Fee: ${application['fee_currency']} ${(((application['fee_amount_minor'] as num?)?.toInt() ?? 0) / 100).toStringAsFixed(2)} • ${application['fee_paid'] == true ? 'settled' : 'not verified as paid'}'),
                        if (application['decision_reason'] != null)
                          Text(application['decision_reason'].toString()),
                        for (final refund
                            in (application['refunds'] as List<dynamic>? ??
                                const []))
                          Text(
                              'Refund ${refund['refund_number']}: ${refund['status']}'),
                        if (application['status'] ==
                            'information_requested') ...[
                          for (final doc in ((_typeFor(application['type_code']
                                      ?.toString())?['configuration']
                                  ?['required_documents'] as List<dynamic>?) ??
                              const []))
                            TextButton.icon(
                                onPressed: () => _uploadResponseDocument(
                                    application, doc['code'].toString()),
                                icon: const Icon(Icons.attach_file),
                                label: Text('Upload ${doc['label']}')),
                          TextButton(
                              onPressed: () => _sendResponse(application),
                              child: const Text('Send response to council')),
                        ],
                        for (final charge
                            in (application['charges'] as List<dynamic>? ??
                                    const [])
                                .where((item) =>
                                    (item['balance_minor'] as num? ?? 0) > 0))
                          TextButton(
                              onPressed: () => _createPayment(
                                  application, charge as Map<String, dynamic>),
                              child: Text(
                                  'Pay ${charge['stage']} charge • ${charge['currency']} ${((charge['balance_minor'] as num).toInt() / 100).toStringAsFixed(2)}')),
                        for (final certificate
                            in (application['certificates'] as List<dynamic>? ??
                                const []))
                          Wrap(
                              crossAxisAlignment: WrapCrossAlignment.center,
                              children: [
                                Text(
                                    'Certificate ${certificate['reference']} v${certificate['version']}'),
                                TextButton(
                                    onPressed: () => _downloadCertificate(
                                        certificate as Map<String, dynamic>),
                                    child: const Text('Download PDF'))
                              ]),
                        for (final payment
                            in (application['refund_eligible_payments']
                                    as List<dynamic>? ??
                                const []))
                          TextButton(
                              onPressed: () async {
                                final note = await _askRefundReason();
                                if (note != null && note.trim().isNotEmpty) {
                                  await _requestRefund(
                                      application,
                                      payment as Map<String, dynamic>,
                                      note.trim());
                                }
                              },
                              child: Text(
                                  'Request refund from ${payment['reference']}')),
                        for (final refund
                            in (application['refunds'] as List<dynamic>? ??
                                const []))
                          Text(
                              'Refund ${refund['refund_number']}: ${refund['status']}'),
                        if (application['licence_id'] != null &&
                            application['status'] == 'issued')
                          TextButton(
                              onPressed: () => _startRenewal(application),
                              child: const Text('Start renewal draft')),
                      ]))),
      ]),
    );
  }
}
