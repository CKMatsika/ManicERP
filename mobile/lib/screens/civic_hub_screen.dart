import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../models/models.dart';

class CivicHubScreen extends StatefulWidget {
  const CivicHubScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<CivicHubScreen> createState() => _CivicHubScreenState();
}

class _CivicHubScreenState extends State<CivicHubScreen> {
  late Future<List<CivicItem>> _items;
  String _selectedFilter = 'all';

  @override
  void initState() {
    super.initState();
    _items = _fetchItems();
  }

  Future<List<CivicItem>> _fetchItems([String? type]) async {
    final query = (type != null && type != 'all') ? '?type=$type' : '';
    final res = await widget.api.get('/api/v1/civic$query');
    final data = (res['data'] as List<dynamic>? ?? const []);
    return data.map((e) => CivicItem.fromJson(e as Map<String, dynamic>)).toList();
  }

  void _filter(String type) {
    setState(() {
      _selectedFilter = type;
      _items = _fetchItems(type);
    });
  }

  void _showItemDetails(CivicItem item) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(item.title),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (item.category != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE8F5E9),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(item.category!, style: const TextStyle(color: Color(0xFF1B5E20), fontWeight: FontWeight.bold, fontSize: 12)),
                ),
              const SizedBox(height: 12),
              if (item.resolutionNumber != null)
                Text('Official Resolution: ${item.resolutionNumber}', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.indigo)),
              if (item.eventDate != null)
                Text('Scheduled Date: ${item.eventDate} • Venue: ${item.venue ?? 'Civic Centre'}', style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.deepOrange)),
              const SizedBox(height: 8),
              Text(item.content ?? item.summary, style: const TextStyle(fontSize: 14, height: 1.4)),
              if (item.authorRole != null) ...[
                const SizedBox(height: 16),
                Text('Issued by: ${item.authorRole}', style: const TextStyle(fontStyle: FontStyle.italic, color: Colors.grey, fontSize: 12)),
              ],
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Civic Engagement Hub'),
      ),
      body: Column(
        children: [
          // Filter Chips
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                _buildFilterChip('all', 'All Notices', Icons.dashboard_outlined),
                const SizedBox(width: 8),
                _buildFilterChip('event', 'Budget & Events', Icons.event_outlined),
                const SizedBox(width: 8),
                _buildFilterChip('resolution', 'Council Resolutions', Icons.gavel_outlined),
                const SizedBox(width: 8),
                _buildFilterChip('news', 'Municipal News', Icons.newspaper_outlined),
                const SizedBox(width: 8),
                _buildFilterChip('newsletter', 'Newsletters', Icons.menu_book_outlined),
              ],
            ),
          ),
          const Divider(height: 1),
          // Content list
          Expanded(
            child: FutureBuilder<List<CivicItem>>(
              future: _items,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (snapshot.hasError) {
                  return Center(child: Text(snapshot.error.toString()));
                }

                final items = snapshot.data ?? [];
                if (items.isEmpty) {
                  return const Center(
                    child: Text('No notices found under this category.'),
                  );
                }

                return ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (context, idx) {
                    final item = items[idx];
                    return _CivicCard(item: item, onTap: () => _showItemDetails(item));
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String key, String label, IconData icon) {
    final isSelected = _selectedFilter == key;
    return FilterChip(
      selected: isSelected,
      label: Text(label),
      avatar: Icon(icon, size: 16, color: isSelected ? Colors.white : const Color(0xFF0D5C3A)),
      selectedColor: const Color(0xFF0D5C3A),
      labelStyle: TextStyle(
        color: isSelected ? Colors.white : Colors.black87,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
      ),
      onSelected: (_) => _filter(key),
    );
  }
}

class _CivicCard extends StatelessWidget {
  const _CivicCard({required this.item, required this.onTap});

  final CivicItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    Color typeColor = const Color(0xFF0D5C3A);
    IconData typeIcon = Icons.info_outline;

    switch (item.type) {
      case 'event':
        typeColor = const Color(0xFFD97706);
        typeIcon = Icons.event;
        break;
      case 'resolution':
        typeColor = const Color(0xFF4338CA);
        typeIcon = Icons.gavel;
        break;
      case 'newsletter':
        typeColor = const Color(0xFF0891B2);
        typeIcon = Icons.menu_book;
        break;
      case 'news':
      default:
        typeColor = const Color(0xFF0D5C3A);
        typeIcon = Icons.newspaper;
    }

    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: typeColor.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(typeIcon, size: 14, color: typeColor),
                        const SizedBox(width: 4),
                        Text(
                          item.category ?? item.type.toUpperCase(),
                          style: TextStyle(color: typeColor, fontWeight: FontWeight.bold, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                  const Spacer(),
                  if (item.publishedDate != null)
                    Text(item.publishedDate!, style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                item.title,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
              ),
              const SizedBox(height: 6),
              Text(
                item.summary,
                style: TextStyle(color: Colors.grey.shade800, fontSize: 13, height: 1.3),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              if (item.resolutionNumber != null) ...[
                const SizedBox(height: 8),
                Text('Resolution Ref: ${item.resolutionNumber}', style: const TextStyle(color: Color(0xFF4338CA), fontWeight: FontWeight.w600, fontSize: 12)),
              ],
              if (item.eventDate != null) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    const Icon(Icons.location_on_outlined, size: 14, color: Colors.deepOrange),
                    const SizedBox(width: 4),
                    Text(item.venue ?? 'Council Venue', style: const TextStyle(fontSize: 12, color: Colors.deepOrange, fontWeight: FontWeight.w500)),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

