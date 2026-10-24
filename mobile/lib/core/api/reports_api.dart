import 'package:intl/intl.dart';

import '../models/day_closing_report.dart';
import '../models/hr.dart';
import 'api_client.dart';
import 'endpoints.dart';

final _isoDate = DateFormat('yyyy-MM-dd');

class ReportsApi {
  final ApiClient _client;

  ReportsApi(this._client);

  Future<MyPerformance> myPerformance() async {
    final data = await _client.get(Endpoints.reportsMyPerformance);
    return MyPerformance.fromJson(data);
  }

  /// The auto-computed figures to prefill the Day Closing Report form for
  /// [date] (defaults to today), for the caller's own branch.
  Future<DayClosingPrefill> dcrClosingPrefill({DateTime? date}) async {
    final data = await _client.get(Endpoints.reportsDcrClosingPrefill, query: {
      if (date != null) 'date': _isoDate.format(date),
    });
    return DayClosingPrefill.fromJson(data);
  }

  Future<DayClosingResult> dcrClosingSubmit({
    required DateTime date,
    required double fundTransferFromAnotherBranch,
    required double rentExpenses,
    required double stationaryAssets,
    required double salaryPayment,
    required double otherExpenses,
    required double pettyCashPayment,
    required double cashDepositByHoBank,
    required double cashDepositByBranchBank,
    required double enrollmentFee,
    required double riskFee,
    required double processingFee,
    required double otherIncome,
    required double bankWithdrawal,
    required double fundReceived,
    required double fundReceivedByHo,
  }) async {
    final data = await _client.post(Endpoints.reportsDcrClosingCreate, data: {
      'date': _isoDate.format(date),
      'fund_transfer_from_another_branch': fundTransferFromAnotherBranch,
      'rent_expenses': rentExpenses,
      'stationary_assets': stationaryAssets,
      'salary_payment': salaryPayment,
      'other_expenses': otherExpenses,
      'petty_cash_payment': pettyCashPayment,
      'cash_deposit_by_ho_bank': cashDepositByHoBank,
      'cash_deposit_by_branch_bank': cashDepositByBranchBank,
      'enrollment_fee': enrollmentFee,
      'risk_fee': riskFee,
      'processing_fee': processingFee,
      'other_income': otherIncome,
      'bank_withdrawal': bankWithdrawal,
      'fund_received': fundReceived,
      'fund_received_by_ho': fundReceivedByHo,
    });
    return DayClosingResult.fromJson(data);
  }
}
