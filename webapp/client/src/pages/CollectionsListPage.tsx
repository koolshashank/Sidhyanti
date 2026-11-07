import { BankOutlined, DownloadOutlined, SearchOutlined, TeamOutlined, WalletOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, Input, Progress, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import {
  downloadCollectionsCsv,
  fetchCollectionList,
  fetchCollectionLookups,
  type CollectionLookups,
  type CollectionRow,
  type CollectionStats,
  type CollectionStatus,
} from '../api/collections';
import { apiErrorMessage } from '../api/client';
import { KpiCard } from '../components/KpiCard';
import { AppShell } from '../layouts/AppShell';
import { fdate, money, number } from '../utils/format';

const { RangePicker } = DatePicker;

const STATUS_COLORS: Record<CollectionStatus, string> = {
  Pending: 'orange',
  Partial: 'blue',
  Paid: 'success',
  Preclosed: 'purple',
};

export default function CollectionsListPage() {
  const [lookups, setLookups] = useState<CollectionLookups | null>(null);
  const [items, setItems] = useState<CollectionRow[]>([]);
  const [stats, setStats] = useState<CollectionStats | null>(null);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [q, setQ] = useState('');
  const [dateRange, setDateRange] = useState<[Dayjs, Dayjs]>([dayjs(), dayjs()]);
  const [branch, setBranch] = useState<number | undefined>();
  const [center, setCenter] = useState<number | undefined>();
  const [status, setStatus] = useState<CollectionStatus | undefined>();

  const dateFrom = dateRange[0].format('YYYY-MM-DD');
  const dateTo = dateRange[1].format('YYYY-MM-DD');

  useEffect(() => {
    fetchCollectionLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchCollectionList({ page, perPage, q: q || undefined, dateFrom, dateTo, branch, center, status })
      .then((res) => {
        setItems(res.items);
        setTotal(res.total);
        setStats(res.stats);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, q, dateFrom, dateTo, branch, center, status]);

  const centerOptions = useMemo(
    () => (lookups?.centers ?? []).filter((c) => !branch || c.branchId === branch).map((c) => ({ value: c.id, label: c.name })),
    [lookups, branch],
  );

  async function handleExport() {
    setExporting(true);
    try {
      await downloadCollectionsCsv({ q: q || undefined, dateFrom, dateTo, branch, center, status });
    } finally {
      setExporting(false);
    }
  }

  const columns: ColumnsType<CollectionRow> = [
    { title: 'Date', dataIndex: 'dueDate', render: (v) => fdate(v), width: 110 },
    {
      title: 'Branch / Center',
      key: 'location',
      render: (_, row) => (
        <div className="text-sm">
          <div className="text-slate-700">{row.branchName ?? '-'}</div>
          <div className="text-xs text-slate-400">{row.centerName ?? '-'}</div>
        </div>
      ),
    },
    {
      title: 'Borrower',
      key: 'borrower',
      render: (_, row) => (
        <div>
          <div className="font-medium text-slate-700">{row.borrowerName ?? '-'}</div>
          <div className="text-xs text-slate-400">{row.loanNo ?? '-'}</div>
        </div>
      ),
    },
    { title: 'Due', dataIndex: 'dueAmount', align: 'right', render: (v: number) => money(v) },
    { title: 'Collected', dataIndex: 'collectedAmount', align: 'right', render: (v: number) => money(v) },
    {
      title: 'Balance',
      key: 'balance',
      align: 'right',
      render: (_, row) => {
        const balance = Math.max(0, row.dueAmount - row.collectedAmount);
        return balance > 0 ? <span className="font-semibold text-red-600">{money(balance)}</span> : <span className="text-slate-400">-</span>;
      },
    },
    {
      title: 'Progress',
      key: 'progress',
      width: 140,
      render: (_, row) => {
        const pct = row.dueAmount > 0 ? Math.min(100, Math.round((row.collectedAmount / row.dueAmount) * 100)) : 0;
        return <Progress percent={pct} size="small" status={pct >= 100 ? 'success' : 'active'} />;
      },
    },
    {
      title: 'Status',
      dataIndex: 'status',
      render: (statusValue: CollectionStatus) => <Tag color={STATUS_COLORS[statusValue]}>{statusValue}</Tag>,
    },
    { title: 'Officer', dataIndex: 'officerName', render: (v) => v ?? 'Unassigned' },
  ];

  return (
    <AppShell title="Collections">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard icon={<TeamOutlined />} label="Clients Due" value={number(stats?.clientsDue ?? 0)} gradient={['#2563eb', '#1e3a8a']} />
          <KpiCard icon={<WalletOutlined />} label="Collection Due" value={money(stats?.dueAmount ?? 0)} gradient={['#c8952c', '#8a5a0a']} />
          <KpiCard
            icon={<BankOutlined />}
            label={`Collected (${stats?.collectionPct ?? 0}%)`}
            value={money(stats?.collectedAmount ?? 0)}
            gradient={['#059669', '#065f46']}
          />
          <KpiCard icon={<WalletOutlined />} label="Balance Pending" value={money(stats?.balanceAmount ?? 0)} gradient={['#dc2626', '#7f1d1d']} />
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Input
              placeholder="Borrower name or loan no..."
              prefix={<SearchOutlined className="text-slate-400" />}
              style={{ width: 240 }}
              value={q}
              onChange={(e) => {
                setPage(1);
                setQ(e.target.value);
              }}
              allowClear
            />
            <RangePicker
              value={dateRange}
              allowClear={false}
              format="DD MMM YYYY"
              onChange={(values) => {
                if (values && values[0] && values[1]) {
                  setPage(1);
                  setDateRange([values[0], values[1]]);
                }
              }}
            />
            <Select
              placeholder="All Branches"
              style={{ width: 170 }}
              allowClear
              value={branch}
              onChange={(v) => {
                setPage(1);
                setBranch(v);
                setCenter(undefined);
              }}
              options={lookups?.branches.map((b) => ({ value: b.id, label: b.name }))}
            />
            <Select
              placeholder="All Centers"
              style={{ width: 190 }}
              allowClear
              showSearch
              optionFilterProp="label"
              value={center}
              onChange={(v) => {
                setPage(1);
                setCenter(v);
              }}
              options={centerOptions}
            />
            <Select
              placeholder="All Status"
              style={{ width: 140 }}
              allowClear
              value={status}
              onChange={(v) => {
                setPage(1);
                setStatus(v);
              }}
              options={[
                { value: 'Pending', label: 'Pending' },
                { value: 'Partial', label: 'Partial' },
                { value: 'Paid', label: 'Paid' },
                { value: 'Preclosed', label: 'Preclosed' },
              ]}
            />
            <Button icon={<DownloadOutlined />} loading={exporting} onClick={handleExport} style={{ marginLeft: 'auto' }}>
              Export CSV
            </Button>
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<CollectionRow>
            rowKey="sheetId"
            columns={columns}
            dataSource={items}
            loading={loading}
            pagination={{
              current: page,
              pageSize: perPage,
              total,
              onChange: (p, ps) => {
                setPage(p);
                setPerPage(ps);
              },
              showTotal: (t) => `${t} installments due`,
            }}
          />
        </div>
      </div>
    </AppShell>
  );
}
