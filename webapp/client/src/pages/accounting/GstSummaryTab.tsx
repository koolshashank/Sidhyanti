import { Alert, DatePicker } from 'antd';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchGstSummary, type GstSummaryResponse } from '../../api/accountingReports';
import { KpiCard } from '../../components/KpiCard';
import { ArrowDownOutlined, ArrowUpOutlined, WalletOutlined } from '@ant-design/icons';
import { money } from '../../utils/format';

const { RangePicker } = DatePicker;

export default function GstSummaryTab() {
  const [range, setRange] = useState<[Dayjs, Dayjs]>([dayjs().startOf('month'), dayjs()]);
  const [data, setData] = useState<GstSummaryResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchGstSummary(range[0].format('YYYY-MM-DD'), range[1].format('YYYY-MM-DD'))
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
            <KpiCard icon={<ArrowDownOutlined />} label="Input GST Credit" value={money(data.inputCredit)} gradient={['#059669', '#065f46']} />
            <KpiCard icon={<ArrowUpOutlined />} label="Output GST Payable" value={money(data.outputPayable)} gradient={['#dc2626', '#7f1d1d']} />
            <KpiCard
              icon={<WalletOutlined />}
              label={data.netPayable >= 0 ? 'Net GST Payable' : 'Net GST Receivable'}
              value={money(Math.abs(data.netPayable))}
              gradient={['#2563eb', '#1e3a8a']}
            />
          </div>

          <div className="rounded-sm border border-[#2d4d7a] bg-[#0f2a52] p-5">
            <h3 className="tally-label mb-3">Tax Ledgers</h3>
            {data.items.length === 0 && <p className="text-sm text-[#9fb3cc]">No GST accounts flagged, or no activity for this period.</p>}
            {data.items.map((r) => (
              <div key={r.accountId} className="flex justify-between border-b border-[#1d3a66] py-2 text-sm last:border-0">
                <span>
                  {r.accountCode} - {r.accountName} ({r.accountType})
                </span>
                <span className="tally-amount font-semibold">{money(r.amount)}</span>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
