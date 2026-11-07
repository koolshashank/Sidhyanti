import { CheckCircleOutlined, CheckSquareOutlined, CloseCircleOutlined, DownloadOutlined, FileTextOutlined, SearchOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, Input, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import type { Dayjs } from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import { apiErrorMessage } from '../api/client';
import { downloadGrtCsv, fetchGrtList, fetchGrtLookups, type GrtLookups, type GrtRow, type GrtStats } from '../api/grt';
import { KpiCard } from '../components/KpiCard';
import { AppShell } from '../layouts/AppShell';
import { fdate, money, number } from '../utils/format';

const { RangePicker } = DatePicker;

function statusLabel(code: string): string {
  return code === 'P' ? 'Passed' : code === 'F' ? 'Failed' : code;
}

export default function GrtListPage() {
  const [lookups, setLookups] = useState<GrtLookups | null>(null);
  const [items, setItems] = useState<GrtRow[]>([]);
  const [stats, setStats] = useState<GrtStats | null>(null);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [q, setQ] = useState('');
  const [dateRange, setDateRange] = useState<[Dayjs, Dayjs] | null>(null);
  const [branch, setBranch] = useState<number | undefined>();
  const [center, setCenter] = useState<number | undefined>();
  const [status, setStatus] = useState<number | undefined>();

  const dateFrom = dateRange ? dateRange[0].format('YYYY-MM-DD') : undefined;
  const dateTo = dateRange ? dateRange[1].format('YYYY-MM-DD') : undefined;

  useEffect(() => {
    fetchGrtLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchGrtList({ page, perPage, q: q || undefined, dateFrom, dateTo, branch, center, status })
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
      await downloadGrtCsv({ q: q || undefined, dateFrom, dateTo, branch, center, status });
    } finally {
      setExporting(false);
    }
  }

  const columns: ColumnsType<GrtRow> = [
    {
      title: 'Loan No',
      dataIndex: 'loanNo',
      render: (v) => <span className="font-semibold text-slate-700">{v}</span>,
    },
    {
      title: 'Client',
      key: 'client',
      render: (_, row) => (
        <div>
          <div className="font-medium text-slate-800">{row.clientName}</div>
          {row.coBorrowerName && <div className="text-xs text-slate-400">Co-borrower: {row.coBorrowerName}</div>}
        </div>
      ),
    },
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
    { title: 'Finance Verified', dataIndex: 'financeAmount', align: 'right', render: (v: number | null) => money(v ?? 0) },
    {
      title: 'Status',
      dataIndex: 'statusCode',
      render: (code: string) =>
        code === 'P' ? (
          <Tag icon={<CheckCircleOutlined />} color="success">
            Passed
          </Tag>
        ) : code === 'F' ? (
          <Tag icon={<CloseCircleOutlined />} color="error">
            Failed
          </Tag>
        ) : (
          <Tag>{code}</Tag>
        ),
    },
    { title: 'Conducted By', dataIndex: 'conductedBy', render: (v) => v ?? 'Unassigned' },
    { title: 'GRT Date', dataIndex: 'grtDate', render: (v) => fdate(v) },
    {
      title: 'Document',
      key: 'document',
      render: (_, row) =>
        row.fileName ? (
          <a href={row.fileName} target="_blank" rel="noopener noreferrer">
            <Button icon={<FileTextOutlined />} size="small">
              View
            </Button>
          </a>
        ) : (
          <span className="text-slate-400">-</span>
        ),
    },
  ];

  return (
    <AppShell title="GRT (Group Recognition Test)">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard icon={<CheckSquareOutlined />} label="Total GRTs" value={number(stats?.totalGrt ?? 0)} gradient={['#2563eb', '#1e3a8a']} />
          <KpiCard icon={<CheckCircleOutlined />} label="Passed" value={number(stats?.passed ?? 0)} gradient={['#059669', '#065f46']} />
          <KpiCard icon={<CloseCircleOutlined />} label="Failed" value={number(stats?.failed ?? 0)} gradient={['#dc2626', '#7f1d1d']} />
          <KpiCard icon={<FileTextOutlined />} label="Finance Verified" value={money(stats?.financeVerified ?? 0)} gradient={['#c8952c', '#8a5a0a']} />
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Input
              placeholder="Loan no. or client name..."
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
              value={dateRange ?? undefined}
              format="DD MMM YYYY"
              placeholder={['GRT From', 'GRT To']}
              allowClear
              onChange={(values) => {
                setPage(1);
                setDateRange(values && values[0] && values[1] ? [values[0], values[1]] : null);
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
              placeholder="Any Status"
              style={{ width: 140 }}
              allowClear
              value={status}
              onChange={(v) => {
                setPage(1);
                setStatus(v);
              }}
              options={lookups?.statuses.map((s) => ({ value: s.id, label: statusLabel(s.name) }))}
            />
            <Button icon={<DownloadOutlined />} loading={exporting} onClick={handleExport} style={{ marginLeft: 'auto' }}>
              Export CSV
            </Button>
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<GrtRow>
            rowKey="grtId"
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
              showTotal: (t) => `${t} GRT records`,
            }}
          />
        </div>
      </div>
    </AppShell>
  );
}
