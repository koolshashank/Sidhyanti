import {
  AuditOutlined,
  BankOutlined,
  BarChartOutlined,
  BellOutlined,
  BookOutlined,
  BoxPlotOutlined,
  BranchesOutlined,
  CalendarOutlined,
  CheckSquareOutlined,
  CrownOutlined,
  DashboardOutlined,
  FileSearchOutlined,
  HomeOutlined,
  LogoutOutlined,
  PhoneOutlined,
  ReadOutlined,
  SettingOutlined,
  SolutionOutlined,
  StopOutlined,
  TagsOutlined,
  TeamOutlined,
  TrophyOutlined,
  UserOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import { Avatar, Badge, Dropdown, Layout, Menu, Tooltip, type MenuProps } from 'antd';
import { type ReactNode, useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { fetchMenuVisibility } from '../api/menuVisibility';
import { useAuth } from '../auth/AuthContext';

const { Sider, Header, Content } = Layout;

const navItems = [
  { key: 'dashboard', icon: <DashboardOutlined />, label: 'Dashboard', route: '/', enabled: true },
  { key: 'staff', icon: <UserOutlined />, label: 'Staff', route: '/staff', enabled: true },
  { key: 'members', icon: <TeamOutlined />, label: 'Members', route: '/members', enabled: true },
  { key: 'loans', icon: <WalletOutlined />, label: 'Loans', route: '/loans', enabled: true },
  { key: 'loan-approvals', icon: <AuditOutlined />, label: 'Loan Approvals', route: '/loan-approvals', enabled: true },
  { key: 'loan-writeoffs', icon: <StopOutlined />, label: 'Loan Write-offs', route: '/loan-writeoffs', enabled: true },
  { key: 'loan-schemes', icon: <BoxPlotOutlined />, label: 'Loan Schemes', route: '/loan-schemes', enabled: true },
  { key: 'loan-product-types', icon: <TagsOutlined />, label: 'Loan Product Types', route: '/loan-product-types', enabled: true },
  { key: 'payment-frequencies', icon: <CalendarOutlined />, label: 'Payment Frequencies', route: '/payment-frequencies', enabled: true },
  { key: 'collections', icon: <BankOutlined />, label: 'Collections', route: '/collections', enabled: true },
  { key: 'accounting', icon: <BookOutlined />, label: 'Accounting', route: '/accounting', enabled: true },
  { key: 'grt', icon: <CheckSquareOutlined />, label: 'GRT', route: '/grt', enabled: true },
  { key: 'cgt', icon: <ReadOutlined />, label: 'CGT', route: '/cgt', enabled: true },
  { key: 'tvr', icon: <PhoneOutlined />, label: 'TVR', route: '/tvr', enabled: true },
  { key: 'death-entries', icon: <SolutionOutlined />, label: 'Death Entries', route: '/death-entries', enabled: true },
  { key: 'incentive', icon: <TrophyOutlined />, label: 'Incentive', route: '/incentive', enabled: true },
  { key: 'audit-log', icon: <FileSearchOutlined />, label: 'Audit Log', route: '/audit-log', enabled: true },
  { key: 'branches', icon: <BranchesOutlined />, label: 'Branches', route: '/branches', enabled: true },
  { key: 'centers', icon: <HomeOutlined />, label: 'Centers', route: '/centers', enabled: true },
  { key: 'attendance', icon: <CalendarOutlined />, label: 'Meeting Attendance', route: '/attendance', enabled: true },
  { key: 'reports', icon: <BarChartOutlined />, label: 'Reports', route: '/reports', enabled: false },
  { key: 'settings', icon: <SettingOutlined />, label: 'Settings', route: '/settings', enabled: true, adminOnly: true },
  { key: 'billing', icon: <CrownOutlined />, label: 'Billing', route: '/billing', enabled: true, superAdminOnly: true },
];

/**
 * Shared shell (sidebar + top bar) for every rebuilt web page -- disabled
 * nav items are a visible roadmap of what's being rebuilt next, not dead UI.
 */
export function AppShell({ title, children }: { title: string; children: ReactNode }) {
  const { staff, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const isAdmin = staff?.roleName === 'Admin' || staff?.roleName === 'SuperAdmin';
  const isSuperAdmin = staff?.roleName === 'SuperAdmin';
  const [hiddenKeys, setHiddenKeys] = useState<Set<string>>(new Set());

  useEffect(() => {
    fetchMenuVisibility()
      .then((res) => setHiddenKeys(new Set(res.items.filter((i) => !i.visible).map((i) => i.key))))
      .catch(() => {});
  }, []);

  const visibleNavItems = navItems.filter(
    (item) => (!item.adminOnly || isAdmin) && (!item.superAdminOnly || isSuperAdmin) && !hiddenKeys.has(item.key),
  );

  const selectedKey =
    visibleNavItems
      .filter((item) => item.enabled)
      .sort((a, b) => b.route.length - a.route.length)
      .find((item) => location.pathname === item.route || location.pathname.startsWith(item.route + '/'))?.key ?? 'dashboard';

  const userMenu: MenuProps['items'] = [
    { key: 'logout', icon: <LogoutOutlined />, label: 'Logout', onClick: logout },
  ];

  return (
    <Layout className="min-h-screen">
      <Sider width={236} style={{ background: '#0b2545' }} breakpoint="lg" collapsedWidth={0}>
        <div className="flex items-center gap-3 px-6 py-5">
          <img src="/logo.png" alt="Sidhyanti CRM" className="h-9 w-9 rounded-lg object-cover" />
          <span className="text-base font-bold text-white">Sidhyanti CRM</span>
        </div>
        <Menu
          mode="inline"
          selectedKeys={[selectedKey]}
          style={{ background: 'transparent', borderInlineEnd: 'none' }}
          onClick={({ key }) => {
            const item = visibleNavItems.find((i) => i.key === key);
            if (item?.enabled) navigate(item.route);
          }}
          items={visibleNavItems.map((item) => ({
            key: item.key,
            icon: item.icon,
            disabled: !item.enabled,
            label: item.enabled ? (
              item.label
            ) : (
              <Tooltip title="Coming soon" placement="right">
                <span>{item.label}</span>
              </Tooltip>
            ),
          }))}
          theme="dark"
        />
      </Sider>
      <Layout>
        {/* Inline background/color -- deliberately not left to the bg-white/text-slate-* classes
            alone, so this bar can never be repainted by a theme (e.g. Accounting's dark
            ConfigProvider) mounted further down the tree; inline styles always win the cascade. */}
        <Header
          className="flex items-center justify-between border-b border-slate-100 px-6"
          style={{ background: '#ffffff' }}
        >
          <h1 className="m-0 text-lg font-bold" style={{ color: '#1e293b' }}>
            {title}
          </h1>
          <div className="flex items-center gap-5">
            <Badge dot>
              <BellOutlined className="text-lg" style={{ color: '#64748b' }} />
            </Badge>
            <Dropdown menu={{ items: userMenu }} placement="bottomRight">
              <div className="flex cursor-pointer items-center gap-2">
                <Avatar style={{ backgroundColor: '#c8952c' }}>{staff?.fullName?.charAt(0) ?? '?'}</Avatar>
                <div className="hidden text-left sm:block">
                  <div className="text-sm font-semibold leading-tight" style={{ color: '#1e293b' }}>
                    {staff?.fullName}
                  </div>
                  <div className="text-xs leading-tight" style={{ color: '#94a3b8' }}>
                    {staff?.roleName}
                  </div>
                </div>
              </div>
            </Dropdown>
          </div>
        </Header>
        <Content className="bg-slate-50 p-6">{children}</Content>
      </Layout>
    </Layout>
  );
}
