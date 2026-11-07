import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, Form, Input, InputNumber, Modal, Select, Switch, Table, Tag, message } from 'antd';
import dayjs from 'dayjs';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import {
  createAccount,
  deleteAccount,
  fetchAccounts,
  updateAccount,
  type AccountRow,
  type AccountType,
  type NormalBalance,
} from '../../api/chartOfAccounts';
import { apiErrorMessage } from '../../api/client';

const TYPE_COLORS: Record<AccountType, string> = {
  Asset: 'blue',
  Liability: 'red',
  Equity: 'purple',
  Income: 'green',
  Expense: 'orange',
};

const DEFAULT_NORMAL: Record<AccountType, NormalBalance> = {
  Asset: 'Debit',
  Expense: 'Debit',
  Liability: 'Credit',
  Equity: 'Credit',
  Income: 'Credit',
};

interface FormShape {
  accountCode: string;
  accountName: string;
  accountType: AccountType;
  normalBalance: NormalBalance;
  parentAccountId?: number;
  isActive: boolean;
  openingBalance?: number;
  openingBalanceDate?: dayjs.Dayjs;
  isCashBankAccount?: boolean;
  isTaxAccount?: boolean;
}

export default function ChartOfAccountsTab() {
  const [items, setItems] = useState<AccountRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<AccountRow | 'new' | null>(null);
  const [form] = Form.useForm<FormShape>();
  const [saving, setSaving] = useState(false);

  function load() {
    setLoading(true);
    fetchAccounts()
      .then((res) => setItems(res.items))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  function openEdit(row: AccountRow | 'new') {
    setEditing(row);
    if (row === 'new') form.resetFields();
    else
      form.setFieldsValue({
        accountCode: row.accountCode,
        accountName: row.accountName,
        accountType: row.accountType,
        normalBalance: row.normalBalance,
        parentAccountId: row.parentAccountId ?? undefined,
        isActive: row.isActive,
        openingBalance: row.openingBalance || undefined,
        openingBalanceDate: row.openingBalanceDate ? dayjs(row.openingBalanceDate) : undefined,
        isCashBankAccount: row.isCashBankAccount,
        isTaxAccount: row.isTaxAccount,
      });
  }

  async function handleSave() {
    const values = await form.validateFields();
    setSaving(true);
    const payload = { ...values, openingBalanceDate: values.openingBalanceDate ? values.openingBalanceDate.format('YYYY-MM-DD') : null };
    try {
      if (editing === 'new') await createAccount(payload);
      else if (editing) await updateAccount(editing.accountId, payload);
      message.success('Saved');
      setEditing(null);
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(row: AccountRow) {
    try {
      await deleteAccount(row.accountId);
      message.success('Deleted');
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  const columns: ColumnsType<AccountRow> = [
    { title: 'Code', dataIndex: 'accountCode', render: (v) => <span className="font-mono font-semibold text-[#e8edf5]">{v}</span> },
    { title: 'Account Name', dataIndex: 'accountName', render: (v) => <span className="font-semibold text-[#e8edf5]">{v}</span> },
    { title: 'Type', dataIndex: 'accountType', render: (v: AccountType) => <Tag color={TYPE_COLORS[v]}>{v}</Tag> },
    { title: 'Normal Balance', dataIndex: 'normalBalance' },
    { title: 'Parent', dataIndex: 'parentAccountName', render: (v) => v ?? '-' },
    {
      title: 'Flags',
      key: 'flags',
      render: (_, row) => (
        <div className="flex gap-1">
          {row.isCashBankAccount && <Tag color="cyan">Cash/Bank</Tag>}
          {row.isTaxAccount && <Tag color="gold">Tax</Tag>}
        </div>
      ),
    },
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
            onClick={() => Modal.confirm({ title: `Delete "${row.accountName}"?`, onOk: () => handleDelete(row) })}
          />
        </div>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-4 flex justify-end">
        <Button type="primary" icon={<PlusOutlined />} onClick={() => openEdit('new')}>
          New Account
        </Button>
      </div>
      {error && <Alert type="error" message={error} showIcon className="mb-4" />}
      <Table<AccountRow> rowKey="accountId" columns={columns} dataSource={items} loading={loading} pagination={false} />

      <Modal title={editing === 'new' ? 'New Account' : 'Edit Account'} open={editing !== null} onCancel={() => setEditing(null)} onOk={handleSave} confirmLoading={saving}>
        <Form<FormShape>
          form={form}
          layout="vertical"
          initialValues={{ isActive: true }}
          onValuesChange={(changed) => {
            if (changed.accountType) form.setFieldsValue({ normalBalance: DEFAULT_NORMAL[changed.accountType as AccountType] });
          }}
        >
          <Form.Item name="accountCode" label="Account Code" rules={[{ required: true, message: 'Required' }]}>
            <Input placeholder="e.g. 1000" />
          </Form.Item>
          <Form.Item name="accountName" label="Account Name" rules={[{ required: true, message: 'Required' }]}>
            <Input placeholder="e.g. Cash in Hand" />
          </Form.Item>
          <Form.Item name="accountType" label="Account Type" rules={[{ required: true, message: 'Required' }]}>
            <Select options={['Asset', 'Liability', 'Equity', 'Income', 'Expense'].map((t) => ({ value: t, label: t }))} />
          </Form.Item>
          <Form.Item name="normalBalance" label="Normal Balance" rules={[{ required: true, message: 'Required' }]}>
            <Select options={['Debit', 'Credit'].map((t) => ({ value: t, label: t }))} />
          </Form.Item>
          <Form.Item name="parentAccountId" label="Parent Account (optional)">
            <Select
              allowClear
              placeholder="None"
              options={items
                .filter((a) => editing === 'new' || editing === null || a.accountId !== editing.accountId)
                .map((a) => ({ value: a.accountId, label: `${a.accountCode} - ${a.accountName}` }))}
            />
          </Form.Item>
          <div className="grid grid-cols-2 gap-x-4">
            <Form.Item name="openingBalance" label="Opening Balance">
              <InputNumber className="w-full" min={0} step={0.01} />
            </Form.Item>
            <Form.Item name="openingBalanceDate" label="Opening Balance Date">
              <DatePicker className="w-full" format="DD MMM YYYY" />
            </Form.Item>
          </div>
          <Form.Item name="isCashBankAccount" label="Treat as a Cash/Bank account (Cash/Bank Book, Reconciliation)" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item name="isTaxAccount" label="Treat as a Tax (GST) account (GST Summary)" valuePropName="checked">
            <Switch />
          </Form.Item>
          <Form.Item name="isActive" label="Active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
