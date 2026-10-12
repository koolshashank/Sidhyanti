import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/models/member.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'member_documents_viewmodel.dart';

class MemberDocumentsScreen extends StatelessWidget {
  final int memberId;
  final String memberName;

  const MemberDocumentsScreen({super.key, required this.memberId, required this.memberName});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => MemberDocumentsViewModel(context.read<MembersApi>(), memberId),
      child: _MemberDocumentsView(memberName: memberName),
    );
  }
}

class _MemberDocumentsView extends StatelessWidget {
  final String memberName;

  const _MemberDocumentsView({required this.memberName});

  static const _icons = <String, IconData>{
    'Client Photograph': Icons.person_rounded,
    'Aadhar Card': Icons.badge_rounded,
    'Aadhar Front Image': Icons.badge_rounded,
    'Aadhar Back Image': Icons.badge_rounded,
    'PAN Card': Icons.credit_card_rounded,
    'PAN Card Image': Icons.credit_card_rounded,
    'Voter ID': Icons.how_to_vote_rounded,
    'Voter ID Front Image': Icons.how_to_vote_rounded,
    'Voter ID Back Image': Icons.how_to_vote_rounded,
    'Address Proof': Icons.home_rounded,
    'House Photo': Icons.house_rounded,
    'Business Photo': Icons.storefront_rounded,
    'Bank Passbook': Icons.account_balance_rounded,
    'GRT Document': Icons.groups_rounded,
  };

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<MemberDocumentsViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Documents')),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null
              ? _CenteredMessage(message: vm.error!, onRetry: vm.load)
              : vm.documents.isEmpty
                  ? const _CenteredMessage(message: 'No documents found for this member.')
                  : RefreshIndicator(
                      onRefresh: vm.load,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: vm.documents.length,
                        itemBuilder: (context, index) {
                          final doc = vm.documents[index];
                          return _DocumentTile(
                            doc: doc,
                            icon: _icons[doc.docType] ?? Icons.description_rounded,
                            onTap: () => _openViewer(context, doc),
                          );
                        },
                      ),
                    ),
    );
  }

  void _openViewer(BuildContext context, MemberDocument doc) {
    if (doc.fileUrl == null || doc.fileUrl!.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No file available for this document')),
      );
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute(builder: (context) => _DocumentImageViewer(doc: doc)),
    );
  }
}

class _DocumentTile extends StatelessWidget {
  final MemberDocument doc;
  final IconData icon;
  final VoidCallback onTap;

  const _DocumentTile({required this.doc, required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(12),
                    gradient: const LinearGradient(colors: [Color(0xFFE65100), Color(0xFFBF360C)]),
                  ),
                  child: Icon(icon, color: Colors.white, size: 20),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(doc.docType, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5)),
                      if (doc.docNumber != null && doc.docNumber!.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(doc.docNumber!, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                      ],
                      if (doc.uploadedAt != null) ...[
                        const SizedBox(height: 2),
                        Text('Uploaded ${fdate(doc.uploadedAt)}', style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
                      ],
                    ],
                  ),
                ),
                if (doc.isVerified)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFF2FBF87).withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Text('Verified', style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: Color(0xFF2FBF87))),
                  ),
                const SizedBox(width: 4),
                Icon(Icons.chevron_right_rounded, color: AppColors.textFaint(context)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DocumentImageViewer extends StatelessWidget {
  final MemberDocument doc;

  const _DocumentImageViewer({required this.doc});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(doc.docType),
      ),
      body: Center(
        child: InteractiveViewer(
          minScale: 0.8,
          maxScale: 4,
          // A spinner sits behind the image the whole time -- loadingBuilder
          // isn't invoked until the first byte arrives, so a dead/unreachable
          // legacy host would otherwise show nothing for the full timeout.
          child: Stack(
            alignment: Alignment.center,
            children: [
              const CircularProgressIndicator(color: Colors.white),
              Image.network(
                doc.fileUrl!,
                fit: BoxFit.contain,
                loadingBuilder: (context, child, progress) => progress == null ? child : const SizedBox.shrink(),
                errorBuilder: (context, error, stack) => const Padding(
                  padding: EdgeInsets.all(24),
                  child: Text(
                    "Couldn't load this document -- the file server may be unreachable.",
                    style: TextStyle(color: Colors.white),
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CenteredMessage extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;

  const _CenteredMessage({required this.message, this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center, style: TextStyle(color: AppColors.textMuted(context))),
            if (onRetry != null) ...[
              const SizedBox(height: 12),
              OutlinedButton(onPressed: onRetry, child: const Text('Retry')),
            ],
          ],
        ),
      ),
    );
  }
}
