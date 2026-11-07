import { Alert, DatePicker, Select, Spin, Table } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { fetchLedger, type LedgerLine, type LedgerResponse } from '../../api/accountingReports';
import { fetchJournalEntryLookups, type AccountOption } from '../../api/journalEntries';
import { apiErrorMessage } from '../../api/client';
import { KpiCard } from '../../components/KpiCard';
import { WalletOutlined } from '@ant-design/icons';
import { fdate, money } from '../../utils/format';

const { RangePicker } = DatePicker;

export default function LedgerTab() {
  const [accounts, setAccounts] = useState<AccountOption[]>([]);
  const [accountId, setAccountId] = useState<number | undefined>();
  const [dateRange, setDateRange] = useState<[Dayjs, Dayjs] | null>([dayjs().startOf('month'), dayjs()]);
  const [ledger, setLedger] = useState<LedgerResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchJournalEntryLookups().then((res) => setAccounts(res.accounts)).catch(() => {});
  }, []);

  useEffect(() => {
    if (!accountId) {
      setLedger(null);
      return;
    }
    setLoading(true);
    setError(null);
    fetchLedger(accountId, {
      dateFrom: dateRange ? dateRange[0].format('YYYY-MM-DD') : undefined,
      dateTo: dateRange ? dateRange[1].format('YYYY-MM-DD') : undefined,
    })
      .then(setLedger)
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }, [accountId, dateRange]);

  const columns: ColumnsType<LedgerLine> = [
    { title: 'Date', dataIndex: 'entryDate', render: (v) => fdate(v) },
    { title: 'Entry No', dataIndex: 'entryNo' },
    { title: 'Narration', key: 'narration', render: (_, r) => r.lineNarration ?? r.narration ?? '-' },
    { title: 'Debit', dataIndex: 'debitAmount', align: 'right', render: (v: number) => (v > 0 ? money(v) : '-') },
    { title: 'Credit', dataIndex: 'creditAmount', align: 'right', render: (v: number) => (v > 0 ? money(v) : '-') },
    { title: 'Balance', dataIndex: 'runningBalance', align: 'right', render: (v: number) => <strong>{money(v)}</strong> },
  ];

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-3">
        <Select
          showSearch
          placeholder="Select an account"
          style={{ width: 300 }}
          optionFilterProp="label"
          value={accountId}
          onChange={setAccountId}
          options={accounts.map((a) => ({ value: a.id, label: `${a.code} - ${a.name}` }))}
        />
        <RangePicker
          value={dateRange ?? undefined}
          format="DD MMM YYYY"
          onChange={(values) => setDateRange(values && values[0] && values[1] ? [values[0], values[1]] : null)}
        />
      </div>

      {error && <Alert type="error" message={error} showIcon />}
      {loading && <Spin />}

      {ledger && (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <KpiCard icon={<WalletOutlined />} label="Opening Balance" value={money(ledger.openingBalance)} gradient={['#2563eb', '#1e3a8a']} />
            <KpiCard icon={<WalletOutlined />} label="Closing Balance" value={money(ledger.closingBalance)} gradient={['#059669', '#065f46']} />
          </div>
          <Table<LedgerLine> rowKey={(_, index) => index ?? 0} columns={columns} dataSource={ledger.items} pagination={false} />
        </>
      )}

      {!accountId && <div className="py-10 text-center text-sm text-[#9fb3cc]">Select an account to view its ledger.</div>}
    </div>
  );
}
