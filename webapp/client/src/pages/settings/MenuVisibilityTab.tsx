import { SaveOutlined } from '@ant-design/icons';
import { Alert, Button, Spin, Switch, Table, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchMenuVisibility, saveMenuVisibility, type MenuVisibilityItem } from '../../api/menuVisibility';

export default function MenuVisibilityTab() {
  const [items, setItems] = useState<MenuVisibilityItem[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchMenuVisibility()
      .then((res) => setItems(res.items))
      .catch((err) => setError(apiErrorMessage(err)));
  }, []);

  if (error) return <Alert type="error" message={error} showIcon />;
  if (!items) return <Spin />;

  async function handleSave() {
    if (!items) return;
    setSaving(true);
    try {
      await saveMenuVisibility(items.filter((i) => !i.visible).map((i) => i.key));
      message.success('Saved -- hidden menus will disappear for everyone on next page load');
    } catch (err) {
      message.error(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  const columns: ColumnsType<MenuVisibilityItem> = [
    { title: 'Menu', dataIndex: 'label', render: (v) => <span className="font-semibold text-slate-800">{v}</span> },
    {
      title: 'Visible to everyone',
      dataIndex: 'visible',
      align: 'center',
      render: (visible: boolean, row) => (
        <Switch
          checked={visible}
          onChange={(value) => setItems((prev) => prev!.map((i) => (i.key === row.key ? { ...i, visible: value } : i)))}
        />
      ),
    },
  ];

  return (
    <div className="max-w-xl">
      <p className="mb-4 text-sm text-slate-500">
        Turn a menu off here and it disappears from the sidebar for every user, including other Admins -- not role-based, a hard global switch. The
        Settings menu itself can never be hidden, so there's always a way back in to re-enable one.
      </p>
      <Table<MenuVisibilityItem>
        rowKey="key"
        columns={columns}
        dataSource={items}
        pagination={false}
        className="rounded-2xl border border-slate-100"
      />
      <Button type="primary" icon={<SaveOutlined />} loading={saving} onClick={handleSave} className="mt-4">
        Save
      </Button>
    </div>
  );
}
