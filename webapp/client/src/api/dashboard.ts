import { apiClient } from './client';

export interface DashboardKpis {
  totalMembers: number;
  activeLoans: number;
  outstandingPortfolio: number;
  lifetimeDisbursed: number;
  overdueCount: number;
  overdueAmount: number;
}

export interface StageCount {
  stage: string;
  count: number;
  amount?: number;
}

export interface BranchPortfolioRow {
  branch: string;
  portfolio: number;
  disbursed: number;
}

export interface TodayVsYesterday {
  todayDue: number;
  todayCollected: number;
  yesterdayDue: number;
  yesterdayCollected: number;
}

export interface MonthVsLastMonth {
  monthDisbCount: number;
  monthCollection: number;
  lastMonthDisbCount: number;
  lastMonthCollection: number;
}

export interface OverdueRow {
  loanId: number;
  loanNo: string;
  firstName: string;
  lastName: string;
  branchName: string;
  dueDate: string;
  emiAmount: number;
  daysOverdue: number;
}

export interface RecentLeadRow {
  memberId: number;
  firstName: string;
  lastName: string;
  branchName: string;
  stageName: string;
}

export interface DashboardSummary {
  kpis: DashboardKpis;
  loanPipeline: StageCount[];
  leadFunnel: StageCount[];
  branchPortfolio: BranchPortfolioRow[];
  todayVsYesterday: TodayVsYesterday;
  monthVsLastMonth: MonthVsLastMonth;
  recentOverdue: OverdueRow[];
  recentLeads: RecentLeadRow[];
}

export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const res = await apiClient.get('/dashboard/summary');
  return res.data.data;
}
