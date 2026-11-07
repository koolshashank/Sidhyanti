import { SaveOutlined } from '@ant-design/icons';
import { Alert, Button, Spin, Switch, Table, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchDeviceLock, saveDeviceLock, type DeviceLockRole } from '../../api/settings';

export default function DeviceLockTab() {
  const [roles, setRoles] = useState<DeviceLockRole[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchDeviceLock()
      .then((res) => setRoles(res.roles))
      .catch((err) => setError(apiErrorMessage(err)));
  }, []);

  if (error) return <Alert type="error" message={error} showIcon />;
  if (!roles) return <Spin />;

  async function handleSave() {
    if (!roles) return;
    setSaving(true);
    try {
      await saveDeviceLock(roles.filter((r) => r.deviceLockEnabled).map((r) => r.roleId));
      message.success('Saved');
    } catch (err) {
      message.error(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  const columns: ColumnsType<DeviceLockRole> = [
    { title: 'Role', dataIndex: 'roleName', render: (v) => <span className="font-semibold text-slate-800">{v}</span> },
    {
      title: 'Lock to one device',
      dataIndex: 'deviceLockEnabled',
      align: 'center',
      render: (checked: boolean, row) => (
        <Switch
          checked={checked}
          onChange={(value) => setRoles((prev) => prev!.map((r) => (r.roleId === row.roleId ? { ...r, deviceLockEnabled: value } : r)))}
        />
      ),
    },
  ];

  return (
    <div className="max-w-xl">
      <p className="mb-4 text-sm text-slate-500">
        When enabled, staff with that role get locked to the first mobile device (phone) they log in from. Use "Registered Devices" to reset a
        locked staff member.
      </p>
      <Table<DeviceLockRole> rowKey="roleId" columns={columns} dataSource={roles} pagination={false} className="rounded-2xl border border-slate-100" />
      <Button type="primary" icon={<SaveOutlined />} loading={saving} onClick={handleSave} className="mt-4">
        Save
      </Button>
    </div>
  );
}
