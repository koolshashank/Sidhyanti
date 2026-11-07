import { CheckCircleOutlined, ClockCircleOutlined, CloseCircleOutlined, FileTextOutlined, SearchOutlined, UnorderedListOutlined } from '@ant-design/icons';
import { Alert, Button, Input, Modal, Segmented, Table, Tag, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../api/client';
import {
  approveDeathEntry,
  fetchDeathEntries,
  rejectDeathEntry,
  type DeathEntryRow,
  type DeathEntryStatus,
} from '../api/deathEntries';
import { KpiCard } from '../components/KpiCard';
import { AppShell } from '../layouts/AppShell';
import { fdate, number } from '../utils/format';

const STATUS_COLORS: Record<DeathEntryStatus, string> = {
  Pending: 'warning',
  Approved: 'success',
  Rejected: 'error',
};

export default function DeathEntriesPage() {
  const [items, setItems] = useState<DeathEntryRow[]>([]);
  const [counts, setCounts] = useState({ Pending: 0, Approved: 0, Rejected: 0, All: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [status, setStatus] = useState<DeathEntryStatus | 'All'>('Pending');
  const [q, setQ] = useState('');

  function load() {
    setLoading(true);
    setError(null);
    fetchDeathEntries({ status, q: q || undefined })
      .then((res) => {
        setItems(res.items);
        setCounts(res.statusCounts);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, [status, q]);

  function handleReview(row: DeathEntryRow, action: 'approve' | 'reject') {
    let remarks = '';
    Modal.confirm({
      title: `${action === 'approve' ? 'Approve' : 'Reject'} death entry?`,
      content: (
        <div>
          <p className="mb-2 text-sm text-slate-500">
            {row.firstName} {row.lastName} ({row.enrollmentNo})
          </p>
          <Input.TextArea rows={2} placeholder="Remarks (optional)" onChange={(e) => (remarks = e.target.value)} />
        </div>
      ),
      okText: action === 'approve' ? 'Approve' : 'Reject',
      okButtonProps: { danger: action === 'reject' },
      onOk: async () => {
        try {
          if (action === 'approve') await approveDeathEntry(row.deathEntryId, remarks || undefined);
          else await rejectDeathEntry(row.deathEntryId, remarks || undefined);
          message.success(`Death entry ${action === 'approve' ? 'approved' : 'rejected'}`);
          load();
        } catch (err) {
          message.error(apiErrorMessage(err));
        }
      },
    });
  }

  const columns: ColumnsType<DeathEntryRow> = [
    {
      title: 'Member',
      key: 'member',
      render: (_, row) => (
        <div>
          <div className="font-semibold text-slate-800">
            {row.firstName} {row.lastName}
          </div>
          <div className="text-xs text-slate-400">
            {row.enrollmentNo} · {row.mobileNo ?? '-'}
          </div>
        </div>
      ),
    },
    {
      title: 'Center / Branch',
      key: 'location',
      render: (_, row) => (
        <div className="text-sm">
          <div className="text-slate-700">{row.centerName ?? '-'}</div>
          <div className="text-xs text-slate-400">{row.branchName ?? '-'}</div>
        </div>
      ),
    },
    {
      title: 'Date of Death',
      key: 'death',
      render: (_, row) => (
        <div>
          <div>{fdate(row.dateOfDeath)}</div>
          {row.cause && <div className="text-xs text-slate-400">{row.cause}</div>}
        </div>
      ),
    },
    {
      title: 'Informant',
      key: 'informant',
      render: (_, row) => (
        <div>
          <div>{row.informantName ?? '-'}</div>
          {row.informantRelation && <div className="text-xs text-slate-400">{row.informantRelation}</div>}
        </div>
      ),
    },
    { title: 'Reported By', dataIndex: 'reportedByName', render: (v) => v ?? '-' },
    {
      title: 'Proof',
      key: 'proof',
      render: (_, row) =>
        row.proofDocumentPath ? (
          <a href={row.proofDocumentPath} target="_blank" rel="noopener noreferrer">
            <Button icon={<FileTextOutlined />} size="small">
              View
            </Button>
          </a>
        ) : (
          <span className="text-slate-400">None</span>
        ),
    },
    {
      title: 'Status',
      key: 'status',
      render: (_, row) => (
        <div>
          <Tag color={STATUS_COLORS[row.status]}>{row.status}</Tag>
          {row.status !== 'Pending' && <div className="mt-1 text-xs text-slate-400">by {row.reviewedByName ?? '-'}</div>}
        </div>
      ),
    },
    {
      title: '',
      key: 'actions',
      render: (_, row) =>
        row.status === 'Pending' ? (
          <div className="flex gap-2">
            <Button size="small" style={{ color: '#1e9d6b', borderColor: '#1e9d6b' }} onClick={() => handleReview(row, 'approve')}>
              Approve
            </Button>
            <Button size="small" danger onClick={() => handleReview(row, 'reject')}>
              Reject
            </Button>
          </div>
        ) : null,
    },
  ];

  return (
    <AppShell title="Death Entries">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard icon={<ClockCircleOutlined />} label="Pending" value={number(counts.Pending)} gradient={['#c8952c', '#8a5a0a']} />
          <KpiCard icon={<CheckCircleOutlined />} label="Approved" value={number(counts.Approved)} gradient={['#059669', '#065f46']} />
          <KpiCard icon={<CloseCircleOutlined />} label="Rejected" value={number(counts.Rejected)} gradient={['#dc2626', '#7f1d1d']} />
          <KpiCard icon={<UnorderedListOutlined />} label="All" value={number(counts.All)} gradient={['#2563eb', '#1e3a8a']} />
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Segmented
              value={status}
              onChange={(v) => setStatus(v as DeathEntryStatus | 'All')}
              options={['Pending', 'Approved', 'Rejected', 'All']}
            />
            <Input
              placeholder="Member name or enrollment no..."
              prefix={<SearchOutlined className="text-slate-400" />}
              style={{ width: 240, marginLeft: 'auto' }}
              value={q}
              onChange={(e) => setQ(e.target.value)}
              allowClear
            />
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<DeathEntryRow>
            rowKey="deathEntryId"
            columns={columns}
            dataSource={items}
            loading={loading}
            pagination={{ pageSize: 20, showTotal: (t) => `${t} death entries` }}
          />
        </div>
      </div>
    </AppShell>
  );
}
