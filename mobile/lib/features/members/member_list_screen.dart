import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/theme/app_colors.dart';
import '../../l10n/generated/app_localizations.dart';
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
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.membersListTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.qr_code_scanner_rounded),
            tooltip: l10n.membersListScanQrTooltip,
            onPressed: () => context.push('/members/scan-qr'),
          ),
          const AccountMenuButton(),
          const SizedBox(width: 8),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(64),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: Row(
              children: [
                Expanded(
                  flex: 3,
                  child: TextField(
                    decoration: InputDecoration(
                      hintText: l10n.membersListSearchHint,
                      hintStyle: TextStyle(fontSize: 13, color: AppColors.textFaint(context)),
                      prefixIcon: const Icon(Icons.search_rounded, size: 20),
                      filled: true,
                      fillColor: AppColors.surface(context),
                      isDense: true,
                      contentPadding: const EdgeInsets.symmetric(vertical: 12),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
                    ),
                    onChanged: context.read<MemberListViewModel>().onSearchChanged,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  flex: 2,
                  child: _StageFilterDropdown(
                    value: vm.stageId,
                    onChanged: context.read<MemberListViewModel>().onStageChanged,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () => context.read<MemberListViewModel>().loadInitial(),
        child: _buildBody(context, vm),
      ),
    );
  }

  Widget _buildBody(BuildContext context, MemberListViewModel vm) {
    final l10n = AppLocalizations.of(context)!;
    if (vm.loading && vm.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.items.isEmpty) {
      return _ErrorView(message: vm.error!, onRetry: vm.loadInitial);
    }
    if (vm.items.isEmpty) {
      return Center(child: Text(l10n.membersListEmpty));
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

/// Mirrors the live Lead_Stage_Master table (fixed, rarely-changing system
/// stages) so the filter doesn't need a round trip to a lookups endpoint.
/// The `id` (not the display name) is what's sent to the API, so the label
/// shown here can be localized freely.
const _leadStageIds = [1, 2, 3, 4, 5, 6];

String _leadStageName(AppLocalizations l10n, int id) {
  switch (id) {
    case 1:
      return l10n.membersListStageNewLead;
    case 2:
      return l10n.membersListStageContacted;
    case 3:
      return l10n.membersListStageKycPending;
    case 4:
      return l10n.membersListStageVerified;
    case 5:
      return l10n.membersListStageConverted;
    case 6:
      return l10n.membersListStageDropped;
    default:
      return '';
  }
}

class _StageFilterDropdown extends StatelessWidget {
  final int? value;
  final ValueChanged<int?> onChanged;

  const _StageFilterDropdown({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Container(
      height: 44,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(24),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int?>(
          value: value,
          isExpanded: true,
          icon: const Icon(Icons.filter_list_rounded, size: 18),
          hint: Text(l10n.membersListStageHint, style: TextStyle(fontSize: 13, color: AppColors.textFaint(context))),
          style: TextStyle(fontSize: 13, color: AppColors.textPrimary(context)),
          items: [
            DropdownMenuItem<int?>(value: null, child: Text(l10n.membersListAllStages)),
            for (final id in _leadStageIds) DropdownMenuItem<int?>(value: id, child: Text(_leadStageName(l10n, id))),
          ],
          onChanged: onChanged,
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onRetry, child: Text(l10n.commonRetry)),
        ],
      ),
    );
  }
}
