import { MobileOutlined } from '@ant-design/icons';
import { Alert, Button, Modal, Table, Tag, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import dayjs from 'dayjs';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchDevices, resetDevice, type DeviceRow } from '../../api/settings';

export default function RegisteredDevicesTab() {
  const [items, setItems] = useState<DeviceRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function load() {
    setLoading(true);
    fetchDevices()
      .then((res) => setItems(res.items))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  async function handleReset(row: DeviceRow) {
    try {
      await resetDevice(row.staffId);
      message.success(`Device reset for ${row.fullName}`);
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  const columns: ColumnsType<DeviceRow> = [
    {
      title: 'Staff',
      key: 'staff',
      render: (_, row) => (
        <div>
          <div className="font-semibold text-slate-800">{row.fullName}</div>
          <div className="text-xs text-slate-400">{row.username}</div>
        </div>
      ),
    },
    { title: 'Role', dataIndex: 'roleName', render: (v) => <Tag color="blue">{v ?? '-'}</Tag> },
    { title: 'Registered On', dataIndex: 'deviceRegisteredAt', render: (v) => (v ? dayjs(v).format('DD MMM YYYY, h:mm A') : '-') },
    {
      title: '',
      key: 'actions',
      render: (_, row) => (
        <Button
          size="small"
          danger
          icon={<MobileOutlined />}
          onClick={() =>
            Modal.confirm({
              title: `Reset device for ${row.fullName}?`,
              content: 'They will be able to log in from a new phone next time.',
              onOk: () => handleReset(row),
            })
          }
        >
          Reset Device
        </Button>
      ),
    },
  ];

  return (
    <div>
      {error && <Alert type="error" message={error} showIcon className="mb-4" />}
      <Table<DeviceRow> rowKey="staffId" columns={columns} dataSource={items} loading={loading} pagination={false} />
    </div>
  );
}
