import { Alert, Button, Input, Modal, Table, Tag, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../api/client';
import { fetchLoanWriteoffs, reviewLoanWriteoff, type WriteoffHistoryRow, type WriteoffPendingRow } from '../api/loanWriteoffs';
import { AppShell } from '../layouts/AppShell';
import { fdate, money } from '../utils/format';

export default function LoanWriteoffsPage() {
  const [pending, setPending] = useState<WriteoffPendingRow[]>([]);
  const [history, setHistory] = useState<WriteoffHistoryRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function load() {
    setLoading(true);
    fetchLoanWriteoffs()
      .then((res) => {
        setPending(res.pending);
        setHistory(res.history);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  function handleReview(row: WriteoffPendingRow, action: 'approve' | 'reject') {
    let remarks = '';
    Modal.confirm({
      title: 'Confirm this decision?',
      content: (
        <div>
          <p className="mb-2 text-sm text-slate-500">
            Loan {row.loanNo} -- {row.firstName} {row.lastName} -- {money(row.outstandingAmount)} outstanding
          </p>
          <Input.TextArea rows={2} placeholder="Remarks (optional)" onChange={(e) => (remarks = e.target.value)} />
        </div>
      ),
      okText: action === 'approve' ? 'Approve' : 'Reject',
      okButtonProps: { danger: action === 'reject' },
      onOk: async () => {
        try {
          await reviewLoanWriteoff(row.requestId, action, remarks || undefined);
          message.success('Decision recorded');
          load();
        } catch (err) {
          message.error(apiErrorMessage(err));
        }
      },
    });
  }

  const pendingColumns: ColumnsType<WriteoffPendingRow> = [
    { title: 'Loan', dataIndex: 'loanNo', render: (v) => <span className="font-semibold text-slate-700">{v}</span> },
    { title: 'Client', key: 'client', render: (_, r) => `${r.firstName} ${r.lastName}` },
    {
      title: 'Branch / Center',
      key: 'location',
      render: (_, r) => (
        <div className="text-sm">
          <div>{r.branchName ?? '-'}</div>
          <div className="text-xs text-slate-400">{r.centerName ?? '-'}</div>
        </div>
      ),
    },
    { title: 'Outstanding', dataIndex: 'outstandingAmount', align: 'right', render: (v: number) => money(v) },
    { title: 'Requested By', dataIndex: 'requestedByName', render: (v) => v ?? '-' },
    { title: 'Reason', dataIndex: 'requestReason', render: (v) => v ?? '-' },
    {
      title: 'Decision',
      key: 'decision',
      render: (_, row) => (
        <div className="flex gap-2">
          <Button size="small" onClick={() => handleReview(row, 'approve')} style={{ color: '#1e9d6b', borderColor: '#1e9d6b' }}>
            Approve
          </Button>
          <Button size="small" danger onClick={() => handleReview(row, 'reject')}>
            Reject
          </Button>
        </div>
      ),
    },
  ];

  const historyColumns: ColumnsType<WriteoffHistoryRow> = [
    { title: 'Loan', dataIndex: 'loanNo', render: (v) => <span className="font-semibold text-slate-700">{v}</span> },
    { title: 'Client', key: 'client', render: (_, r) => `${r.firstName} ${r.lastName}` },
    { title: 'Outstanding', dataIndex: 'outstandingAmount', align: 'right', render: (v: number) => money(v) },
    { title: 'Status', dataIndex: 'status', render: (v: 'Approved' | 'Rejected') => <Tag color={v === 'Approved' ? 'success' : 'error'}>{v}</Tag> },
    { title: 'Reviewed By', dataIndex: 'reviewedByName', render: (v) => v ?? '-' },
    { title: 'Reviewed At', dataIndex: 'reviewedAt', render: (v) => fdate(v) },
    { title: 'Remarks', dataIndex: 'reviewRemarks', render: (v) => v ?? '-' },
  ];

  return (
    <AppShell title="Loan Write-offs / NPA">
      <div className="flex flex-col gap-6">
        <p className="text-sm text-slate-500">NPA loans (90+ days overdue) requested for write-off by field/branch staff.</p>

        {error && <Alert type="error" message={error} showIcon />}

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <h3 className="mb-4 text-base font-semibold text-slate-800">Pending Write-off Requests</h3>
          <Table<WriteoffPendingRow> rowKey="requestId" columns={pendingColumns} dataSource={pending} loading={loading} pagination={false} />
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <h3 className="mb-4 text-base font-semibold text-slate-800">Decision History</h3>
          <Table<WriteoffHistoryRow> rowKey="requestId" columns={historyColumns} dataSource={history} loading={loading} pagination={false} />
        </div>
      </div>
    </AppShell>
  );
}
