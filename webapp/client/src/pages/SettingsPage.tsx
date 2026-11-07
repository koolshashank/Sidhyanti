import { Tabs } from 'antd';
import { AppShell } from '../layouts/AppShell';
import AmBranchMappingTab from './settings/AmBranchMappingTab';
import AppSettingsTab from './settings/AppSettingsTab';
import DesignationsTab from './settings/DesignationsTab';
import DeviceLockTab from './settings/DeviceLockTab';
import KfsSettingsTab from './settings/KfsSettingsTab';
import MenuVisibilityTab from './settings/MenuVisibilityTab';
import MobileMenuAccessTab from './settings/MobileMenuAccessTab';
import RegisteredDevicesTab from './settings/RegisteredDevicesTab';
import RolePermissionsTab from './settings/RolePermissionsTab';

export default function SettingsPage() {
  return (
    <AppShell title="Settings">
      <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
        <Tabs
          defaultActiveKey="permissions"
          items={[
            { key: 'permissions', label: 'Role Permissions', children: <RolePermissionsTab /> },
            { key: 'designations', label: 'Designations', children: <DesignationsTab /> },
            { key: 'mobile-menu', label: 'Mobile Menu Access', children: <MobileMenuAccessTab /> },
            { key: 'menu-visibility', label: 'Web Menu Visibility', children: <MenuVisibilityTab /> },
            { key: 'am-branch-mapping', label: 'AM Branch Mapping', children: <AmBranchMappingTab /> },
            { key: 'app-settings', label: 'App Settings', children: <AppSettingsTab /> },
            { key: 'device-lock', label: 'Device Lock Settings', children: <DeviceLockTab /> },
            { key: 'devices', label: 'Registered Devices', children: <RegisteredDevicesTab /> },
            { key: 'kfs', label: 'KFS Settings', children: <KfsSettingsTab /> },
          ]}
        />
      </div>
    </AppShell>
  );
}
