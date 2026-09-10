import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shell/account_menu_button.dart';
import 'member_list_viewmodel.dart';
import 'widgets/member_list_tile.dart';

class MemberListScreen extends StatelessWidget {
  const MemberListScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => MemberListViewModel(context.read<MembersApi>()),
      child: const _MemberListView(),
    );
  }
}

class _MemberListView extends StatefulWidget {
  const _MemberListView();

  @override
  State<_MemberListView> createState() => _MemberListViewState();
}

class _MemberListViewState extends State<_MemberListView> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      context.read<MemberListViewModel>().loadMore();
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<MemberListViewModel>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Members'),
        actions: const [AccountMenuButton(), SizedBox(width: 8)],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(64),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Search name, mobile or enrollment no...',
                hintStyle: TextStyle(fontSize: 13.5, color: AppColors.textFaint(context)),
                prefixIcon: const Icon(Icons.search_rounded),
                filled: true,
                fillColor: AppColors.surface(context),
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(vertical: 12),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
              ),
              onChanged: context.read<MemberListViewModel>().onSearchChanged,
            ),
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () => context.read<MemberListViewModel>().loadInitial(),
        child: _buildBody(vm),
      ),
    );
  }

  Widget _buildBody(MemberListViewModel vm) {
    if (vm.loading && vm.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.items.isEmpty) {
      return _ErrorView(message: vm.error!, onRetry: vm.loadInitial);
    }
    if (vm.items.isEmpty) {
      return const Center(child: Text('No members match your search.'));
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

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onRetry, child: const Text('Retry')),
        ],
      ),
    );
  }
}
