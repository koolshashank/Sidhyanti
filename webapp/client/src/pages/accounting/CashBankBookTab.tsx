import { Alert, Checkbox, DatePicker, Table, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchCashBankBook, type CashBankBook, type CashBankBookEntry } from '../../api/accountingReports';
import { reconcileLine } from '../../api/journalEntries';
import { fdate, money } from '../../utils/format';

const { RangePicker } = DatePicker;

export default function CashBankBookTab() {
  const [range, setRange] = useState<[Dayjs, Dayjs] | null>([dayjs().startOf('month'), dayjs()]);
  const [books, setBooks] = useState<CashBankBook[]>([]);
  const [error, setError] = useState<string | null>(null);

  function load() {
    fetchCashBankBook({
      dateFrom: range ? range[0].format('YYYY-MM-DD') : undefined,
      dateTo: range ? range[1].format('YYYY-MM-DD') : undefined,
    })
      .then((res) => setBooks(res.books))
      .catch((err) => setError(apiErrorMessage(err)));
  }

  useEffect(load, [range]);

  async function handleToggle(line: CashBankBookEntry) {
    try {
      await reconcileLine(line.lineId, !line.isReconciled);
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  function columnsFor(): ColumnsType<CashBankBookEntry> {
    return [
      { title: 'Date', dataIndex: 'entryDate', render: (v) => fdate(v) },
      { title: 'Entry No', dataIndex: 'entryNo' },
      { title: 'Narration', dataIndex: 'narration', render: (v) => v ?? '-' },
      { title: 'Debit', dataIndex: 'debitAmount', align: 'right', render: (v: number) => (v > 0 ? money(v) : '-') },
      { title: 'Credit', dataIndex: 'creditAmount', align: 'right', render: (v: number) => (v > 0 ? money(v) : '-') },
      { title: 'Balance', dataIndex: 'runningBalance', align: 'right', render: (v: number) => <strong>{money(v)}</strong> },
      {
        title: 'Reconciled',
        key: 'reconciled',
        render: (_, l) => <Checkbox checked={l.isReconciled} onChange={() => handleToggle(l)} />,
      },
    ];
  }

  return (
    <div className="flex flex-col gap-6">
      <RangePicker
        value={range ?? undefined}
        format="DD MMM YYYY"
        onChange={(values) => setRange(values && values[0] && values[1] ? [values[0], values[1]] : null)}
      />

      {error && <Alert type="error" message={error} showIcon />}

      {books.length === 0 && (
        <div className="py-10 text-center text-sm text-[#9fb3cc]">
          No accounts flagged as Cash/Bank yet -- tick "Treat as a Cash/Bank account" on an account in Chart of Accounts.
        </div>
      )}

      {books.map((book) => (
        <div key={book.account.accountId}>
          <h3 className="tally-label mb-2">
            {book.account.accountCode} - {book.account.accountName} &middot; Opening {money(book.openingBalance)} &middot; Closing {money(book.closingBalance)}
          </h3>
          <Table<CashBankBookEntry> rowKey="lineId" columns={columnsFor()} dataSource={book.items} pagination={false} />
        </div>
      ))}
    </div>
  );
}
