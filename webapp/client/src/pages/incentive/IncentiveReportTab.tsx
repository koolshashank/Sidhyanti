import { Alert, Select, Table } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchIncentiveLookups, fetchIncentiveReport, type IncentiveReportRow, type LookupOption } from '../../api/incentive';
import { KpiCard } from '../../components/KpiCard';
import { WalletOutlined } from '@ant-design/icons';
import { money, number } from '../../utils/format';

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December',
].map((label, i) => ({ value: i + 1, label }));

const now = new Date();

export default function IncentiveReportTab() {
  const [branches, setBranches] = useState<LookupOption[]>([]);
  const [items, setItems] = useState<IncentiveReportRow[]>([]);
  const [totalIncentive, setTotalIncentive] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [branch, setBranch] = useState<number | undefined>();

  useEffect(() => {
    fetchIncentiveLookups().then((res) => setBranches(res.branches)).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchIncentiveReport({ year, month, branch })
      .then((res) => {
        setItems(res.items);
        setTotalIncentive(res.totalIncentive);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }, [year, month, branch]);

  const columns: ColumnsType<IncentiveReportRow> = [
    { title: 'Staff', dataIndex: 'fullName', render: (v) => <span className="font-semibold text-slate-800">{v}</span> },
    { title: 'Branch', dataIndex: 'branchName', render: (v) => v ?? '-' },
    {
      title: 'Disbursed / Target',
      key: 'disb',
      align: 'right',
      render: (_, row) => (
        <div>
          <div>{money(row.amountDisbursed)}</div>
          <div className="text-xs text-slate-400">of {money(row.target)}</div>
        </div>
      ),
    },
    { title: 'Disb %', dataIndex: 'disbPercent', align: 'center', render: (v: number) => `${v}%` },
    { title: 'Disb Incentive', dataIndex: 'disbIncentive', align: 'right', render: (v: number) => money(v) },
    {
      title: 'Collected / Due',
      key: 'coll',
      align: 'right',
      render: (_, row) => (
        <div>
          <div>{money(row.collected)}</div>
          <div className="text-xs text-slate-400">of {money(row.due)}</div>
        </div>
      ),
    },
    { title: 'Coll %', dataIndex: 'collPercent', align: 'center', render: (v: number) => `${v}%` },
    { title: 'Coll Incentive', dataIndex: 'collIncentive', align: 'right', render: (v: number) => money(v) },
    {
      title: 'Total',
      dataIndex: 'total',
      align: 'right',
      render: (v: number) => <span className="font-bold text-emerald-700">{money(v)}</span>,
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="max-w-xs">
        <KpiCard
          icon={<WalletOutlined />}
          label={`Total Incentive Payout -- ${MONTHS[month - 1]?.label} ${year}`}
          value={money(totalIncentive)}
          gradient={['#059669', '#065f46']}
        />
      </div>

      <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <div className="mb-4 flex flex-wrap items-center gap-3">
          <Select value={month} onChange={setMonth} style={{ width: 150 }} options={MONTHS} />
          <Select
            value={year}
            onChange={setYear}
            style={{ width: 110 }}
            options={Array.from({ length: 5 }, (_, i) => now.getFullYear() - i).map((y) => ({ value: y, label: y }))}
          />
          <Select
            placeholder="All Branches"
            style={{ width: 180 }}
            allowClear
            value={branch}
            onChange={setBranch}
            options={branches.map((b) => ({ value: b.id, label: b.name }))}
          />
          <span className="text-xs text-slate-400">{number(items.length)} staff</span>
        </div>

        {error && <Alert type="error" message={error} showIcon className="mb-4" />}

        <Table<IncentiveReportRow> rowKey="staffId" columns={columns} dataSource={items} loading={loading} pagination={false} scroll={{ x: true }} />
      </div>
    </div>
  );
}
