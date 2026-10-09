import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/theme/app_colors.dart';
import '../members/widgets/member_list_tile.dart';
import 'existing_clients_viewmodel.dart';

/// Second step of "Existing Client Journey" -- one center's clients who
/// already have a disbursed loan. Tapping a client jumps straight into the
/// Loan Proposal screen for them (same destination the Reloan screen and
/// Member Detail's "New Loan" button already use).
class ExistingClientsScreen extends StatelessWidget {
  final int centerId;
  final String centerName;

  const ExistingClientsScreen({super.key, required this.centerId, required this.centerName});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => ExistingClientsViewModel(context.read<MembersApi>(), centerId),
      child: _ExistingClientsView(centerName: centerName),
    );
  }
}

class _ExistingClientsView extends StatefulWidget {
  final String centerName;

  const _ExistingClientsView({required this.centerName});

  @override
  State<_ExistingClientsView> createState() => _ExistingClientsViewState();
}

class _ExistingClientsViewState extends State<_ExistingClientsView> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      context.read<ExistingClientsViewModel>().loadMore();
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<ExistingClientsViewModel>();

    return Scaffold(
      appBar: AppBar(title: Text(widget.centerName)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: TextField(
              controller: _searchController,
              onChanged: context.read<ExistingClientsViewModel>().onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Search name, mobile or enrollment no...',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: vm.query.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close_rounded),
                        onPressed: () {
                          _searchController.clear();
                          context.read<ExistingClientsViewModel>().onSearchChanged('');
                        },
                      ),
                filled: true,
                fillColor: AppColors.surface(context),
                contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: AppColors.border(context))),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: AppColors.border(context))),
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => context.read<ExistingClientsViewModel>().loadInitial(),
              child: _buildBody(vm),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(ExistingClientsViewModel vm) {
    if (vm.loading && vm.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.items.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 60),
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(vm.error!, textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  OutlinedButton(onPressed: vm.loadInitial, child: const Text('Retry')),
                ],
              ),
            ),
          ),
        ],
      );
    }
    if (vm.items.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 60),
            child: Center(
              child: Text(
                vm.query.isEmpty ? 'No existing clients in this center yet.' : 'No clients match "${vm.query}"',
                textAlign: TextAlign.center,
              ),
            ),
          ),
        ],
      );
    }
    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      itemCount: vm.items.length + (vm.hasMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (index >= vm.items.length) {
          return const Padding(
            padding: EdgeInsets.all(16),
            child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
          );
        }
        final member = vm.items[index];
        return MemberListTile(
          member: member,
          onTap: () => context.push('/client-sourcing/loan-proposal?member_id=${member.memberId}&coborrower_name='),
        );
      },
    );
  }
}
