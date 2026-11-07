import { Alert, DatePicker, Tag } from 'antd';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchDayBook, type DayBookEntry } from '../../api/accountingReports';
import { fdate, money } from '../../utils/format';

const { RangePicker } = DatePicker;

export default function DayBookTab() {
  const [range, setRange] = useState<[Dayjs, Dayjs]>([dayjs(), dayjs()]);
  const [items, setItems] = useState<DayBookEntry[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchDayBook(range[0].format('YYYY-MM-DD'), range[1].format('YYYY-MM-DD'))
      .then((res) => setItems(res.items))
      .catch((err) => setError(apiErrorMessage(err)));
  }, [range]);

  return (
    <div className="flex flex-col gap-4">
      <RangePicker value={range} format="DD MMM YYYY" allowClear={false} onChange={(values) => values && values[0] && values[1] && setRange([values[0], values[1]])} />

      {error && <Alert type="error" message={error} showIcon />}

      {items.length === 0 && <div className="py-10 text-center text-sm text-[#9fb3cc]">No vouchers posted in this range.</div>}

      <div className="flex flex-col gap-3">
        {items.map((entry) => (
          <div key={entry.journalEntryId} className="rounded-sm border border-[#2d4d7a] bg-[#0f2a52] p-4">
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <Tag color="gold">{entry.voucherType}</Tag>
                <span className="font-mono font-semibold">{entry.entryNo}</span>
                <span className="text-xs text-[#9fb3cc]">{fdate(entry.entryDate)}</span>
                {entry.isReversal && <Tag color="purple">Reversal</Tag>}
                {entry.isReversed && <Tag color="default">Reversed</Tag>}
              </div>
              <span className="tally-amount font-bold">{money(entry.totalAmount)}</span>
            </div>
            {entry.narration && <p className="mb-2 text-xs text-[#9fb3cc]">{entry.narration}</p>}
            {entry.lines.map((l, i) => (
              <div key={i} className="flex justify-between border-t border-[#1d3a66] py-1 text-sm">
                <span>
                  {l.accountCode} - {l.accountName}
                </span>
                <span className="tally-amount">{l.debitAmount > 0 ? `Dr ${money(l.debitAmount)}` : `Cr ${money(l.creditAmount)}`}</span>
              </div>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}
