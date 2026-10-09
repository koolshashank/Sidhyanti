import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/theme/app_colors.dart';
import 'dormant_clients_viewmodel.dart';
import 'widgets/member_list_tile.dart';

/// "Dormant Client" dashboard card -- members whose loan has closed and who
/// aren't currently borrowing. Tapping one opens their Member Detail screen
/// (same place a fresh/reloan proposal would be started from).
class DormantClientsScreen extends StatelessWidget {
  const DormantClientsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => DormantClientsViewModel(context.read<MembersApi>()),
      child: const _DormantClientsView(),
    );
  }
}

class _DormantClientsView extends StatefulWidget {
  const _DormantClientsView();

  @override
  State<_DormantClientsView> createState() => _DormantClientsViewState();
}

class _DormantClientsViewState extends State<_DormantClientsView> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      context.read<DormantClientsViewModel>().loadMore();
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
    final vm = context.watch<DormantClientsViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Dormant Clients')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: TextField(
              controller: _searchController,
              onChanged: context.read<DormantClientsViewModel>().onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Search name, mobile or enrollment no...',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: vm.query.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close_rounded),
                        onPressed: () {
                          _searchController.clear();
                          context.read<DormantClientsViewModel>().onSearchChanged('');
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
              onRefresh: () => context.read<DormantClientsViewModel>().loadInitial(),
              child: _buildBody(vm),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(DormantClientsViewModel vm) {
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
                vm.query.isEmpty ? 'No dormant clients right now.' : 'No clients match "${vm.query}"',
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
          onTap: () => context.push('/members/${member.memberId}'),
        );
      },
    );
  }
}
