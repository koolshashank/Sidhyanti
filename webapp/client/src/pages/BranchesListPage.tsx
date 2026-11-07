import {
  AppstoreOutlined,
  BarsOutlined,
  EditOutlined,
  EnvironmentOutlined,
  MailOutlined,
  PhoneOutlined,
  PlusOutlined,
} from '@ant-design/icons';
import { Alert, Button, Empty, Segmented, Spin, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import { fetchBranches, type BranchRow } from '../api/branches';
import { AppShell } from '../layouts/AppShell';
import { money, moneyCompact, number } from '../utils/format';

export default function BranchesListPage() {
  const navigate = useNavigate();
  const [branches, setBranches] = useState<BranchRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [view, setView] = useState<'table' | 'card'>('table');

  useEffect(() => {
    fetchBranches()
      .then(setBranches)
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }, []);

  const columns: ColumnsType<BranchRow> = [
    {
      title: 'Branch',
      key: 'branch',
      render: (_, b) => (
        <div>
          <div className="font-semibold text-slate-800">{b.branchName.trim()}</div>
          <div className="text-xs text-slate-400">{b.branchCode}</div>
        </div>
      ),
    },
    {
      title: 'Location',
      key: 'location',
      render: (_, b) => (
        <span className="text-sm text-slate-600">
          {[b.districtName, b.stateName].filter(Boolean).join(', ') || '-'}
          {b.pincode ? ` - ${b.pincode}` : ''}
        </span>
      ),
    },
    {
      title: 'Contact',
      key: 'contact',
      render: (_, b) => (
        <div className="text-sm text-slate-600">
          {b.phone && <div>{b.phone}</div>}
          {b.email && <div className="text-xs text-slate-400">{b.email}</div>}
          {!b.phone && !b.email && '-'}
        </div>
      ),
    },
    { title: 'Members', dataIndex: 'totalMembers', align: 'center', render: (v) => number(v) },
    { title: 'Centers', dataIndex: 'activeCenters', align: 'center', render: (v) => number(v) },
    { title: 'Active Loans', dataIndex: 'activeLoans', align: 'center', render: (v) => number(v) },
    { title: 'Portfolio', dataIndex: 'outstandingPortfolio', align: 'right', render: (v) => moneyCompact(v) },
    { title: 'Lifetime Disbursed', dataIndex: 'lifetimeDisbursed', align: 'right', render: (v) => money(v) },
    {
      title: 'Status',
      dataIndex: 'isActive',
      render: (isActive: boolean) => <Tag color={isActive ? 'success' : 'default'}>{isActive ? 'Active' : 'Inactive'}</Tag>,
    },
    {
      title: '',
      key: 'actions',
      render: (_, b) => <Button icon={<EditOutlined />} size="small" onClick={() => navigate(`/branches/${b.branchId}/edit`)} />,
    },
  ];

  return (
    <AppShell title="Branches">
      <div className="flex flex-col gap-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <span className="text-sm text-slate-500">{branches.length} branch offices</span>
          <div className="flex items-center gap-3">
            <Segmented
              value={view}
              onChange={(v) => setView(v as 'table' | 'card')}
              options={[
                { label: 'Table', value: 'table', icon: <BarsOutlined /> },
                { label: 'Card', value: 'card', icon: <AppstoreOutlined /> },
              ]}
            />
            <Button type="primary" icon={<PlusOutlined />} style={{ background: '#0b2545' }} onClick={() => navigate('/branches/new')}>
              New Branch
            </Button>
          </div>
        </div>

        {error && <Alert type="error" message={error} showIcon />}

        {loading ? (
          <div className="flex h-60 items-center justify-center">
            <Spin size="large" />
          </div>
        ) : branches.length === 0 ? (
          <Empty description="No branches yet" />
        ) : view === 'table' ? (
          <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <Table<BranchRow> rowKey="branchId" columns={columns} dataSource={branches} pagination={false} />
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            {branches.map((b) => (
              <BranchCard key={b.branchId} branch={b} onEdit={() => navigate(`/branches/${b.branchId}/edit`)} />
            ))}
          </div>
        )}
      </div>
    </AppShell>
  );
}

function BranchCard({ branch, onEdit }: { branch: BranchRow; onEdit: () => void }) {
  return (
    <div className="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm transition-shadow hover:shadow-md">
      <div className="h-1.5" style={{ background: branch.isActive ? 'linear-gradient(90deg, #c8952c, #e8b95a)' : '#cbd5e1' }} />
      <div className="p-5">
        <div className="mb-3 flex items-start justify-between">
          <div>
            <h3 className="text-base font-bold text-slate-800">{branch.branchName.trim()}</h3>
            <span className="text-xs text-slate-400">{branch.branchCode}</span>
          </div>
          <div className="flex items-center gap-2">
            <Tag color={branch.isActive ? 'success' : 'default'}>{branch.isActive ? 'Active' : 'Inactive'}</Tag>
            <Button icon={<EditOutlined />} size="small" onClick={onEdit} />
          </div>
        </div>

        <div className="mb-4 flex flex-col gap-1.5 text-xs text-slate-500">
          {branch.address && (
            <div className="flex items-start gap-2">
              <EnvironmentOutlined className="mt-0.5" />
              <span>
                {branch.address}
                {branch.districtName ? `, ${branch.districtName}` : ''}
                {branch.stateName ? `, ${branch.stateName}` : ''}
                {branch.pincode ? ` - ${branch.pincode}` : ''}
              </span>
            </div>
          )}
          {branch.phone && (
            <div className="flex items-center gap-2">
              <PhoneOutlined /> {branch.phone}
            </div>
          )}
          {branch.email && (
            <div className="flex items-center gap-2">
              <MailOutlined /> {branch.email}
            </div>
          )}
        </div>

        <div className="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3">
          <Stat label="Members" value={number(branch.totalMembers)} />
          <Stat label="Centers" value={number(branch.activeCenters)} />
          <Stat label="Active Loans" value={number(branch.activeLoans)} />
          <Stat label="Portfolio" value={moneyCompact(branch.outstandingPortfolio)} />
        </div>
        <div className="mt-3 text-center text-xs text-slate-400">Lifetime Disbursed: {money(branch.lifetimeDisbursed)}</div>
      </div>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-sm font-bold text-slate-800">{value}</div>
      <div className="text-[11px] text-slate-400">{label}</div>
    </div>
  );
}
