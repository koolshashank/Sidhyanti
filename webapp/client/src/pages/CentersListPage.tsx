import {
  CheckCircleOutlined,
  EditOutlined,
  EnvironmentOutlined,
  SearchOutlined,
  SwapOutlined,
  TeamOutlined,
  UserSwitchOutlined,
} from '@ant-design/icons';
import { Alert, Button, Input, Select, Table, Tag, Tooltip } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchCenterList, fetchCenterLookups, type CenterListStats, type CenterLookups, type CenterRow } from '../api/centers';
import { apiErrorMessage } from '../api/client';
import { KpiCard } from '../components/KpiCard';
import { ShiftMeetingModal } from '../components/ShiftMeetingModal';
import { TransferCenterModal } from '../components/TransferCenterModal';
import { AppShell } from '../layouts/AppShell';
import { number, weekdayName } from '../utils/format';

const WEEKDAY_OPTIONS = [
  { value: 1, label: 'Monday' },
  { value: 2, label: 'Tuesday' },
  { value: 3, label: 'Wednesday' },
  { value: 4, label: 'Thursday' },
  { value: 5, label: 'Friday' },
  { value: 6, label: 'Saturday' },
  { value: 7, label: 'Sunday' },
];

export default function CentersListPage() {
  const navigate = useNavigate();
  const [lookups, setLookups] = useState<CenterLookups | null>(null);
  const [items, setItems] = useState<CenterRow[]>([]);
  const [stats, setStats] = useState<CenterListStats | null>(null);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [q, setQ] = useState('');
  const [branch, setBranch] = useState<number | undefined>();
  const [day, setDay] = useState<number | undefined>();
  const [status, setStatus] = useState<'active' | 'inactive' | undefined>();
  const [shiftingCenter, setShiftingCenter] = useState<CenterRow | null>(null);
  const [transferringCenter, setTransferringCenter] = useState<CenterRow | null>(null);

  useEffect(() => {
    fetchCenterLookups().then(setLookups).catch(() => {});
  }, []);

  function load() {
    setLoading(true);
    setError(null);
    fetchCenterList({ page, perPage, q: q || undefined, branch, day, status })
      .then((res) => {
        setItems(res.items);
        setTotal(res.total);
        setStats(res.stats);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, [page, q, branch, day, status]);

  const columns: ColumnsType<CenterRow> = [
    { title: 'Code', dataIndex: 'centerCode', render: (v) => <span className="font-semibold text-slate-700">{v}</span> },
    {
      title: 'Center',
      key: 'center',
      render: (_, c) => (
        <div>
          <div className="font-semibold text-slate-800">{c.centerName.trim()}</div>
          {c.centerPlace && <div className="text-xs text-slate-400">{c.centerPlace}</div>}
        </div>
      ),
    },
    { title: 'Branch', dataIndex: 'branchName', render: (v) => v ?? '-' },
    {
      title: 'Contact',
      key: 'contact',
      render: (_, c) => (
        <div className="text-sm text-slate-600">
          {c.contactPerson && <div>{c.contactPerson}</div>}
          {c.contactNo && <div className="text-xs text-slate-400">{c.contactNo}</div>}
          {!c.contactPerson && !c.contactNo && '-'}
        </div>
      ),
    },
    {
      title: 'Schedule',
      key: 'schedule',
      render: (_, c) => (
        <div className="text-sm text-slate-600">
          <div>{weekdayName(c.meetingDay)}</div>
          {c.meetingTime && <div className="text-xs text-slate-400">{c.meetingTime}</div>}
        </div>
      ),
    },
    { title: 'Managed By', dataIndex: 'managerName', render: (v) => v?.trim() || 'Unassigned' },
    {
      title: 'Members',
      dataIndex: 'memberCount',
      align: 'center',
      render: (v: number) => (
        <Tag icon={<TeamOutlined />} color="blue">
          {number(v)}
        </Tag>
      ),
    },
    {
      title: 'Status',
      dataIndex: 'isActive',
      render: (isActive: boolean) => <Tag color={isActive ? 'success' : 'default'}>{isActive ? 'Active' : 'Inactive'}</Tag>,
    },
    {
      title: '',
      key: 'actions',
      render: (_, c) => (
        <div className="flex items-center gap-2">
          {c.latitude && c.longitude && (
            <Tooltip title="View on map">
              <Button
                icon={<EnvironmentOutlined />}
                size="small"
                onClick={(e) => {
                  e.stopPropagation();
                  window.open(`https://www.google.com/maps?q=${c.latitude},${c.longitude}`, '_blank');
                }}
              />
            </Tooltip>
          )}
          <Tooltip title="Shift Meeting">
            <Button
              icon={<SwapOutlined />}
              size="small"
              onClick={(e) => {
                e.stopPropagation();
                setShiftingCenter(c);
              }}
            />
          </Tooltip>
          <Tooltip title="Transfer Center">
            <Button
              icon={<UserSwitchOutlined />}
              size="small"
              onClick={(e) => {
                e.stopPropagation();
                setTransferringCenter(c);
              }}
            />
          </Tooltip>
          <Button icon={<EditOutlined />} size="small" onClick={() => navigate(`/centers/${c.centerId}/edit`)}>
            Edit
          </Button>
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Centers">
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard icon={<TeamOutlined />} label="Total Centers" value={number(stats?.totalCenters ?? 0)} gradient={['#2563eb', '#1e3a8a']} />
          <KpiCard icon={<CheckCircleOutlined />} label="Active Centers" value={number(stats?.activeCenters ?? 0)} gradient={['#059669', '#065f46']} />
          <KpiCard icon={<EnvironmentOutlined />} label="Branches Covered" value={number(stats?.branchesCovered ?? 0)} gradient={['#c8952c', '#8a5a0a']} />
          <KpiCard icon={<TeamOutlined />} label="Members in Centers" value={number(stats?.totalMembersInCenters ?? 0)} gradient={['#7c3aed', '#4c1d95']} />
        </div>

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <Input
              placeholder="Center name, code, contact person or number..."
              prefix={<SearchOutlined className="text-slate-400" />}
              style={{ width: 280 }}
              value={q}
              onChange={(e) => {
                setPage(1);
                setQ(e.target.value);
              }}
              allowClear
            />
            <Select
              placeholder="All Branches"
              style={{ width: 170 }}
              allowClear
              value={branch}
              onChange={(v) => {
                setPage(1);
                setBranch(v);
              }}
              options={lookups?.branches.map((b) => ({ value: b.id, label: b.name }))}
            />
            <Select
              placeholder="Any Day"
              style={{ width: 150 }}
              allowClear
              value={day}
              onChange={(v) => {
                setPage(1);
                setDay(v);
              }}
              options={WEEKDAY_OPTIONS}
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

          <Table<CenterRow>
            rowKey="centerId"
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
              showTotal: (t) => `${t} centers`,
            }}
          />
        </div>
      </div>

      {shiftingCenter && (
        <ShiftMeetingModal
          center={shiftingCenter}
          onClose={() => setShiftingCenter(null)}
          onDone={() => {
            setShiftingCenter(null);
            load();
          }}
        />
      )}

      {transferringCenter && (
        <TransferCenterModal
          center={transferringCenter}
          branchStaff={(lookups?.staff ?? []).filter((s) => s.branchId === transferringCenter.branchId)}
          onClose={() => setTransferringCenter(null)}
          onDone={() => {
            setTransferringCenter(null);
            load();
          }}
        />
      )}
    </AppShell>
  );
}
