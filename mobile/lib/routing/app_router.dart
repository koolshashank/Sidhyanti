import 'package:go_router/go_router.dart';

import '../core/auth/auth_state.dart';
import '../features/account/account_screen.dart';
import '../features/area_manager/am_loan_approval_screen.dart';
import '../features/area_manager/center_transfer_screen.dart';
import '../features/area_manager/tvr_screen.dart';
import '../features/auth/login_screen.dart';
import '../features/auth/splash_screen.dart';
import '../features/bm_approval/bm_approval_screen.dart';
import '../features/bm_approval/proposal_review_screen.dart';
import '../features/hr/attendance_gate_screen.dart';
import '../features/center_approval/center_approval_screen.dart';
import '../features/center_approval/center_review_screen.dart';
import '../features/collections/arrear_center_detail_screen.dart';
import '../features/collections/collection_center_detail_screen.dart';
import '../features/collections/collections_home_screen.dart';
import '../features/dashboard/dashboard_screen.dart';
import '../features/dcr/dcr_screen.dart';
import '../features/disbursement/disbursement_screen.dart';
import '../features/hr/hr_screen.dart';
import '../features/loans/grt_screen.dart';
import '../features/loans/loan_detail_screen.dart';
import '../features/members/death_entry_screen.dart';
import '../features/members/member_detail_screen.dart';
import '../features/members/member_documents_screen.dart';
import '../features/members/member_list_screen.dart';
import '../features/members/member_repayment_history_screen.dart';
import '../features/members/member_stage_history_screen.dart';
import '../features/notifications/notifications_screen.dart';
import '../features/operations/cgt_screen.dart';
import '../features/operations/client_sourcing_hub_screen.dart';
import '../features/operations/existing_client_centers_screen.dart';
import '../features/operations/existing_clients_screen.dart';
import '../features/operations/client_sourcing_screen.dart';
import '../features/operations/coborrower_screen.dart';
import '../features/operations/kyc_capture_screen.dart';
import '../features/operations/loan_proposal_screen.dart';
import '../features/operations/create_center_screen.dart';
import '../features/operations/my_centers_screen.dart';
import '../features/operations/pending_journeys_screen.dart';
import '../features/reloan/reloan_screen.dart';
import '../features/reports/reports_screen.dart';
import '../shell/app_shell.dart';

