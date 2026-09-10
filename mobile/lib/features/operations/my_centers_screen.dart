import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/centers_api.dart';
import '../../core/models/center_summary.dart';
import '../../core/theme/app_colors.dart';
import 'my_centers_viewmodel.dart';
import 'widgets/meeting_day.dart';

class MyCentersScreen extends StatelessWidget {
  const MyCentersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => MyCentersViewModel(context.read<CentersApi>()),
      child: const _MyCentersView(),
    );
  }
}

class _MyCentersView extends StatelessWidget {
  const _MyCentersView();

  Future<void> _openMap(BuildContext context, CenterSummary center) async {
    final lat = center.latitude;
    final lng = center.longitude;
    final uri = Uri.parse('geo:$lat,$lng?q=$lat,$lng(${Uri.encodeComponent(center.name)})');
    final fallback = Uri.parse('https://www.google.com/maps/search/?api=1&query=$lat,$lng');
    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication).catchError((_) => false);
    if (!launched) {
      await launchUrl(fallback, mode: LaunchMode.externalApplication);
    }
  }

  void _showCgtImage(BuildContext context, CenterSummary center) {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 4, 0),
              child: Row(
                children: [
                  Expanded(
                    child: Text(center.name,
                        style: const TextStyle(fontWeight: FontWeight.w700), overflow: TextOverflow.ellipsis),
                  ),
                  IconButton(icon: const Icon(Icons.close_rounded), onPressed: () => Navigator.of(context).pop()),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: SizedBox(
                  width: 320,
                  height: 320,
                  // A spinner sits behind the image the whole time -- Image.network's
                  // loadingBuilder isn't invoked at all until the first byte/response
                  // arrives, so a dead/unreachable host (some older CGT_Images URLs
                  // point at a host that's no longer live) would otherwise show
                  // nothing for the full connection timeout before the error appears.
                  child: Stack(
                    alignment: Alignment.center,
                    children: [
                      const CircularProgressIndicator(),
                      Image.network(
                        center.cgtImageUrl!,
                        fit: BoxFit.contain,
                        loadingBuilder: (context, child, progress) => progress == null ? child : const SizedBox.shrink(),
                        errorBuilder: (context, error, stack) => Container(
                          color: AppColors.surface(context),
                          alignment: Alignment.center,
                          child: const Padding(
                            padding: EdgeInsets.all(16),
                            child: Text(
                              "Couldn't load this photo -- the image server may be unreachable.",
                              textAlign: TextAlign.center,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _onTapCenter(BuildContext context, CenterSummary center) {
    if (center.cgtDone) {
      context.push('/client-sourcing/new-lead?center_id=${center.id}&center_name=${Uri.encodeComponent(center.name)}');
    } else {
      context.push('/cgt?center_id=${center.id}&center_name=${Uri.encodeComponent(center.name)}');
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<MyCentersViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Select Center')),
      body: RefreshIndicator(
        onRefresh: () => context.read<MyCentersViewModel>().load(),
        child: _buildBody(context, vm),
      ),
    );
  }

  Widget _buildBody(BuildContext context, MyCentersViewModel vm) {
    if (vm.loading && vm.centers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.centers.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(vm.error!),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: vm.load, child: const Text('Retry')),
          ],
        ),
      );
    }
    if (vm.centers.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          Padding(
            padding: EdgeInsets.only(top: 80),
            child: Center(
              child: Text('No centers yet. Create one from the dashboard first.', textAlign: TextAlign.center),
            ),
          ),
        ],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: vm.centers.length,
      itemBuilder: (context, index) {
        final center = vm.centers[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: AppColors.surface(context),
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border(context)),
            boxShadow: [
              BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 12, offset: const Offset(0, 5)),
            ],
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(18),
            onTap: () => _onTapCenter(context, center),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(center.name,
                                  style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context)),
                                  overflow: TextOverflow.ellipsis),
                            ),
                            const SizedBox(width: 8),
                            _CgtStatusChip(done: center.cgtDone),
                          ],
                        ),
                        if (center.code != null && center.code!.isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(
                            'Center ID: ${center.code}',
                            style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context), fontWeight: FontWeight.w600),
                          ),
                        ],
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            Icon(Icons.event_rounded, size: 14, color: AppColors.textFaint(context)),
                            const SizedBox(width: 4),
                            Text(formatMeetingDay(center.meetingDay),
                                style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                            const SizedBox(width: 12),
                            Icon(Icons.schedule_rounded, size: 14, color: AppColors.textFaint(context)),
                            const SizedBox(width: 4),
                            Text(center.meetingTime ?? '-', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                          ],
                        ),
                        if (center.address != null && center.address!.isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Icon(Icons.location_on_outlined, size: 14, color: AppColors.textFaint(context)),
                              const SizedBox(width: 4),
                              Expanded(
                                child: Text(
                                  center.address!,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                  Column(
                    children: [
                      if (center.hasLocation)
                        IconButton(
                          onPressed: () => _openMap(context, center),
                          icon: const Icon(Icons.map_rounded),
                          color: const Color(0xFF6A4FC7),
                          tooltip: 'Open in Maps',
                        ),
                      if (center.cgtDone && center.cgtImageUrl != null && center.cgtImageUrl!.isNotEmpty)
                        IconButton(
                          onPressed: () => _showCgtImage(context, center),
                          icon: const Icon(Icons.photo_camera_rounded),
                          color: const Color(0xFF2FBF87),
                          tooltip: 'View CGT Photo',
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

class _CgtStatusChip extends StatelessWidget {
  final bool done;

  const _CgtStatusChip({required this.done});

  @override
  Widget build(BuildContext context) {
    final color = done ? const Color(0xFF2FBF87) : const Color(0xFFC8952C);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(
        done ? 'CGT Done' : 'CGT Pending',
        style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: color),
      ),
    );
  }
}
