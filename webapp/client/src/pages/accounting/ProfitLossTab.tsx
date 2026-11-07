import { Alert, DatePicker } from 'antd';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchProfitLoss, type ProfitLossResponse } from '../../api/accountingReports';
import { KpiCard } from '../../components/KpiCard';
import { ArrowDownOutlined, ArrowUpOutlined, WalletOutlined } from '@ant-design/icons';
import { money } from '../../utils/format';

const { RangePicker } = DatePicker;

export default function ProfitLossTab() {
  const [range, setRange] = useState<[Dayjs, Dayjs]>([dayjs().startOf('year'), dayjs()]);
  const [data, setData] = useState<ProfitLossResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchProfitLoss(range[0].format('YYYY-MM-DD'), range[1].format('YYYY-MM-DD'))
      .then(setData)
      .catch((err) => setError(apiErrorMessage(err)));
  }, [range]);

  return (
    <div className="flex flex-col gap-4">
      <RangePicker value={range} format="DD MMM YYYY" allowClear={false} onChange={(values) => values && values[0] && values[1] && setRange([values[0], values[1]])} />

      {error && <Alert type="error" message={error} showIcon />}

      {data && (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <KpiCard icon={<ArrowUpOutlined />} label="Total Income" value={money(data.totalIncome)} gradient={['#059669', '#065f46']} />
            <KpiCard icon={<ArrowDownOutlined />} label="Total Expense" value={money(data.totalExpense)} gradient={['#dc2626', '#7f1d1d']} />
            <KpiCard
              icon={<WalletOutlined />}
              label={data.netProfit >= 0 ? 'Net Profit' : 'Net Loss'}
              value={money(Math.abs(data.netProfit))}
              gradient={data.netProfit >= 0 ? ['#2563eb', '#1e3a8a'] : ['#dc2626', '#7f1d1d']}
            />
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div className="rounded-sm border border-[#2d4d7a] bg-[#0f2a52] p-5">
              <h3 className="mb-3 text-sm font-bold uppercase tracking-wide text-emerald-400">Income</h3>
              {data.income.length === 0 && <p className="text-sm text-[#9fb3cc]">No income recorded for this period.</p>}
              {data.income.map((r) => (
                <div key={r.accountId} className="flex justify-between border-b border-[#1d3a66] py-2 text-sm last:border-0">
                  <span>{r.accountName}</span>
                  <span className="tally-amount font-semibold">{money(r.amount)}</span>
                </div>
              ))}
              <div className="mt-2 flex justify-between border-t border-[#f5c518] pt-2 text-sm font-bold text-[#f5c518]">
                <span>Total Income</span>
                <span className="tally-amount">{money(data.totalIncome)}</span>
              </div>
            </div>
            <div className="rounded-sm border border-[#2d4d7a] bg-[#0f2a52] p-5">
              <h3 className="mb-3 text-sm font-bold uppercase tracking-wide text-red-400">Expense</h3>
              {data.expense.length === 0 && <p className="text-sm text-[#9fb3cc]">No expense recorded for this period.</p>}
              {data.expense.map((r) => (
                <div key={r.accountId} className="flex justify-between border-b border-[#1d3a66] py-2 text-sm last:border-0">
                  <span>{r.accountName}</span>
                  <span className="tally-amount font-semibold">{money(r.amount)}</span>
                </div>
              ))}
              <div className="mt-2 flex justify-between border-t border-[#f5c518] pt-2 text-sm font-bold text-[#f5c518]">
                <span>Total Expense</span>
                <span className="tally-amount">{money(data.totalExpense)}</span>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
