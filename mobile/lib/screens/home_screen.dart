import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/session_store.dart';
import '../models/models.dart';
import 'account_screen.dart';
import 'link_account_screen.dart';
import 'requests_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.api, required this.session, required this.onSignOut});

  final ApiClient api;
  final SessionStore session;
  final Future<void> Function() onSignOut;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<List<Account>> _accounts;
  String _councilName = 'Council';

  @override
  void initState() {
    super.initState();
    _accounts = _load();
    widget.session.readCouncilName().then((value) {
      if (mounted && value != null) setState(() => _councilName = value);
    });
  }

  Future<List<Account>> _load() async {
    final response = await widget.api.get('/api/v1/accounts');
    final data = (response['data'] as List<dynamic>? ?? const []);
    return data.map((item) => Account.fromJson(item as Map<String, dynamic>)).toList();
  }

  void _refresh() {
    setState(() => _accounts = _load());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('My accounts', style: TextStyle(fontSize: 18)),
            Text(_councilName, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.normal)),
          ],
        ),
        actions: [
          IconButton(onPressed: _refresh, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
          IconButton(onPressed: () => widget.onSignOut(), icon: const Icon(Icons.logout), tooltip: 'Sign out'),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => _refresh(),
        child: FutureBuilder<List<Account>>(
          future: _accounts,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }

            if (snapshot.hasError) {
              return _ErrorState(message: snapshot.error.toString(), onRetry: _refresh);
            }

            final accounts = snapshot.data ?? [];

            if (accounts.isEmpty) {
              return ListView(
                padding: const EdgeInsets.all(24),
                children: [
                  const Text('No accounts linked yet', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  const Text('Link an account with a verification code sent to the contact details held by the council.'),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () async {
                      await Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => LinkAccountScreen(api: widget.api),
                      ));
                      _refresh();
                    },
                    child: const Text('Link an account'),
                  ),
                ],
              );
            }

            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                for (final account in accounts)
                  Card(
                    child: ListTile(
                      title: Text(account.accountNumber, style: const TextStyle(fontFamily: 'monospace', fontSize: 14)),
                      subtitle: Text('${account.property ?? 'No property'} • outstanding ${account.outstandingLabel}'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () async {
                        await Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => AccountScreen(api: widget.api, account: account),
                        ));
                        _refresh();
                      },
                    ),
                  ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () async {
                          await Navigator.of(context).push(MaterialPageRoute(
                            builder: (_) => LinkAccountScreen(api: widget.api),
                          ));
                          _refresh();
                        },
                        icon: const Icon(Icons.link),
                        label: const Text('Link account'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () async {
                          await Navigator.of(context).push(MaterialPageRoute(
                            builder: (_) => RequestsScreen(api: widget.api, accounts: accounts),
                          ));
                        },
                        icon: const Icon(Icons.report_problem_outlined),
                        label: const Text('Requests'),
                      ),
                    ),
                  ],
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const Icon(Icons.cloud_off, size: 40),
        const SizedBox(height: 12),
        Text(message),
        const SizedBox(height: 16),
        FilledButton(onPressed: onRetry, child: const Text('Retry')),
      ],
    );
  }
}
