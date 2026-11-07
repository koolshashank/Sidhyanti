import { Alert, DatePicker } from 'antd';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchBalanceSheet, type BalanceSheetResponse } from '../../api/accountingReports';
import { money } from '../../utils/format';

function Section({ title, rows, total, totalLabel }: { title: string; rows: { accountId: number; accountName: string; amount: number }[]; total: number; totalLabel: string }) {
  return (
    <div className="rounded-sm border border-[#2d4d7a] bg-[#0f2a52] p-5">
      <h3 className="tally-label mb-3">{title}</h3>
      {rows.length === 0 && <p className="text-sm text-[#9fb3cc]">Nothing posted yet.</p>}
      {rows.map((r) => (
        <div key={r.accountId} className="flex justify-between border-b border-[#1d3a66] py-2 text-sm last:border-0">
          <span>{r.accountName}</span>
          <span className="tally-amount font-semibold">{money(r.amount)}</span>
        </div>
      ))}
      <div className="mt-2 flex justify-between border-t border-[#f5c518] pt-2 text-sm font-bold text-[#f5c518]">
        <span>{totalLabel}</span>
        <span className="tally-amount">{money(total)}</span>
      </div>
    </div>
  );
}

export default function BalanceSheetTab() {
  const [asOfDate, setAsOfDate] = useState<Dayjs>(dayjs());
  const [data, setData] = useState<BalanceSheetResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchBalanceSheet(asOfDate.format('YYYY-MM-DD'))
      .then(setData)
      .catch((err) => setError(apiErrorMessage(err)));
  }, [asOfDate]);

  const tied = data && data.totalAssets === data.totalLiabilitiesAndEquity;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-3">
        <span className="tally-label">As of</span>
        <DatePicker value={asOfDate} format="DD MMM YYYY" allowClear={false} onChange={(v) => v && setAsOfDate(v)} />
      </div>

      {error && <Alert type="error" message={error} showIcon />}

      {data && (
        <>
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Section title="Assets" rows={data.assets} total={data.totalAssets} totalLabel="Total Assets" />
            <div className="flex flex-col gap-4">
              <Section title="Liabilities" rows={data.liabilities} total={data.totalLiabilities} totalLabel="Total Liabilities" />
              <Section title="Equity" rows={data.equity} total={data.totalEquity} totalLabel="Total Equity" />
            </div>
          </div>
          <Alert
            type={tied ? 'success' : 'error'}
            showIcon
            message={
              tied
                ? `Balance Sheet ties out -- Assets ${money(data.totalAssets)} = Liabilities + Equity ${money(data.totalLiabilitiesAndEquity)}`
                : `Does not tie out -- Assets ${money(data.totalAssets)} vs Liabilities + Equity ${money(data.totalLiabilitiesAndEquity)}`
            }
          />
        </>
      )}
    </div>
  );
}
