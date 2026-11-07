import { CalendarOutlined, CheckCircleOutlined, CloseCircleOutlined, DownloadOutlined, EnvironmentOutlined, SearchOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, Input, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import {
  downloadAttendanceCsv,
  fetchAttendanceList,
  fetchAttendanceLookups,
  type AttendanceLookups,
  type AttendanceRow,
  type AttendanceStats,
} from '../api/attendance';
import { apiErrorMessage } from '../api/client';
import { KpiCard } from '../components/KpiCard';
import { AppShell } from '../layouts/AppShell';
import { fdate, number } from '../utils/format';

const { RangePicker } = DatePicker;

export default function AttendanceListPage() {
  const [lookups, setLookups] = useState<AttendanceLookups | null>(null);
  const [items, setItems] = useState<AttendanceRow[]>([]);
  const [stats, setStats] = useState<AttendanceStats | null>(null);
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
  const [status, setStatus] = useState<'Present' | 'Absent' | undefined>();

  const dateFrom = dateRange[0].format('YYYY-MM-DD');
  const dateTo = dateRange[1].format('YYYY-MM-DD');

  useEffect(() => {
    fetchAttendanceLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchAttendanceList({ page, perPage, q: q || undefined, dateFrom, dateTo, branch, center, status })
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
      await downloadAttendanceCsv({ q: q || undefined, dateFrom, dateTo, branch, center, status });
    } finally {
      setExporting(false);
    }
  }

  const columns: ColumnsType<AttendanceRow> = [
    { title: 'Date', dataIndex: 'attendanceDate', render: (v) => fdate(v), width: 120 },
    {
      title: 'Center',
      key: 'center',
      render: (_, row) => (
        <div>
          <div className="font-semibold text-slate-800">{row.centerName ?? '-'}</div>
          <div className="text-xs text-slate-400">{row.branchName ?? '-'}</div>
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
    {
      title: 'Status',
      dataIndex: 'status',
      render: (statusValue: 'Present' | 'Absent') =>
        statusValue === 'Present' ? (
          <Tag icon={<CheckCircleOutlined />} color="success">
            Present
          </Tag>
        ) : (
          <Tag icon={<CloseCircleOutlined />} color="error">
            Absent
          </Tag>
        ),
    },
    { title: 'Marked By', dataIndex: 'markedByName', render: (v) => v ?? '-' },
    {
      title: 'Marked At',
      dataIndex: 'markedAt',
      render: (v) => (v ? dayjs(v).format('DD MMM, h:mm A') : '-'),
    },
  ];

  return (
    <AppShell title="Meeting Attendance">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard icon={<CalendarOutlined />} label="Total Marked" value={number(stats?.totalMarked ?? 0)} gradient={['#2563eb', '#1e3a8a']} />
          <KpiCard icon={<CheckCircleOutlined />} label="Present" value={number(stats?.present ?? 0)} gradient={['#059669', '#065f46']} />
          <KpiCard icon={<CloseCircleOutlined />} label="Absent" value={number(stats?.absent ?? 0)} gradient={['#dc2626', '#7f1d1d']} />
          <KpiCard icon={<EnvironmentOutlined />} label="Centers Covered" value={number(stats?.centersCovered ?? 0)} gradient={['#c8952c', '#8a5a0a']} />
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
                { value: 'Present', label: 'Present' },
                { value: 'Absent', label: 'Absent' },
              ]}
            />
            <Button icon={<DownloadOutlined />} loading={exporting} onClick={handleExport} style={{ marginLeft: 'auto' }}>
              Export CSV
            </Button>
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<AttendanceRow>
            rowKey="attendanceId"
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
              showTotal: (t) => `${t} records`,
            }}
          />
        </div>
      </div>
    </AppShell>
  );
}
