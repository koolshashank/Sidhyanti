import { Alert, Button, Input, Spin, Tabs, message } from 'antd';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import { fetchLoanApprovals, reviewLoanApproval, type LoanApprovalRow, type LoanApprovalsResponse } from '../api/loanApprovals';
import { AppShell } from '../layouts/AppShell';
import { fdate, money } from '../utils/format';

function ApprovalCard({ row, onReview }: { row: LoanApprovalRow; onReview: (id: number, action: 'approve' | 'reject', remarks: string) => void }) {
  const navigate = useNavigate();
  const [remarks, setRemarks] = useState('');
  const amount = row.loanAmountSanctioned ?? row.loanAmountApplied ?? 0;

  return (
    <div className="mb-4 rounded-xl border border-slate-100 p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <button className="text-left font-bold text-slate-800 hover:text-blue-600" onClick={() => navigate(`/members`)}>
            {row.firstName} {row.lastName}
          </button>
          <div className="text-xs text-slate-400">
            Loan {row.loanNo} · Requested by {row.requestedByName ?? '-'} · {fdate(row.createdAt)}
          </div>
          {row.requestRemarks && <div className="mt-1 text-xs text-slate-600">{row.requestRemarks}</div>}
        </div>
        <div className="text-lg font-extrabold text-[#0b2545]">{money(amount)}</div>
      </div>

      {row.quote && (
        <div className="mt-3 flex flex-wrap gap-6 rounded-lg bg-slate-50 p-3">
          <div>
            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">Installments Paid</div>
            <div className="text-sm font-bold">
              {row.quote.installmentsPaid} / {row.quote.totalInstallments}
            </div>
          </div>
          <div>
            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">Principal Outstanding</div>
            <div className="text-sm font-bold">{money(row.quote.principalOutstanding)}</div>
          </div>
          <div>
            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">Amount to Pay Now</div>
            <div className="text-sm font-bold text-[#0b2545]">{money(row.quote.precloseAmount)}</div>
          </div>
          <div>
            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">Member Saves (Interest)</div>
            <div className="text-sm font-bold text-emerald-600">{money(row.quote.interestSavings)}</div>
          </div>
        </div>
      )}

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <Input placeholder="Remarks (optional)" value={remarks} onChange={(e) => setRemarks(e.target.value)} style={{ flex: 1, minWidth: 200 }} />
        <Button type="primary" onClick={() => onReview(row.loanApprovalId, 'approve', remarks)}>
          Approve
        </Button>
        <Button danger onClick={() => onReview(row.loanApprovalId, 'reject', remarks)}>
          Reject
        </Button>
      </div>
    </div>
  );
}

export default function LoanApprovalsPage() {
  const [data, setData] = useState<LoanApprovalsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function load() {
    setLoading(true);
    fetchLoanApprovals()
      .then(setData)
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  async function handleReview(id: number, action: 'approve' | 'reject', remarks: string) {
    try {
      await reviewLoanApproval(id, action, remarks || undefined);
      message.success('Request reviewed');
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  if (loading) {
    return (
      <AppShell title="Loan Approvals">
        <div className="flex justify-center py-20">
          <Spin size="large" />
        </div>
      </AppShell>
    );
  }

  if (error || !data) {
    return (
      <AppShell title="Loan Approvals">
        <Alert type="error" message={error ?? 'Failed to load'} showIcon />
      </AppShell>
    );
  }

  if (!data.canProposal && !data.canPreclose) {
    return (
      <AppShell title="Loan Approvals">
        <Alert type="info" message="You do not have permission to review loan approvals." showIcon />
      </AppShell>
    );
  }

  return (
    <AppShell title="Loan Approvals">
      <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <Tabs
          items={[
            data.canProposal
              ? {
                  key: 'proposal',
                  label: `Loan Proposals (${data.proposals.length})`,
                  children:
                    data.proposals.length === 0 ? (
                      <div className="py-10 text-center text-sm text-slate-400">No loan proposals awaiting your review.</div>
                    ) : (
                      data.proposals.map((p) => <ApprovalCard key={p.loanApprovalId} row={p} onReview={handleReview} />)
                    ),
                }
              : null,
            data.canPreclose
              ? {
                  key: 'preclose',
                  label: `Preclose Requests (${data.preclosures.length})`,
                  children:
                    data.preclosures.length === 0 ? (
                      <div className="py-10 text-center text-sm text-slate-400">No preclose requests awaiting your review.</div>
                    ) : (
                      data.preclosures.map((p) => <ApprovalCard key={p.loanApprovalId} row={p} onReview={handleReview} />)
                    ),
                }
              : null,
          ].filter((x): x is NonNullable<typeof x> => x !== null)}
        />
      </div>
    </AppShell>
  );
}
