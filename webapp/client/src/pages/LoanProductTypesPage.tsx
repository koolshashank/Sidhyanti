import { BoxPlotOutlined, DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { Alert, Button, Form, Input, Modal, Table, Tag, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../api/client';
import {
  createProductType,
  deleteProductType,
  fetchProductTypes,
  updateProductType,
  type ProductTypeRow,
} from '../api/productTypes';
import { AppShell } from '../layouts/AppShell';
import { number } from '../utils/format';

interface FormShape {
  productTypeName: string;
}

export default function LoanProductTypesPage() {
  const [items, setItems] = useState<ProductTypeRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<ProductTypeRow | 'new' | null>(null);
  const [form] = Form.useForm<FormShape>();
  const [saving, setSaving] = useState(false);

  function load() {
    setLoading(true);
    fetchProductTypes()
      .then((res) => setItems(res.items))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  function openEdit(row: ProductTypeRow | 'new') {
    setEditing(row);
    form.resetFields();
    if (row !== 'new') form.setFieldsValue({ productTypeName: row.productTypeName });
  }

  async function handleSave() {
    const values = await form.validateFields();
    setSaving(true);
    try {
      if (editing === 'new') await createProductType(values.productTypeName);
      else if (editing) await updateProductType(editing.productTypeId, values.productTypeName);
      message.success('Saved');
      setEditing(null);
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(row: ProductTypeRow) {
    try {
      await deleteProductType(row.productTypeId);
      message.success('Deleted');
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  const columns: ColumnsType<ProductTypeRow> = [
    { title: 'Product Type', dataIndex: 'productTypeName', render: (v) => <span className="font-semibold text-slate-800">{v}</span> },
    {
      title: 'Loan Schemes Using It',
      dataIndex: 'schemeCount',
      align: 'center',
      render: (v: number) => <Tag color="blue">{number(v)}</Tag>,
    },
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
            onClick={() => Modal.confirm({ title: `Delete "${row.productTypeName}"?`, onOk: () => handleDelete(row) })}
          />
        </div>
      ),
    },
  ];

  return (
    <AppShell title="Loan Product Types">
      <div className="flex flex-col gap-6">
        <div className="flex items-center justify-between">
          <p className="text-sm text-slate-500">
            Product types group your Loan Schemes (e.g. JLG, Business Loan, MSME) -- every new scheme is created against one of these.
          </p>
          <Button type="primary" icon={<PlusOutlined />} onClick={() => openEdit('new')}>
            New Product Type
          </Button>
        </div>

        {error && <Alert type="error" message={error} showIcon />}

        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <Table<ProductTypeRow> rowKey="productTypeId" columns={columns} dataSource={items} loading={loading} pagination={false} />
        </div>
      </div>

      <Modal
        title={editing === 'new' ? 'New Product Type' : 'Edit Product Type'}
        open={editing !== null}
        onCancel={() => setEditing(null)}
        onOk={handleSave}
        confirmLoading={saving}
      >
        <Form<FormShape> form={form} layout="vertical">
          <Form.Item name="productTypeName" label="Product Type Name" rules={[{ required: true, message: 'Required' }]}>
            <Input placeholder="e.g. JLG, Business Loan, MSME" prefix={<BoxPlotOutlined className="text-slate-400" />} />
          </Form.Item>
        </Form>
      </Modal>
    </AppShell>
  );
}
