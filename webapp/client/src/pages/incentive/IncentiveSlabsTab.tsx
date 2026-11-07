import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { Alert, Button, Form, InputNumber, Modal, Switch, Table, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import {
  createIncentiveSlab,
  deleteIncentiveSlab,
  fetchIncentiveSlabs,
  toggleIncentiveSlab,
  type IncentiveSlab,
} from '../../api/incentive';
import { money } from '../../utils/format';

interface FormShape {
  minAchievementPercent: number;
  maxAchievementPercent?: number;
  incentiveAmount: number;
}

function SlabTable({
  title,
  helperText,
  slabs,
  onAdd,
  onToggle,
  onDelete,
}: {
  title: string;
  helperText: string;
  slabs: IncentiveSlab[];
  onAdd: (values: FormShape) => Promise<void>;
  onToggle: (id: number) => Promise<void>;
  onDelete: (id: number) => Promise<void>;
}) {
  const [adding, setAdding] = useState(false);
  const [form] = Form.useForm<FormShape>();
  const [saving, setSaving] = useState(false);

  async function handleAdd() {
    const values = await form.validateFields();
    setSaving(true);
    try {
      await onAdd(values);
      setAdding(false);
      form.resetFields();
    } finally {
      setSaving(false);
    }
  }

  const columns: ColumnsType<IncentiveSlab> = [
    { title: 'Min %', dataIndex: 'minAchievementPercent', render: (v: number) => `${v}%` },
    { title: 'Max %', dataIndex: 'maxAchievementPercent', render: (v: number | null) => (v === null ? 'No limit' : `${v}%`) },
    { title: 'Amount', dataIndex: 'incentiveAmount', render: (v: number) => money(v) },
    {
      title: 'Status',
      dataIndex: 'isActive',
      render: (active: boolean, row) => <Switch checked={active} onChange={() => onToggle(row.slabId)} checkedChildren="Active" unCheckedChildren="Inactive" />,
    },
    {
      title: '',
      key: 'actions',
      render: (_, row) => (
        <Button
          size="small"
          danger
          icon={<DeleteOutlined />}
          onClick={() => Modal.confirm({ title: 'Delete this slab?', onOk: () => onDelete(row.slabId) })}
        />
      ),
    },
  ];

  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
      <div className="mb-1 flex items-center justify-between">
        <h3 className="text-base font-semibold text-slate-800">{title}</h3>
        <Button type="primary" size="small" icon={<PlusOutlined />} onClick={() => setAdding(true)}>
          Add Slab
        </Button>
      </div>
      <p className="mb-4 text-sm text-slate-500">{helperText}</p>
      <Table<IncentiveSlab> rowKey="slabId" columns={columns} dataSource={slabs} pagination={false} />

      <Modal title={`Add ${title} Slab`} open={adding} onCancel={() => setAdding(false)} onOk={handleAdd} confirmLoading={saving}>
        <Form<FormShape> form={form} layout="vertical">
          <Form.Item name="minAchievementPercent" label="Min Achievement %" rules={[{ required: true, message: 'Required' }]}>
            <InputNumber min={0} className="w-full" />
          </Form.Item>
          <Form.Item name="maxAchievementPercent" label="Max Achievement % (leave blank for no limit)">
            <InputNumber min={0} className="w-full" />
          </Form.Item>
          <Form.Item name="incentiveAmount" label="Incentive Amount (flat)" rules={[{ required: true, message: 'Required' }]}>
            <InputNumber min={0} className="w-full" prefix="Rs." />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}

export default function IncentiveSlabsTab() {
  const [disbursement, setDisbursement] = useState<IncentiveSlab[]>([]);
  const [collection, setCollection] = useState<IncentiveSlab[]>([]);
  const [error, setError] = useState<string | null>(null);

  function load() {
    fetchIncentiveSlabs()
      .then((res) => {
        setDisbursement(res.disbursement);
        setCollection(res.collection);
      })
      .catch((err) => setError(apiErrorMessage(err)));
  }

  useEffect(load, []);

  async function handleAdd(metricType: 'Disbursement' | 'Collection', values: FormShape) {
    try {
      await createIncentiveSlab({
        metricType,
        minAchievementPercent: values.minAchievementPercent,
        maxAchievementPercent: values.maxAchievementPercent ?? null,
        incentiveAmount: values.incentiveAmount,
      });
      message.success('Slab added');
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  async function handleToggle(id: number) {
    try {
      await toggleIncentiveSlab(id);
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  async function handleDelete(id: number) {
    try {
      await deleteIncentiveSlab(id);
      message.success('Deleted');
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  return (
    <div className="flex flex-col gap-6">
      {error && <Alert type="error" message={error} showIcon />}
      <SlabTable
        title="Disbursement Incentive Slabs"
        helperText="Based on each officer's monthly disbursement-target achievement %."
        slabs={disbursement}
        onAdd={(v) => handleAdd('Disbursement', v)}
        onToggle={handleToggle}
        onDelete={handleDelete}
      />
      <SlabTable
        title="Collection Incentive Slabs"
        helperText="Based on each officer's monthly collection-due achievement % (collected vs. what was due -- there's no separate settable collection target)."
        slabs={collection}
        onAdd={(v) => handleAdd('Collection', v)}
        onToggle={handleToggle}
        onDelete={handleDelete}
      />
    </div>
  );
}
