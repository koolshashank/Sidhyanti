import { DownloadOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { downloadAuditLogsCsv, fetchAuditLogLookups, fetchAuditLogs, type AuditLogRow } from '../api/auditLogs';
import { apiErrorMessage } from '../api/client';
import { AppShell } from '../layouts/AppShell';

const { RangePicker } = DatePicker;

const ACTION_COLORS: Record<string, string> = {
  INSERT: 'success',
  UPDATE: 'warning',
  DELETE: 'error',
};

function DiffJson({ label, value }: { label: string; value: string | null }) {
  if (!value) return null;
  return (
    <details className="mt-1">
      <summary className="cursor-pointer text-xs text-slate-500">{label}</summary>
      <pre className="mt-1 max-w-md overflow-auto rounded bg-slate-50 p-2 text-[11px] text-slate-600">{value.slice(0, 1000)}</pre>
    </details>
  );
}

export default function AuditLogPage() {
  const [tables, setTables] = useState<string[]>([]);
  const [items, setItems] = useState<AuditLogRow[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(50);
  const [table, setTable] = useState<string | undefined>();
  const [action, setAction] = useState<'INSERT' | 'UPDATE' | 'DELETE' | undefined>();
  const [dateRange, setDateRange] = useState<[Dayjs, Dayjs] | null>(null);

  const dateFrom = dateRange ? dateRange[0].format('YYYY-MM-DD') : undefined;
  const dateTo = dateRange ? dateRange[1].format('YYYY-MM-DD') : undefined;

  useEffect(() => {
    fetchAuditLogLookups().then((res) => setTables(res.tables)).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchAuditLogs({ page, perPage, table, action, dateFrom, dateTo })
      .then((res) => {
        setItems(res.items);
        setTotal(res.total);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, table, action, dateFrom, dateTo]);

  async function handleExport() {
    setExporting(true);
    try {
      await downloadAuditLogsCsv({ table, action, dateFrom, dateTo });
    } finally {
      setExporting(false);
    }
  }

  const columns: ColumnsType<AuditLogRow> = [
    { title: 'When', dataIndex: 'changedAt', render: (v) => dayjs(v).format('DD MMM YYYY, h:mm:ss A'), width: 190 },
    { title: 'Table', dataIndex: 'tableName', render: (v) => <span className="font-semibold text-slate-700">{v}</span> },
    { title: 'Record ID', dataIndex: 'recordId', render: (v) => `#${v}` },
    { title: 'Action', dataIndex: 'actionType', render: (v: string) => <Tag color={ACTION_COLORS[v] ?? 'default'}>{v}</Tag> },
    { title: 'Changed By', dataIndex: 'staffName', render: (v) => v ?? 'System' },
    {
      title: 'Details',
      key: 'details',
      render: (_, row) => (
        <div>
          <DiffJson label="Before" value={row.oldValues} />
          <DiffJson label="After" value={row.newValues} />
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Audit Log">
      <div className="flex flex-col gap-4">
        <p className="text-sm text-slate-500">
          Every insert/update/delete on Member, Loan, Collection, Task, Interaction, Staff, Branch, Center, and Repayment_Schedule is captured here
          automatically by database triggers -- this is a read-only view of that trail.
        </p>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Select
              placeholder="All Tables"
              style={{ width: 200 }}
              allowClear
              value={table}
              onChange={(v) => {
                setPage(1);
                setTable(v);
              }}
              options={tables.map((t) => ({ value: t, label: t }))}
            />
            <Select
              placeholder="Any Action"
              style={{ width: 150 }}
              allowClear
              value={action}
              onChange={(v) => {
                setPage(1);
                setAction(v);
              }}
              options={[
                { value: 'INSERT', label: 'INSERT' },
                { value: 'UPDATE', label: 'UPDATE' },
                { value: 'DELETE', label: 'DELETE' },
              ]}
            />
            <RangePicker
              value={dateRange ?? undefined}
              format="DD MMM YYYY"
              allowClear
              onChange={(values) => {
                setPage(1);
                setDateRange(values && values[0] && values[1] ? [values[0], values[1]] : null);
              }}
            />
            <Button icon={<DownloadOutlined />} loading={exporting} onClick={handleExport} style={{ marginLeft: 'auto' }}>
              Export CSV
            </Button>
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<AuditLogRow>
            rowKey="auditId"
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
              showTotal: (t) => `${t} entries`,
            }}
          />
        </div>
      </div>
    </AppShell>
  );
}
