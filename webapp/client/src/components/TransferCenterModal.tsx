import { Alert, Form, Input, Modal, Select } from 'antd';
import { useState } from 'react';
import type { CenterRow, LookupOption } from '../api/centers';
import { transferCenter } from '../api/centers';
import { apiErrorMessage } from '../api/client';

interface FormShape {
  newStaffId: number;
  remark?: string;
}

/**
 * Same reassignment centers.php's "Transfer Center" action does -- every
 * member in this center, every one of their still-open loans, and today's
 * open collection sheet rows move to the new (same-branch) employee along
 * with the center itself. The staff dropdown is pre-filtered to the
 * center's own branch, same constraint the PHP side enforces server-side.
 */
export function TransferCenterModal({
  center,
  branchStaff,
  onClose,
  onDone,
}: {
  center: CenterRow;
  branchStaff: LookupOption[];
  onClose: () => void;
  onDone: () => void;
}) {
  const [form] = Form.useForm<FormShape>();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleOk() {
    const values = await form.validateFields();
    setSubmitting(true);
    setError(null);
    try {
      const result = await transferCenter(center.centerId, { newStaffId: values.newStaffId, remark: values.remark });
      Modal.success({
        title: 'Center transferred',
        content: `${result.membersMoved} member(s) and ${result.loansMoved} active loan(s) moved with it.`,
      });
      onDone();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal
      title={`Transfer Center -- ${center.centerName.trim()}`}
      open
      onCancel={onClose}
      onOk={handleOk}
      confirmLoading={submitting}
      okText="Transfer Center"
      okButtonProps={{ danger: true }}
    >
      <p className="mb-4 text-sm text-slate-500">
        Currently managed by <strong>{center.managerName?.trim() || 'Unassigned'}</strong>. This moves the center, its {center.memberCount} member(s), and
        their active loans to the new employee.
      </p>
      {error && <Alert type="error" message={error} showIcon className="mb-4" />}
      <Form<FormShape> form={form} layout="vertical">
        <Form.Item name="newStaffId" label="New Employee" rules={[{ required: true, message: 'Required' }]}>
          <Select placeholder="Select an employee from this branch" showSearch optionFilterProp="label" options={branchStaff.map((s) => ({ value: s.id, label: s.name }))} />
        </Form.Item>
        <Form.Item name="remark" label="Remark (optional)">
          <Input.TextArea rows={2} placeholder="Reason for the transfer" />
        </Form.Item>
      </Form>
    </Modal>
  );
}
