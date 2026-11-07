import {
  AlertOutlined,
  BankOutlined,
  TeamOutlined,
  UserAddOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import { Alert, Empty, Spin, Tag } from 'antd';
import { useEffect, useState } from 'react';
import { fetchDashboardSummary, type DashboardSummary } from '../api/dashboard';
import { apiErrorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { KpiCard } from '../components/KpiCard';
import { EmptyStateIllustration } from '../components/illustrations/EmptyStateIllustration';
import { RankedBarList } from '../components/RankedBarList';
import { TrendCard } from '../components/TrendCard';
import { AppShell } from '../layouts/AppShell';
import { money, moneyCompact, number } from '../utils/format';

const STAGE_COLORS: Record<string, string> = {
  Applied: 'linear-gradient(90deg, #94a3b8, #64748b)',
  'GRT Pending': 'linear-gradient(90deg, #fbbf24, #d97706)',
  Approved: 'linear-gradient(90deg, #38bdf8, #0284c7)',
  Disbursed: 'linear-gradient(90deg, #a5b4fc, #6366f1)',
  Active: 'linear-gradient(90deg, #4ade80, #16a34a)',
  Closed: 'linear-gradient(90deg, #94a3b8, #475569)',
  Rejected: 'linear-gradient(90deg, #f87171, #dc2626)',
  Preclosed: 'linear-gradient(90deg, #c084fc, #9333ea)',
};

const BRANCH_GRADIENT = 'linear-gradient(90deg, #e8b95a, #c8952c)';

export default function DashboardPage() {
  const [data, setData] = useState<DashboardSummary | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboardSummary()
      .then(setData)
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }, []);

  return (
    <AppShell title="Dashboard">
      {loading ? (
        <div className="flex h-96 items-center justify-center">
          <Spin size="large" />
        </div>
      ) : error ? (
        <Alert type="error" showIcon message={error} />
      ) : data ? (
        <DashboardContent data={data} />
      ) : (
        <Empty image={<EmptyStateIllustration className="mx-auto h-28 w-auto" />} />
      )}
    </AppShell>
  );
}

function DashboardContent({ data }: { data: DashboardSummary }) {
  const { staff } = useAuth();
  const { kpis, loanPipeline, branchPortfolio, todayVsYesterday, monthVsLastMonth, recentOverdue, recentLeads } = data;
  const firstName = staff?.fullName?.trim().split(' ')[0] ?? '';

  return (
    <div className="flex flex-col gap-6">
      {/* Welcome banner */}
      <div
        className="relative flex items-center overflow-hidden rounded-2xl px-8 py-10 text-white shadow-sm"
        style={{
          backgroundImage: 'url(/dashboard-banner.png)',
          backgroundSize: 'cover',
          backgroundPosition: 'center',
        }}
      >
        <div className="relative z-10 max-w-md">
          <h2 className="m-0 text-xl font-bold">Welcome back{firstName ? `, ${firstName}` : ''}</h2>
          <p className="mt-1 text-sm text-white/70">Here's how your branches are performing today.</p>
        </div>
      </div>

      {/* KPI row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard
          icon={<TeamOutlined />}
          label="Total Members"
          value={number(kpis.totalMembers)}
          gradient={['#2563eb', '#1e3a8a']}
        />
        <KpiCard
          icon={<WalletOutlined />}
          label="Active Loans"
          value={number(kpis.activeLoans)}
          gradient={['#059669', '#065f46']}
        />
        <KpiCard
          icon={<BankOutlined />}
          label="Outstanding Portfolio"
          value={moneyCompact(kpis.outstandingPortfolio)}
          subValue={`Lifetime disbursed ${moneyCompact(kpis.lifetimeDisbursed)}`}
          gradient={['#c8952c', '#8a5a0a']}
        />
        <KpiCard
          icon={<AlertOutlined />}
          label="Overdue Installments"
          value={number(kpis.overdueCount)}
          subValue={moneyCompact(kpis.overdueAmount) + ' at risk'}
          gradient={['#dc2626', '#7f1d1d']}
        />
      </div>

      {/* Trend comparison row */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TrendCard
          title="Collections -- Today vs Yesterday"
          currentLabel="Collected today"
          current={todayVsYesterday.todayCollected}
          previousLabel="Collected yesterday"
          previous={todayVsYesterday.yesterdayCollected}
          format={money}
        />
        <TrendCard
          title="Collections -- This Month vs Last Month"
          currentLabel="This month"
          current={monthVsLastMonth.monthCollection}
          previousLabel="Last month"
          previous={monthVsLastMonth.lastMonthCollection}
          format={money}
        />
      </div>

      {/* Charts row */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-5 flex items-center justify-between">
            <h3 className="text-sm font-bold text-slate-700">Loan Pipeline</h3>
            <span className="text-xs font-medium text-slate-400">{loanPipeline.reduce((s, r) => s + r.count, 0)} loans total</span>
          </div>
          <RankedBarList
            items={loanPipeline.map((s) => ({
              label: s.stage,
              value: s.count,
              displayValue: number(s.count),
              color: STAGE_COLORS[s.stage] ?? '#94a3b8',
            }))}
          />
        </div>
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-5 flex items-center justify-between">
            <h3 className="text-sm font-bold text-slate-700">Branch-wise Outstanding Portfolio</h3>
            <span className="text-xs font-medium text-slate-400">{branchPortfolio.length} branches</span>
          </div>
          <RankedBarList
            items={branchPortfolio.map((b) => ({
              label: b.branch.trim(),
              value: b.portfolio,
              displayValue: money(b.portfolio),
              color: BRANCH_GRADIENT,
            }))}
          />
        </div>
      </div>

      {/* Activity row */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <h3 className="mb-4 text-sm font-bold text-slate-700">Most Overdue Installments</h3>
          <div className="flex flex-col divide-y divide-slate-50">
            {recentOverdue.length === 0 && (
              <Empty image={<EmptyStateIllustration className="mx-auto h-20 w-auto" />} description="No overdue installments" />
            )}
            {recentOverdue.map((row, i) => (
              <div key={`${row.loanId}-${i}`} className="flex items-center justify-between py-3">
                <div>
                  <div className="text-sm font-semibold text-slate-800">
                    {row.firstName} {row.lastName}
                  </div>
                  <div className="text-xs text-slate-400">
                    {row.loanNo} · {row.branchName}
                  </div>
                </div>
                <div className="text-right">
                  <div className="text-sm font-bold text-red-500">{money(row.emiAmount)}</div>
                  <Tag color="red" className="mt-0.5">
                    {row.daysOverdue}d overdue
                  </Tag>
                </div>
              </div>
            ))}
          </div>
        </div>
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <h3 className="mb-4 flex items-center gap-2 text-sm font-bold text-slate-700">
            <UserAddOutlined /> Recent Leads
          </h3>
          <div className="flex flex-col divide-y divide-slate-50">
            {recentLeads.length === 0 && <Empty image={<EmptyStateIllustration className="mx-auto h-20 w-auto" />} description="No leads yet" />}
            {recentLeads.map((row) => (
              <div key={row.memberId} className="flex items-center justify-between py-3">
                <div>
                  <div className="text-sm font-semibold text-slate-800">
                    {row.firstName} {row.lastName}
                  </div>
                  <div className="text-xs text-slate-400">{row.branchName}</div>
                </div>
                <Tag color="blue">{row.stageName}</Tag>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
