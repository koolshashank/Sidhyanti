import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { Alert, Button, Form, Input, Modal, Select, Table, Tag, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import {
  createDesignation,
  deleteDesignation,
  fetchDesignationLookups,
  fetchDesignations,
  updateDesignation,
  type DesignationRow,
} from '../../api/settings';

interface FormShape {
  designationName: string;
  roleId: number;
}

export default function DesignationsTab() {
  const [items, setItems] = useState<DesignationRow[]>([]);
  const [roles, setRoles] = useState<{ id: number; name: string }[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<DesignationRow | 'new' | null>(null);
  const [form] = Form.useForm<FormShape>();
  const [saving, setSaving] = useState(false);

  function load() {
    setLoading(true);
    Promise.all([fetchDesignations(), fetchDesignationLookups()])
      .then(([d, l]) => {
        setItems(d.items);
        setRoles(l.roles);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  function openEdit(row: DesignationRow | 'new') {
    setEditing(row);
    if (row === 'new') form.resetFields();
    else form.setFieldsValue({ designationName: row.designationName, roleId: row.roleId });
  }

  async function handleSave() {
    const values = await form.validateFields();
    setSaving(true);
    try {
      if (editing === 'new') await createDesignation(values);
      else if (editing) await updateDesignation(editing.designationId, values);
      message.success('Saved');
      setEditing(null);
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(row: DesignationRow) {
    try {
      await deleteDesignation(row.designationId);
      message.success('Deleted');
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  const columns: ColumnsType<DesignationRow> = [
    { title: 'Designation', dataIndex: 'designationName', render: (v) => <span className="font-semibold text-slate-800">{v}</span> },
    { title: 'Role', dataIndex: 'roleName', render: (v) => <Tag color="blue">{v}</Tag> },
    { title: 'Active Staff', dataIndex: 'activeStaffCount', align: 'center' },
    {
      title: '',
      key: 'actions',
      render: (_, row) => (
        <div className="flex gap-2">
          <Button size="small" icon={<EditOutlined />} onClick={() => openEdit(row)} />
          <Button
            size="small"
            danger
            icon={<DeleteOutlined />}
            onClick={() => Modal.confirm({ title: `Delete "${row.designationName}"?`, onOk: () => handleDelete(row) })}
          />
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-4 flex justify-end">
        <Button type="primary" icon={<PlusOutlined />} onClick={() => openEdit('new')}>
          Add Designation
        </Button>
      </div>
      {error && <Alert type="error" message={error} showIcon className="mb-4" />}
      <Table<DesignationRow> rowKey="designationId" columns={columns} dataSource={items} loading={loading} pagination={false} />

      <Modal
        title={editing === 'new' ? 'Add Designation' : 'Edit Designation'}
        open={editing !== null}
        onCancel={() => setEditing(null)}
        onOk={handleSave}
        confirmLoading={saving}
      >
        <Form<FormShape> form={form} layout="vertical">
          <Form.Item name="designationName" label="Designation Name" rules={[{ required: true, message: 'Required' }]}>
            <Input placeholder="e.g. Cluster Manager" />
          </Form.Item>
          <Form.Item name="roleId" label="Role" rules={[{ required: true, message: 'Required' }]}>
            <Select placeholder="Select role" options={roles.map((r) => ({ value: r.id, label: r.name }))} />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
