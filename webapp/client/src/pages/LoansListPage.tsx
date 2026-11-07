import { DownloadOutlined, FileTextOutlined, LineChartOutlined, PlusOutlined, SearchOutlined, WalletOutlined } from '@ant-design/icons';
import { Alert, Button, Input, Select, Table, Tabs, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import { downloadLoansCsv, fetchLoanList, fetchLoanLookups, type LoanListStats, type LoanLookups, type LoanRow } from '../api/loans';
import { KpiCard } from '../components/KpiCard';
import { AppShell } from '../layouts/AppShell';
import { fdate, money, number } from '../utils/format';

const STAGE_COLORS: Record<string, string> = {
  Applied: 'default',
  'GRT Pending': 'orange',
  Approved: 'blue',
  Disbursed: 'purple',
  Active: 'green',
  Closed: 'default',
  Rejected: 'red',
  Preclosed: 'purple',
};

export default function LoansListPage() {
  const navigate = useNavigate();
  const [lookups, setLookups] = useState<LoanLookups | null>(null);
  const [items, setItems] = useState<LoanRow[]>([]);
  const [stats, setStats] = useState<LoanListStats | null>(null);
  const [stageCounts, setStageCounts] = useState<Record<string, number>>({});
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [q, setQ] = useState('');
  const [branch, setBranch] = useState<number | undefined>();
  const [stage, setStage] = useState<string>('');

  useEffect(() => {
    fetchLoanLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchLoanList({ page, perPage, q: q || undefined, branch, stage: stage || undefined })
      .then((res) => {
        setItems(res.items);
        setTotal(res.total);
        setStats(res.stats);
        setStageCounts(res.stageCounts);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, q, branch, stage]);

  async function handleExport() {
    setExporting(true);
    try {
      await downloadLoansCsv({ q: q || undefined, branch, stage: stage || undefined });
    } finally {
      setExporting(false);
    }
  }

  const columns: ColumnsType<LoanRow> = [
    { title: 'Loan No.', dataIndex: 'loanNo', render: (v) => <span className="font-semibold text-slate-700">{v}</span> },
    {
      title: 'Member',
      key: 'member',
      render: (_, r) => `${r.firstName} ${r.lastName}`.trim() || '-',
    },
    { title: 'Branch', dataIndex: 'branchName', render: (v) => v ?? '-' },
    { title: 'Purpose', dataIndex: 'purposeName', render: (v) => v ?? '-' },
    { title: 'Officer', dataIndex: 'officerName', render: (v) => v?.trim() || 'Unassigned' },
    {
      title: 'Amount',
      key: 'amount',
      align: 'right',
      render: (_, r) => money(r.loanAmountSanctioned ?? r.loanAmountApplied ?? 0),
    },
    {
      title: 'Stage',
      dataIndex: 'stageName',
      render: (v: string | null) => <Tag color={STAGE_COLORS[v ?? ''] ?? 'default'}>{v ?? '-'}</Tag>,
    },
    { title: 'Applied', dataIndex: 'applicationDate', render: (v) => fdate(v) },
  ];

  const stageTabs = [
    { key: '', label: `All (${number(stats?.totalLoans ?? 0)})` },
    ...(lookups?.stages.map((s) => ({ key: s.name, label: `${s.name} (${number(stageCounts[s.name] ?? 0)})` })) ?? []),
  ];

  return (
    <AppShell title="Loan Pipeline">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard
            icon={<FileTextOutlined />}
            label={branch || q ? 'Loans (filtered)' : 'Loans in Pipeline'}
            value={number(stats?.totalLoans ?? 0)}
            gradient={['#2563eb', '#1e3a8a']}
          />
          <KpiCard icon={<WalletOutlined />} label="Total Pipeline Value" value={money(stats?.totalAmount ?? 0)} gradient={['#c8952c', '#8a5a0a']} />
          <KpiCard icon={<LineChartOutlined />} label="Active Loans" value={number(stats?.activeCount ?? 0)} gradient={['#059669', '#065f46']} />
          <KpiCard icon={<WalletOutlined />} label="Avg Ticket Size" value={money(stats?.avgTicket ?? 0)} gradient={['#7c3aed', '#4c1d95']} />
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <Tabs
            activeKey={stage}
            onChange={(key) => {
              setPage(1);
              setStage(key);
            }}
            items={stageTabs.map((t) => ({ key: t.key, label: t.label }))}
          />

          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Input
              placeholder="Loan no. or member name"
              prefix={<SearchOutlined className="text-slate-400" />}
              style={{ width: 240 }}
              value={q}
              onChange={(e) => {
                setPage(1);
                setQ(e.target.value);
              }}
              allowClear
            />
            <Select
              placeholder="All Branches"
              style={{ width: 170 }}
              allowClear
              value={branch}
              onChange={(v) => {
                setPage(1);
                setBranch(v);
              }}
              options={lookups?.branches.map((b) => ({ value: b.id, label: b.name }))}
            />
            <Button icon={<DownloadOutlined />} loading={exporting} onClick={handleExport} style={{ marginLeft: 'auto' }}>
              Export CSV
            </Button>
            <Button type="primary" icon={<PlusOutlined />} onClick={() => navigate('/loans/new')}>
              New Loan
            </Button>
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<LoanRow>
            rowKey="loanId"
            columns={columns}
            dataSource={items}
            loading={loading}
            onRow={(row) => ({ onClick: () => navigate(`/members/${row.memberId}`), className: 'cursor-pointer' })}
            pagination={{
              current: page,
              pageSize: perPage,
              total,
              onChange: (p, ps) => {
                setPage(p);
                setPerPage(ps);
              },
              showTotal: (t) => `${t} loans`,
            }}
          />
        </div>
      </div>
    </AppShell>
  );
}
