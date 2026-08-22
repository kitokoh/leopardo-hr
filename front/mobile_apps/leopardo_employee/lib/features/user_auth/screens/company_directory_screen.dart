import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_employee/features/user_auth/data/user_auth_repository.dart';
import 'package:leopardo_employee/features/user_auth/providers/user_auth_provider.dart';

class CompanyDirectoryScreen extends ConsumerStatefulWidget {
  const CompanyDirectoryScreen({super.key});

  @override
  ConsumerState<CompanyDirectoryScreen> createState() => _CompanyDirectoryScreenState();
}

class _CompanyDirectoryScreenState extends ConsumerState<CompanyDirectoryScreen> {
  final _searchController = TextEditingController();
  List<Map<String, dynamic>> _companies = const [];
  bool _loading = false;
  String? _error;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _loadCompanies('');
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () => _loadCompanies(value));
  }

  Future<void> _loadCompanies(String search) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final companies = await ref.read(userAuthRepositoryProvider).searchCompanies(search);
      if (mounted) setState(() => _companies = companies);
    } catch (_) {
      if (mounted) setState(() => _error = 'Impossible de charger les entreprises.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _request(Map<String, dynamic> company) async {
    final companyId = company['id']?.toString();
    if (companyId == null || companyId.isEmpty) return;
    try {
      await ref.read(userAuthRepositoryProvider).requestToJoinCompany(companyId: companyId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Demande envoyée. L’entreprise doit maintenant l’accepter.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Demande impossible : $e')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = AppColors.textPrimaryFor(context);
    final muted = AppColors.textSecondaryFor(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rejoindre une entreprise'),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.pop()),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
        children: [
          Text('Trouvez votre entreprise', style: AppTypography.title.copyWith(color: text)),
          const SizedBox(height: 8),
          Text('Envoyez une demande. Le pointage sera activé uniquement après acceptation.', style: AppTypography.body.copyWith(color: muted)),
          const SizedBox(height: 20),
          TextField(
            controller: _searchController,
            onChanged: _onSearchChanged,
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'Nom, ville ou secteur',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          if (_loading) const Center(child: CircularProgressIndicator()),
          if (_error != null) Text(_error!, style: const TextStyle(color: AppColors.danger)),
          ..._companies.map((company) {
            final name = company['name']?.toString() ?? 'Entreprise';
            final details = [company['sector'], company['city']]
                .whereType<String>()
                .where((value) => value.isNotEmpty)
                .join(' · ');
            return Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                leading: const CircleAvatar(child: Icon(Icons.business_outlined)),
                title: Text(name),
                subtitle: Text(details.isEmpty ? 'Entreprise Leopardo' : details),
                trailing: FilledButton(
                  onPressed: () => _request(company),
                  child: const Text('Demander'),
                ),
              ),
            );
          }),
          if (!_loading && _error == null && _companies.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 32),
              child: Text('Aucune entreprise trouvée. Vérifiez le nom ou la ville.', textAlign: TextAlign.center, style: TextStyle(color: muted)),
            ),
        ],
      ),
    );
  }
}
