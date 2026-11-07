import {
  ApartmentOutlined,
  BankOutlined,
  CrownOutlined,
  EditOutlined,
  IdcardOutlined,
  PhoneOutlined,
  PlusOutlined,
  SafetyCertificateOutlined,
  SearchOutlined,
  SolutionOutlined,
  TeamOutlined,
  UserSwitchOutlined,
  UsergroupAddOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import { Alert, Avatar, Button, Input, Select, Table, Tag, Tooltip } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import { fetchStaffList, fetchStaffLookups, type StaffListStats, type StaffLookups, type StaffRow } from '../api/staff';
import { KpiCard } from '../components/KpiCard';
import { AppShell } from '../layouts/AppShell';

const ROLE_COLORS: Record<string, string> = {
  Admin: 'blue',
  SuperAdmin: 'geekblue',
  'Branch Manager': 'gold',
  'Loan Officer': 'cyan',
  'Field Officer': 'green',
  'Area Manager': 'purple',
  Accountant: 'magenta',
  HR: 'orange',
};

const ROLE_ICONS: Record<string, React.ReactNode> = {
  Admin: <CrownOutlined />,
  SuperAdmin: <CrownOutlined />,
  'Branch Manager': <ApartmentOutlined />,
  'Area Manager': <UserSwitchOutlined />,
  'Loan Officer': <SolutionOutlined />,
  'Field Officer': <TeamOutlined />,
  Accountant: <IdcardOutlined />,
  HR: <SafetyCertificateOutlined />,
};

export default function StaffListPage() {
  const navigate = useNavigate();
  const [lookups, setLookups] = useState<StaffLookups | null>(null);
  const [items, setItems] = useState<StaffRow[]>([]);
  const [stats, setStats] = useState<StaffListStats | null>(null);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(12);
  const [q, setQ] = useState('');
  const [branch, setBranch] = useState<number | undefined>();
  const [role, setRole] = useState<number | undefined>();
  const [status, setStatus] = useState<'active' | 'inactive' | undefined>();

  useEffect(() => {
    fetchStaffLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchStaffList({ page, perPage, q: q || undefined, branch, role, status })
      .then((res) => {
        setItems(res.items);
        setTotal(res.total);
        setStats(res.stats);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, q, branch, role, status]);

  const columns: ColumnsType<StaffRow> = [
    {
      title: 'Employee',
      key: 'employee',
      render: (_, row) => (
        <div className="flex items-center gap-3">
          <Avatar style={{ backgroundColor: row.isActive ? '#0b2545' : '#94a3b8' }}>{row.fullName.charAt(0)}</Avatar>
          <div>
            <div className="font-semibold text-slate-800">{row.fullName.trim()}</div>
            <div className="text-xs text-slate-400">
              {row.employeeCode} · {row.username}
            </div>
          </div>
        </div>
      ),
    },
    {
      title: 'Role',
      dataIndex: 'roleName',
      render: (roleName: string | null) => (
        <Tag color={ROLE_COLORS[roleName ?? ''] ?? 'default'} icon={ROLE_ICONS[roleName ?? '']}>
          {roleName ?? '-'}
        </Tag>
      ),
    },
    { title: 'Designation', dataIndex: 'designationName', render: (v) => v ?? '-' },
    {
      title: 'Branch',
      dataIndex: 'branchName',
      render: (v) => (
        <span className="inline-flex items-center gap-1.5 text-slate-600">
          <BankOutlined className="text-slate-400" />
          {v ?? 'Unassigned'}
        </span>
      ),
    },
    {
      title: 'Phone',
      dataIndex: 'phone',
      render: (v) =>
        v ? (
          <span className="inline-flex items-center gap-1.5 text-slate-600">
            <PhoneOutlined className="text-slate-400" />
            {v}
          </span>
        ) : (
          '-'
        ),
    },
    { title: 'Leads', dataIndex: 'leadCount', align: 'center' },
    { title: 'Active Loans', dataIndex: 'activeLoans', align: 'center' },
    {
      title: 'Status',
      dataIndex: 'isActive',
      render: (isActive: boolean) => (
        <span className={`inline-flex items-center gap-1.5 text-sm font-medium ${isActive ? 'text-emerald-600' : 'text-slate-400'}`}>
          <span className={`h-1.5 w-1.5 rounded-full ${isActive ? 'bg-emerald-500' : 'bg-slate-400'}`} />
          {isActive ? 'Active' : 'Inactive'}
        </span>
      ),
    },
    {
      title: '',
      key: 'actions',
      render: (_, row) => (
        <Tooltip title="Edit">
          <Button icon={<EditOutlined />} size="small" onClick={() => navigate(`/staff/${row.staffId}/edit`)} />
        </Tooltip>
      ),
    },
  ];

  return (
    <AppShell title="Staff">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard icon={<TeamOutlined />} label="Total Employees" value={String(stats?.totalStaff ?? '-')} gradient={['#2563eb', '#1e3a8a']} />
          <KpiCard icon={<UsergroupAddOutlined />} label="Active Employees" value={String(stats?.activeStaff ?? '-')} gradient={['#059669', '#065f46']} />
          <KpiCard icon={<ApartmentOutlined />} label="Branches Covered" value={String(stats?.branchesCovered ?? '-')} gradient={['#c8952c', '#8a5a0a']} />
          <KpiCard icon={<WalletOutlined />} label="Active Loans Managed" value={String(stats?.activeLoansManaged ?? '-')} gradient={['#7c3aed', '#4c1d95']} />
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Input
              placeholder="Search name, code or phone..."
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
              placeholder="All Branches"
              prefix={<BankOutlined className="text-slate-400" />}
              style={{ width: 180 }}
              allowClear
              value={branch}
              onChange={(v) => {
                setPage(1);
                setBranch(v);
              }}
              options={lookups?.branches.map((b) => ({ value: b.id, label: b.name }))}
            />
            <Select
              placeholder="All Roles"
              prefix={<SafetyCertificateOutlined className="text-slate-400" />}
              style={{ width: 180 }}
              allowClear
              value={role}
              onChange={(v) => {
                setPage(1);
                setRole(v);
              }}
              options={lookups?.roles.map((r) => ({ value: r.id, label: r.name }))}
            />
            <Select
              placeholder="All Status"
              prefix={<IdcardOutlined className="text-slate-400" />}
              style={{ width: 150 }}
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
            <Button type="primary" icon={<PlusOutlined />} style={{ background: '#0b2545', marginLeft: 'auto' }} onClick={() => navigate('/staff/new')}>
              Add Employee
            </Button>
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<StaffRow>
            rowKey="staffId"
            columns={columns}
            dataSource={items}
            loading={loading}
            pagination={{
              current: page,
              pageSize: perPage,
              total,
              onChange: (p, ps) => {
                setPage(p);
                setPerPage(ps);
              },
              showTotal: (t) => `${t} employees`,
            }}
          />
        </div>
      </div>
    </AppShell>
  );
}
