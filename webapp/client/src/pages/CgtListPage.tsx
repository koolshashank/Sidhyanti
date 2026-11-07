import { CheckCircleOutlined, FileTextOutlined, HourglassOutlined, HomeOutlined, SearchOutlined, DownloadOutlined } from '@ant-design/icons';
import { Alert, Button, Input, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../api/client';
import { downloadCgtCsv, fetchCgtList, fetchCgtLookups, type CgtLookups, type CgtRow, type CgtStats } from '../api/cgt';
import { KpiCard } from '../components/KpiCard';
import { AppShell } from '../layouts/AppShell';
import { fdate, number, weekdayName } from '../utils/format';

export default function CgtListPage() {
  const [lookups, setLookups] = useState<CgtLookups | null>(null);
  const [items, setItems] = useState<CgtRow[]>([]);
  const [stats, setStats] = useState<CgtStats | null>(null);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [q, setQ] = useState('');
  const [branch, setBranch] = useState<number | undefined>();
  const [status, setStatus] = useState<'done' | 'pending' | undefined>();

  useEffect(() => {
    fetchCgtLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchCgtList({ page, perPage, q: q || undefined, branch, status })
      .then((res) => {
        setItems(res.items);
        setTotal(res.total);
        setStats(res.stats);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, q, branch, status]);

  async function handleExport() {
    setExporting(true);
    try {
      await downloadCgtCsv({ q: q || undefined, branch, status });
    } finally {
      setExporting(false);
    }
  }

  const columns: ColumnsType<CgtRow> = [
    { title: 'Branch', dataIndex: 'branchName' },
    {
      title: 'Center',
      key: 'center',
      render: (_, row) => (
        <div>
          <div className="font-semibold text-slate-800">{row.centerName}</div>
          <div className="text-xs text-slate-400">
            {row.centerCode}
            {row.centerPlace ? ` · ${row.centerPlace}` : ''}
          </div>
        </div>
      ),
    },
    {
      title: 'Contact',
      key: 'contact',
      render: (_, row) => (
        <div className="text-sm text-slate-600">
          <div>{row.contactPerson || '-'}</div>
          <div className="text-xs text-slate-400">{row.contactNo || '-'}</div>
        </div>
      ),
    },
    {
      title: 'Meeting',
      key: 'meeting',
      render: (_, row) => (
        <div className="text-sm text-slate-600">
          <div>{weekdayName(row.meetingDay)}</div>
          {row.meetingTime && <div className="text-xs text-slate-400">{row.meetingTime}</div>}
        </div>
      ),
    },
    { title: 'CGT Date', dataIndex: 'cgtDate', render: (v) => fdate(v) },
    { title: 'Conducted By', dataIndex: 'conductedBy', render: (v) => v ?? 'Unassigned' },
    {
      title: 'Status',
      dataIndex: 'cgtStatus',
      render: (statusValue: 'Done' | 'Pending') =>
        statusValue === 'Done' ? <Tag color="success">Done</Tag> : <Tag color="warning">Pending</Tag>,
    },
    {
      title: 'Document',
      key: 'document',
      render: (_, row) =>
        row.cgtImage ? (
          <a href={row.cgtImage} target="_blank" rel="noopener noreferrer">
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
    <AppShell title="CGT (Center Group Training)">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <KpiCard icon={<HomeOutlined />} label="Total Active Centers" value={number(stats?.totalCenters ?? 0)} gradient={['#2563eb', '#1e3a8a']} />
          <KpiCard icon={<CheckCircleOutlined />} label="CGT Done" value={number(stats?.done ?? 0)} gradient={['#059669', '#065f46']} />
          <KpiCard icon={<HourglassOutlined />} label="CGT Pending" value={number(stats?.pending ?? 0)} gradient={['#c8952c', '#8a5a0a']} />
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Input
              placeholder="Center code, name or branch..."
              prefix={<SearchOutlined className="text-slate-400" />}
              style={{ width: 260 }}
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
            <Select
              placeholder="Any Status"
              style={{ width: 140 }}
              allowClear
              value={status}
              onChange={(v) => {
                setPage(1);
                setStatus(v);
              }}
              options={[
                { value: 'done', label: 'Done' },
                { value: 'pending', label: 'Pending' },
              ]}
            />
            <Button icon={<DownloadOutlined />} loading={exporting} onClick={handleExport} style={{ marginLeft: 'auto' }}>
              Export CSV
            </Button>
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<CgtRow>
            rowKey="centerId"
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
              showTotal: (t) => `${t} centers`,
            }}
          />
        </div>
      </div>
    </AppShell>
  );
}
