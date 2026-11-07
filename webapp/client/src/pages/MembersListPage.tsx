import { BankOutlined, DownloadOutlined, FlagOutlined, PercentageOutlined, PhoneOutlined, RightOutlined, SearchOutlined, TeamOutlined, UserSwitchOutlined } from '@ant-design/icons';
import { Alert, Button, Input, Select, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import { downloadMembersCsv, fetchMemberList, fetchMemberLookups, type MemberListStats, type MemberLookups, type MemberRow } from '../api/members';
import { KpiCard } from '../components/KpiCard';
import { AppShell } from '../layouts/AppShell';
import { fdate, money, number } from '../utils/format';

const STAGE_COLORS: Record<string, string> = {
  'New Lead': 'gold',
  Contacted: 'blue',
  'KYC Pending': 'orange',
  Verified: 'cyan',
  Converted: 'green',
  Dropped: 'red',
};

const AVATAR_PALETTE = ['#2563eb', '#059669', '#c8952c', '#7c3aed', '#dc2626', '#0891b2', '#be185d'];

function avatarColor(seed: number): string {
  return AVATAR_PALETTE[seed % AVATAR_PALETTE.length];
}

function initials(firstName: string, lastName: string): string {
  return `${firstName?.[0] ?? ''}${lastName?.[0] ?? ''}`.toUpperCase() || '?';
}

export default function MembersListPage() {
  const navigate = useNavigate();
  const [lookups, setLookups] = useState<MemberLookups | null>(null);
  const [items, setItems] = useState<MemberRow[]>([]);
  const [stats, setStats] = useState<MemberListStats | null>(null);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [q, setQ] = useState('');
  const [branch, setBranch] = useState<number | undefined>();
  const [stage, setStage] = useState<number | undefined>();
  const [officer, setOfficer] = useState<number | undefined>();

  useEffect(() => {
    fetchMemberLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    fetchMemberList({ page, perPage, q: q || undefined, branch, stage, officer })
      .then((res) => {
        setItems(res.items);
        setTotal(res.total);
        setStats(res.stats);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, q, branch, stage, officer]);

  async function handleExport() {
    setExporting(true);
    try {
      await downloadMembersCsv({ q: q || undefined, branch, stage, officer });
    } finally {
      setExporting(false);
    }
  }

  const columns: ColumnsType<MemberRow> = [
    {
      title: 'Member',
      key: 'member',
      render: (_, row) => (
        <div className="flex items-center gap-3">
          <div
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
            style={{ backgroundColor: avatarColor(row.memberId) }}
          >
            {initials(row.firstName, row.lastName)}
          </div>
          <div>
            <div className="font-semibold text-slate-800">
              {row.firstName} {row.lastName}
              {row.gender && <span className="ml-1 text-xs font-normal text-slate-400">({row.gender})</span>}
            </div>
            <div className="text-xs text-slate-400">
              {row.enrollmentNo}
              {row.mobileNo && (
                <>
                  {' · '}
                  <a
                    href={`tel:${row.mobileNo}`}
                    onClick={(e) => e.stopPropagation()}
                    className="text-slate-500 hover:text-blue-600"
                  >
                    <PhoneOutlined className="mr-1" />
                    {row.mobileNo}
                  </a>
                </>
              )}
            </div>
          </div>
        </div>
      ),
    },
    {
      title: 'Branch / Center',
      key: 'location',
      render: (_, row) => (
        <div className="text-sm">
          <div className="text-slate-700">{row.branchName ?? '-'}</div>
          <div className="text-xs text-slate-400">{row.centerName ?? '-'}</div>
        </div>
      ),
    },
    {
      title: 'Stage',
      dataIndex: 'stageName',
      render: (stageName: string | null) => <Tag color={STAGE_COLORS[stageName ?? ''] ?? 'default'}>{stageName ?? '-'}</Tag>,
    },
    {
      title: 'Loan Exposure',
      key: 'loans',
      render: (_, row) =>
        row.activeLoanCount > 0 ? (
          <div className="text-sm">
            <div className="font-semibold text-slate-700">{money(row.activeLoanAmount ?? 0)}</div>
            <div className="text-xs text-slate-400">
              {row.activeLoanCount} active loan{row.activeLoanCount > 1 ? 's' : ''}
            </div>
          </div>
        ) : (
          <span className="text-xs text-slate-400">No active loan</span>
        ),
    },
    { title: 'Assigned To', dataIndex: 'assignee', render: (v) => v ?? 'Unassigned' },
    { title: 'Joined', dataIndex: 'createdAt', render: (v) => fdate(v) },
    {
      title: '',
      key: 'actions',
      width: 40,
      render: (_, row) => (
        <Button
          type="text"
          size="small"
          icon={<RightOutlined />}
          onClick={(e) => {
            e.stopPropagation();
            navigate(`/members/${row.memberId}`);
          }}
        />
      ),
    },
  ];

  return (
    <AppShell title="Members / Leads">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard icon={<TeamOutlined />} label="Total Members" value={number(stats?.totalMembers ?? 0)} gradient={['#2563eb', '#1e3a8a']} />
          <KpiCard icon={<FlagOutlined />} label="New Leads" value={number(stats?.newLeads ?? 0)} gradient={['#c8952c', '#8a5a0a']} />
          <KpiCard icon={<UserSwitchOutlined />} label="Converted" value={number(stats?.converted ?? 0)} gradient={['#059669', '#065f46']} />
          <KpiCard icon={<PercentageOutlined />} label="Conversion Rate" value={`${stats?.conversionRate ?? 0}%`} gradient={['#dc2626', '#7f1d1d']} />
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Input
              placeholder="Name, mobile or enrollment no..."
              prefix={<SearchOutlined className="text-slate-400" />}
              style={{ width: 260 }}
              className="border-slate-200 font-medium shadow-sm hover:border-[#0b2545] focus-within:border-[#0b2545]"
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
              className="shadow-sm"
              allowClear
              value={branch}
              onChange={(v) => {
                setPage(1);
                setBranch(v);
              }}
              options={lookups?.branches.map((b) => ({ value: b.id, label: b.name }))}
            />
            <Select
              placeholder="All Stages"
              prefix={<FlagOutlined className="text-slate-400" />}
              style={{ width: 170 }}
              className="shadow-sm"
              allowClear
              value={stage}
              onChange={(v) => {
                setPage(1);
                setStage(v);
              }}
              options={lookups?.stages.map((s) => ({ value: s.id, label: s.name }))}
            />
            <Select
              placeholder="All Officers"
              prefix={<UserSwitchOutlined className="text-slate-400" />}
              style={{ width: 190 }}
              className="shadow-sm"
              allowClear
              showSearch
              optionFilterProp="label"
              value={officer}
              onChange={(v) => {
                setPage(1);
                setOfficer(v);
              }}
              options={lookups?.officers.map((o) => ({ value: o.id, label: o.name }))}
            />
            <Button icon={<DownloadOutlined />} loading={exporting} onClick={handleExport} style={{ marginLeft: 'auto' }}>
              Export CSV
            </Button>
          </div>

          {error && <Alert type="error" message={error} showIcon className="mb-4" />}

          <Table<MemberRow>
            rowKey="memberId"
            columns={columns}
            dataSource={items}
            loading={loading}
            onRow={(row) => ({ onClick: () => navigate(`/members/${row.memberId}`), className: 'cursor-pointer' })}
            pagination={{
              current: page,
              pageSize: perPage,
              total,
              onChange: (p, ps) => {
                setPage(p);
                setPerPage(ps);
              },
              showTotal: (t) => `${t} members`,
            }}
          />
        </div>
      </div>
    </AppShell>
  );
}
