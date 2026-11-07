import { PhoneOutlined, SearchOutlined } from '@ant-design/icons';
import { Alert, Button, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { Input } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchTvrLookups, fetchTvrPending, type TvrLookups, type TvrPendingRow } from '../../api/tvr';
import { RecordTvrModal } from '../../components/RecordTvrModal';
import { fdate, money } from '../../utils/format';

export default function TvrPendingTab() {
  const [lookups, setLookups] = useState<TvrLookups | null>(null);
  const [items, setItems] = useState<TvrPendingRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [q, setQ] = useState('');
  const [branch, setBranch] = useState<number | undefined>();
  const [center, setCenter] = useState<number | undefined>();
  const [recordingLoan, setRecordingLoan] = useState<TvrPendingRow | null>(null);

  useEffect(() => {
    fetchTvrLookups().then(setLookups).catch(() => {});
  }, []);

  function load() {
    setLoading(true);
    setError(null);
    fetchTvrPending({ q: q || undefined, branch, center })
      .then((res) => setItems(res.items))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, [q, branch, center]);

  const centerOptions = useMemo(
    () => (lookups?.centers ?? []).filter((c) => !branch || c.branchId === branch).map((c) => ({ value: c.id, label: c.name })),
    [lookups, branch],
  );

  const columns: ColumnsType<TvrPendingRow> = [
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
    { title: 'Loan Amount', dataIndex: 'loanAmount', align: 'right', render: (v: number | null) => money(v ?? 0) },
    { title: 'Applied On', dataIndex: 'applicationDate', render: (v) => fdate(v) },
    { title: 'Stage', dataIndex: 'stageName', render: (v) => <Tag color="blue">{v ?? '-'}</Tag> },
    {
      title: '',
      key: 'actions',
      render: (_, row) => (
        <Button type="primary" icon={<PhoneOutlined />} size="small" onClick={() => setRecordingLoan(row)}>
          Conduct TVR
        </Button>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-slate-500">
        Loans in Applied / GRT Pending / Approved stage that don't have a TVR recorded yet -- same worklist the mobile app shows the field team.
      </p>

      <div className="flex flex-wrap items-center gap-3">
        <Input
          placeholder="Loan no. or client name..."
          prefix={<SearchOutlined className="text-slate-400" />}
          style={{ width: 240 }}
          value={q}
          onChange={(e) => setQ(e.target.value)}
          allowClear
        />
        <Select
          placeholder="All Branches"
          style={{ width: 170 }}
          allowClear
          value={branch}
          onChange={(v) => {
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
          onChange={setCenter}
          options={centerOptions}
        />
      </div>

      {error && <Alert type="error" message={error} showIcon />}

      <Table<TvrPendingRow>
        rowKey="loanId"
        columns={columns}
        dataSource={items}
        loading={loading}
        pagination={{ pageSize: 20, showTotal: (t) => `${t} loans pending TVR` }}
      />

      {recordingLoan && lookups && (
        <RecordTvrModal
          loan={recordingLoan}
          statuses={lookups.statuses}
          onClose={() => setRecordingLoan(null)}
          onDone={() => {
            setRecordingLoan(null);
            load();
          }}
        />
      )}
    </div>
  );
}
