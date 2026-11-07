import { CheckCircleOutlined, ClockCircleOutlined, CloseCircleOutlined, DownloadOutlined, PhoneOutlined, SearchOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, Input, Select, Table, Tag, Tooltip } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import type { Dayjs } from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { downloadTvrCsv, fetchTvrList, fetchTvrLookups, type TvrLookups, type TvrRow, type TvrStats } from '../../api/tvr';
import { KpiCard } from '../../components/KpiCard';
import { fdate, money, number } from '../../utils/format';

const { RangePicker } = DatePicker;

const STATUS_COLORS: Record<string, string> = {
  Approved: 'success',
  Rejected: 'error',
  Pending: 'warning',
};

export default function TvrRecordsTab() {
  const [lookups, setLookups] = useState<TvrLookups | null>(null);
  const [items, setItems] = useState<TvrRow[]>([]);
  const [stats, setStats] = useState<TvrStats | null>(null);
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
    fetchTvrLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchTvrList({ page, perPage, q: q || undefined, dateFrom, dateTo, branch, center, status })
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
      await downloadTvrCsv({ q: q || undefined, dateFrom, dateTo, branch, center, status });
    } finally {
      setExporting(false);
    }
  }

  const columns: ColumnsType<TvrRow> = [
    { title: 'Loan No', dataIndex: 'loanNo', render: (v) => <span className="font-semibold text-slate-700">{v}</span> },
    {
      title: 'Client',
      key: 'client',
      render: (_, row) => (
        <div>
          <div className="font-medium text-slate-800">{row.clientName}</div>
          <div className="text-xs text-slate-400">{row.mobileNo ?? '-'}</div>
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
    { title: 'Contact Person', dataIndex: 'contactPersonName', render: (v) => v ?? '-' },
    { title: 'Loan Amount', dataIndex: 'loanAmount', align: 'right', render: (v: number | null) => money(v ?? 0) },
    { title: 'TVR Date', dataIndex: 'tvrDate', render: (v) => fdate(v) },
    { title: 'Conducted By', dataIndex: 'conductedBy', render: (v) => v ?? 'Unassigned' },
    {
      title: 'Status',
      dataIndex: 'statusName',
      render: (statusName: string) => <Tag color={STATUS_COLORS[statusName] ?? 'default'}>{statusName}</Tag>,
    },
    {
      title: 'Notes',
      dataIndex: 'verificationNotes',
      render: (v: string | null) =>
        v ? (
          <Tooltip title={v}>
            <span className="cursor-help text-slate-400 underline decoration-dotted">View</span>
          </Tooltip>
        ) : (
          <span className="text-slate-300">-</span>
        ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard icon={<PhoneOutlined />} label="Total TVRs" value={number(stats?.totalTvr ?? 0)} gradient={['#2563eb', '#1e3a8a']} />
        <KpiCard icon={<CheckCircleOutlined />} label="Approved" value={number(stats?.approved ?? 0)} gradient={['#059669', '#065f46']} />
        <KpiCard icon={<CloseCircleOutlined />} label="Rejected" value={number(stats?.rejected ?? 0)} gradient={['#dc2626', '#7f1d1d']} />
        <KpiCard icon={<ClockCircleOutlined />} label="Pending" value={number(stats?.pending ?? 0)} gradient={['#c8952c', '#8a5a0a']} />
      </div>

      <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <div className="mb-4 flex flex-wrap items-center gap-3">
          <Input
            placeholder="Loan no, client or contact person..."
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
            placeholder={['TVR From', 'TVR To']}
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
            options={lookups?.statuses.map((s) => ({ value: s.id, label: s.name }))}
          />
          <Button icon={<DownloadOutlined />} loading={exporting} onClick={handleExport} style={{ marginLeft: 'auto' }}>
            Export CSV
          </Button>
        </div>

        {error && <Alert type="error" message={error} showIcon className="mb-4" />}

        <Table<TvrRow>
          rowKey="tvrId"
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
            showTotal: (t) => `${t} TVR records`,
          }}
        />
      </div>
    </div>
  );
}