GoRouter buildAppRouter(AuthState authState) {
  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: authState,
    redirect: (context, state) {
      final at = state.matchedLocation;
      switch (authState.status) {
        case AuthStatus.unknown:
          return at == '/splash' ? null : '/splash';
        case AuthStatus.unauthenticated:
          return at == '/login' ? null : '/login';
        case AuthStatus.authenticated:
          if (authState.attendanceRequired) {
            return at == '/attendance-gate' ? null : '/attendance-gate';
          }
          return (at == '/login' || at == '/splash' || at == '/attendance-gate') ? '/dashboard' : null;
      }
    },
    routes: [
      GoRoute(path: '/splash', builder: (context, state) => const SplashScreen()),
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(path: '/attendance-gate', builder: (context, state) => const AttendanceGateScreen()),
      ShellRoute(
        builder: (context, state, child) => AppShell(child: child),
        routes: [
          GoRoute(path: '/dashboard', builder: (context, state) => const DashboardScreen()),
          GoRoute(path: '/hr', builder: (context, state) => const HrScreen()),
          GoRoute(path: '/reports', builder: (context, state) => const ReportsScreen()),
        ],
      ),
      GoRoute(path: '/members', builder: (context, state) => const MemberListScreen()),
      GoRoute(path: '/account', builder: (context, state) => const AccountScreen()),
      GoRoute(path: '/bm-approval', builder: (context, state) => const BmApprovalScreen()),
      GoRoute(path: '/disbursement', builder: (context, state) => const DisbursementScreen()),
      GoRoute(path: '/dcr', builder: (context, state) => const DcrScreen()),
      GoRoute(path: '/center-approval', builder: (context, state) => const CenterApprovalScreen()),
      GoRoute(
        path: '/centers/:id/review',
        builder: (context, state) => CenterReviewScreen(centerId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(path: '/reloan', builder: (context, state) => const ReloanScreen()),
      GoRoute(path: '/am-loan-approval', builder: (context, state) => const AmLoanApprovalScreen()),
      GoRoute(path: '/am-center-transfer', builder: (context, state) => const CenterTransferScreen()),
      GoRoute(path: '/am-tvr', builder: (context, state) => const TvrScreen()),
      GoRoute(path: '/notifications', builder: (context, state) => const NotificationsScreen()),
      GoRoute(path: '/collections', builder: (context, state) => const CollectionsHomeScreen()),
      GoRoute(
        path: '/collections/center/:id',
        builder: (context, state) {
          final qp = state.uri.queryParameters;
          return CollectionCenterDetailScreen(
            centerId: int.parse(state.pathParameters['id']!),
            centerName: qp['center_name'] ?? '',
            dateFrom: DateTime.parse(qp['date_from']!),
            dateTo: DateTime.parse(qp['date_to']!),
          );
        },
      ),
      GoRoute(
        path: '/collections/arrear-center/:id',
        builder: (context, state) => ArrearCenterDetailScreen(
          centerId: int.parse(state.pathParameters['id']!),
          centerName: state.uri.queryParameters['center_name'] ?? '',
        ),
      ),
      GoRoute(
        path: '/members/:id',
        builder: (context, state) => MemberDetailScreen(memberId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/members/:id/documents',
        builder: (context, state) => MemberDocumentsScreen(
          memberId: int.parse(state.pathParameters['id']!),
          memberName: state.uri.queryParameters['name'] ?? '',
        ),
      ),
      GoRoute(
        path: '/members/:id/repayment-history',
        builder: (context, state) => MemberRepaymentHistoryScreen(memberId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/members/:id/stage-history',
        builder: (context, state) => MemberStageHistoryScreen(memberId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/members/:id/death-entry',
        builder: (context, state) => DeathEntryScreen(
          memberId: int.parse(state.pathParameters['id']!),
          memberName: state.uri.queryParameters['name'] ?? '',
        ),
      ),
      GoRoute(
        path: '/loans/:id',
        builder: (context, state) => LoanDetailScreen(loanId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/loans/:id/review',
        builder: (context, state) => ProposalReviewScreen(loanId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/loans/:id/grt',
        builder: (context, state) {
          final qp = state.uri.queryParameters;
          return GrtScreen(
            loanId: int.parse(state.pathParameters['id']!),
            loanNo: qp['loan_no'] ?? '',
            applicationDate: qp['application_date'] != null ? DateTime.tryParse(qp['application_date']!) : null,
            loanAmount: double.tryParse(qp['loan_amount'] ?? '') ?? 0,
          );
        },
      ),
      GoRoute(path: '/client-sourcing', builder: (context, state) => const ClientSourcingHubScreen()),
      GoRoute(path: '/client-sourcing/centers', builder: (context, state) => const MyCentersScreen()),
      GoRoute(path: '/client-sourcing/pending', builder: (context, state) => const PendingJourneysScreen()),
      GoRoute(path: '/client-sourcing/existing-centers', builder: (context, state) => const ExistingClientCentersScreen()),
      GoRoute(
        path: '/client-sourcing/existing-centers/:id/clients',
        builder: (context, state) => ExistingClientsScreen(
          centerId: int.parse(state.pathParameters['id']!),
          centerName: state.uri.queryParameters['center_name'] ?? '',
        ),
      ),
      GoRoute(
        path: '/client-sourcing/new-lead',
        builder: (context, state) => ClientSourcingScreen(
          centerId: int.parse(state.uri.queryParameters['center_id']!),
          centerName: state.uri.queryParameters['center_name'] ?? '',
        ),
      ),
      GoRoute(
        path: '/client-sourcing/coborrower',
        builder: (context, state) => CoBorrowerScreen(
          memberId: int.parse(state.uri.queryParameters['member_id']!),
          memberName: state.uri.queryParameters['member_name'] ?? '',
        ),
      ),
      GoRoute(
        path: '/client-sourcing/kyc',
        builder: (context, state) {
          final qp = state.uri.queryParameters;
          return KycCaptureScreen(
            flow: qp['flow'] ?? 'member',
            memberId: int.parse(qp['member_id']!),
            memberName: qp['member_name'] ?? '',
            coBorrowerId: int.parse(qp['coborrower_id']!),
            coBorrowerName: qp['coborrower_name'] ?? '',
          );
        },
      ),
      GoRoute(
        path: '/client-sourcing/loan-proposal',
        builder: (context, state) => LoanProposalScreen(
          memberId: int.parse(state.uri.queryParameters['member_id']!),
          coBorrowerName: state.uri.queryParameters['coborrower_name'] ?? '',
        ),
      ),
      GoRoute(path: '/create-center', builder: (context, state) => const CreateCenterScreen()),
      GoRoute(
        path: '/cgt',
        builder: (context, state) => CgtScreen(
          centerId: int.parse(state.uri.queryParameters['center_id']!),
          centerName: state.uri.queryParameters['center_name'] ?? '',
        ),
      ),
    ],
  );
}
