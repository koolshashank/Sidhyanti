import { Alert, DatePicker, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs, { type Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchTrialBalance, type TrialBalanceResponse, type TrialBalanceRow } from '../../api/accountingReports';
import type { AccountType } from '../../api/chartOfAccounts';
import { money } from '../../utils/format';

const TYPE_COLORS: Record<AccountType, string> = {
  Asset: 'blue',
  Liability: 'red',
  Equity: 'purple',
  Income: 'green',
  Expense: 'orange',
};

export default function TrialBalanceTab() {
  const [asOfDate, setAsOfDate] = useState<Dayjs>(dayjs());
  const [data, setData] = useState<TrialBalanceResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    fetchTrialBalance(asOfDate.format('YYYY-MM-DD'))
      .then(setData)
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }, [asOfDate]);

  const columns: ColumnsType<TrialBalanceRow> = [
    { title: 'Code', dataIndex: 'accountCode' },
    { title: 'Account', dataIndex: 'accountName', render: (v) => <span className="font-semibold text-[#e8edf5]">{v}</span> },
    { title: 'Type', dataIndex: 'accountType', render: (v: AccountType) => <Tag color={TYPE_COLORS[v]}>{v}</Tag> },
    { title: 'Debit', dataIndex: 'debit', align: 'right', render: (v: number) => (v > 0 ? money(v) : '-') },
    { title: 'Credit', dataIndex: 'credit', align: 'right', render: (v: number) => (v > 0 ? money(v) : '-') },
  ];

  const balanced = data && data.totalDebit === data.totalCredit;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-3">
        <span className="text-sm text-[#9fb3cc]">As of</span>
        <DatePicker value={asOfDate} format="DD MMM YYYY" allowClear={false} onChange={(v) => v && setAsOfDate(v)} />
      </div>

      {error && <Alert type="error" message={error} showIcon />}

      {data && (
        <>
          <Table<TrialBalanceRow>
            rowKey="accountId"
            columns={columns}
            dataSource={data.items}
            loading={loading}
            pagination={false}
            summary={() => (
              <Table.Summary.Row>
                <Table.Summary.Cell index={0} colSpan={3}>
                  <strong>Total</strong>
                </Table.Summary.Cell>
                <Table.Summary.Cell index={1} align="right">
                  <strong>{money(data.totalDebit)}</strong>
                </Table.Summary.Cell>
                <Table.Summary.Cell index={2} align="right">
                  <strong>{money(data.totalCredit)}</strong>
                </Table.Summary.Cell>
              </Table.Summary.Row>
            )}
          />
          <Alert
            type={balanced ? 'success' : 'error'}
            showIcon
            message={balanced ? 'Trial Balance is balanced' : `Trial Balance does NOT balance -- difference of ${money(Math.abs(data.totalDebit - data.totalCredit))}`}
          />
        </>
      )}
    </div>
  );
}
