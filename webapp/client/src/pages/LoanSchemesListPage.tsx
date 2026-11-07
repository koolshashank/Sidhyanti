import { BoxPlotOutlined, CheckCircleOutlined, PercentageOutlined, PlusOutlined, SearchOutlined, TagOutlined } from '@ant-design/icons';
import { Alert, Button, Input, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import { fetchLoanSchemeList, fetchLoanSchemeLookups, type LoanSchemeRow, type LoanSchemeStats } from '../api/loanSchemes';
import { KpiCard } from '../components/KpiCard';
import { AppShell } from '../layouts/AppShell';
import { money, number } from '../utils/format';

export default function LoanSchemesListPage() {
  const navigate = useNavigate();
  const [productTypes, setProductTypes] = useState<{ id: number; name: string }[]>([]);
  const [items, setItems] = useState<LoanSchemeRow[]>([]);
  const [stats, setStats] = useState<LoanSchemeStats | null>(null);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [q, setQ] = useState('');
  const [product, setProduct] = useState<number | undefined>();
  const [status, setStatus] = useState<'active' | 'inactive' | undefined>();

  useEffect(() => {
    fetchLoanSchemeLookups().then((res) => setProductTypes(res.productTypes)).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchLoanSchemeList({ page, perPage, q: q || undefined, product, status })
      .then((res) => {
        setItems(res.items);
        setTotal(res.total);
        setStats(res.stats);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, q, product, status]);

  const columns: ColumnsType<LoanSchemeRow> = [
    { title: 'Code', dataIndex: 'schemeCode', render: (v) => <span className="font-semibold text-slate-700">{v}</span> },
    { title: 'Scheme Name', dataIndex: 'schemeName', render: (v) => <span className="font-semibold text-slate-800">{v}</span> },
    { title: 'Product Type', dataIndex: 'productTypeName', render: (v) => v ?? '-' },
    {
      title: 'Amount Range',
      key: 'amount',
      render: (_, row) =>
        row.minLoanAmount || row.maxLoanAmount
          ? `${money(row.minLoanAmount ?? 0)} - ${money(row.maxLoanAmount ?? 0)}`
          : row.defaultLoanAmount
            ? money(row.defaultLoanAmount)
            : '-',
    },
    { title: 'Tenure', dataIndex: 'tenureMonths', render: (v) => `${v} months` },
    {
      title: 'Interest',
      key: 'interest',
      render: (_, row) => (
        <span className="font-bold text-[#0b2545]">
          {row.interestRatePercent}% {row.interestMethod ? `(${row.interestMethod})` : ''}
        </span>
      ),
    },
    { title: 'Frequency', dataIndex: 'paymentFrequency' },
    { title: 'Status', dataIndex: 'isActive', render: (v: boolean) => <Tag color={v ? 'success' : 'default'}>{v ? 'Active' : 'Inactive'}</Tag> },
    {
      title: '',
      key: 'actions',
      render: (_, row) => (
        <Button size="small" onClick={() => navigate(`/loan-schemes/${row.schemeId}/edit`)}>
          View
        </Button>
      ),
    },
  ];

  return (
    <AppShell title="Loan Schemes">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard icon={<BoxPlotOutlined />} label="Total Schemes" value={number(stats?.totalSchemes ?? 0)} gradient={['#2563eb', '#1e3a8a']} />
          <KpiCard icon={<CheckCircleOutlined />} label="Active Schemes" value={number(stats?.activeSchemes ?? 0)} gradient={['#059669', '#065f46']} />
          <KpiCard icon={<TagOutlined />} label="Product Types" value={number(stats?.productTypesUsed ?? 0)} gradient={['#c8952c', '#8a5a0a']} />
          <KpiCard icon={<PercentageOutlined />} label="Avg Interest Rate" value={`${stats?.avgInterest ?? 0}%`} gradient={['#dc2626', '#7f1d1d']} />
        </div>

        <div className="flex items-center justify-between">
          <p className="text-sm text-slate-500">Every new loan can be issued against one of these pre-configured products.</p>
          <Button type="primary" icon={<PlusOutlined />} onClick={() => navigate('/loan-schemes/new')}>
            New Scheme
          </Button>
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Input
              placeholder="Scheme name or code..."
              prefix={<SearchOutlined className="text-slate-400" />}
              style={{ width: 240 }}
              value={q}
              onChange={(e) => {
                setPage(1);
                setQ(e.target.value);
              }}
              allowClear
            />
            <Select
              placeholder="All Product Types"
              style={{ width: 190 }}
              allowClear
              value={product}
              onChange={(v) => {
                setPage(1);
                setProduct(v);
              }}
              options={productTypes.map((p) => ({ value: p.id, label: p.name }))}
            />
            <Select
              placeholder="Any Status"
              style={{ width: 140 }}
              allowClear
              value={status}
              onChange={(v) => {
                setPage(1);
                setStatus(v);
              }}
              options={[
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
              ]}
            />
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<LoanSchemeRow>
            rowKey="schemeId"
            columns={columns}
            dataSource={items}
            loading={loading}
            onRow={(row) => ({ onClick: () => navigate(`/loan-schemes/${row.schemeId}/edit`), className: 'cursor-pointer' })}
            pagination={{
              current: page,
              pageSize: perPage,
              total,
              onChange: (p, ps) => {
                setPage(p);
                setPerPage(ps);
              },
              showTotal: (t) => `${t} schemes`,
            }}
          />
        </div>
      </div>
    </AppShell>
  );
}
