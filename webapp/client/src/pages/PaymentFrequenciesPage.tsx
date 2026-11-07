import { CalendarOutlined, DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { Alert, Button, Form, Input, Modal, Switch, Table, Tag, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../api/client';
import {
  createPaymentFrequency,
  deletePaymentFrequency,
  fetchPaymentFrequencies,
  updatePaymentFrequency,
  type PaymentFrequencyRow,
} from '../api/paymentFrequencies';
import { AppShell } from '../layouts/AppShell';
import { number } from '../utils/format';

interface FormShape {
  frequencyName: string;
  isActive: boolean;
}

export default function PaymentFrequenciesPage() {
  const [items, setItems] = useState<PaymentFrequencyRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<PaymentFrequencyRow | 'new' | null>(null);
  const [form] = Form.useForm<FormShape>();
  const [saving, setSaving] = useState(false);

  function load() {
    setLoading(true);
    fetchPaymentFrequencies()
      .then((res) => setItems(res.items))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  function openEdit(row: PaymentFrequencyRow | 'new') {
    setEditing(row);
    if (row === 'new') form.resetFields();
    else form.setFieldsValue({ frequencyName: row.frequencyName, isActive: row.isActive });
  }

  async function handleSave() {
    const values = await form.validateFields();
    setSaving(true);
    try {
      if (editing === 'new') await createPaymentFrequency(values.frequencyName);
      else if (editing) await updatePaymentFrequency(editing.frequencyId, values.frequencyName, values.isActive);
      message.success('Saved');
      setEditing(null);
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(row: PaymentFrequencyRow) {
    try {
      await deletePaymentFrequency(row.frequencyId);
      message.success('Deleted');
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  const columns: ColumnsType<PaymentFrequencyRow> = [
    { title: 'Frequency', dataIndex: 'frequencyName', render: (v) => <span className="font-semibold text-slate-800">{v}</span> },
    { title: 'Loan Schemes Using It', dataIndex: 'schemeCount', align: 'center', render: (v: number) => <Tag color="blue">{number(v)}</Tag> },
    { title: 'Status', dataIndex: 'isActive', render: (v: boolean) => <Tag color={v ? 'success' : 'default'}>{v ? 'Active' : 'Inactive'}</Tag> },
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
            onClick={() => Modal.confirm({ title: `Delete "${row.frequencyName}"?`, onOk: () => handleDelete(row) })}
          />
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Payment Frequencies">
      <div className="flex flex-col gap-6">
        <div className="flex items-center justify-between">
          <p className="text-sm text-slate-500">
            How often a loan scheme collects installments (Weekly, Fortnightly, Monthly) -- every Loan Scheme picks one of these.
          </p>
          <Button type="primary" icon={<PlusOutlined />} onClick={() => openEdit('new')}>
            New Frequency
          </Button>
        </div>

        {error && <Alert type="error" message={error} showIcon />}

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <Table<PaymentFrequencyRow> rowKey="frequencyId" columns={columns} dataSource={items} loading={loading} pagination={false} />
        </div>
      </div>

      <Modal
        title={editing === 'new' ? 'New Payment Frequency' : 'Edit Payment Frequency'}
        open={editing !== null}
        onCancel={() => setEditing(null)}
        onOk={handleSave}
        confirmLoading={saving}
      >
        <Form<FormShape> form={form} layout="vertical" initialValues={{ isActive: true }}>
          <Form.Item name="frequencyName" label="Frequency Name" rules={[{ required: true, message: 'Required' }]}>
            <Input placeholder="e.g. Weekly, Fortnightly, Monthly" prefix={<CalendarOutlined className="text-slate-400" />} />
          </Form.Item>
          {editing !== 'new' && (
            <Form.Item name="isActive" label="Active" valuePropName="checked">
              <Switch />
            </Form.Item>
          )}
        </Form>
      </Modal>
    </AppShell>
  );
}
