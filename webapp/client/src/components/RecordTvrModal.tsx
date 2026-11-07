import { Alert, Form, Input, Modal, Select } from 'antd';
import { DatePicker } from 'antd';
import dayjs, { type Dayjs } from 'dayjs';
import { useState } from 'react';
import { apiErrorMessage } from '../api/client';
import { recordTvr, type LookupOption, type TvrPendingRow } from '../api/tvr';

interface FormShape {
  contactPersonName: string;
  tvrDate: Dayjs;
  tvrStatusId: number;
  verificationNotes?: string;
}

/** Same 4-field capture the mobile app's TVR bottom sheet uses -- contact person, date, status, notes. */
export function RecordTvrModal({
  loan,
  statuses,
  onClose,
  onDone,
}: {
  loan: TvrPendingRow;
  statuses: LookupOption[];
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
      await recordTvr({
        loanId: loan.loanId,
        contactPersonName: values.contactPersonName,
        tvrDate: values.tvrDate.format('YYYY-MM-DD'),
        tvrStatusId: values.tvrStatusId,
        verificationNotes: values.verificationNotes,
      });
      Modal.success({ title: 'TVR recorded', content: `Verification saved for loan ${loan.loanNo}.` });
      onDone();
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal title={`Conduct TVR -- ${loan.loanNo}`} open onCancel={onClose} onOk={handleOk} confirmLoading={submitting} okText="Save TVR">
      <p className="mb-4 text-sm text-slate-500">
        {loan.clientName} ({loan.mobileNo ?? 'no mobile'}) · {loan.centerName ?? '-'}, {loan.branchName ?? '-'}
      </p>
      {error && <Alert type="error" message={error} showIcon className="mb-4" />}
      <Form<FormShape> form={form} layout="vertical" initialValues={{ tvrDate: dayjs() }}>
        <Form.Item name="contactPersonName" label="Contact Person Name" rules={[{ required: true, message: 'Required' }]}>
          <Input placeholder="Who was spoken to" />
        </Form.Item>
        <Form.Item name="tvrDate" label="TVR Date" rules={[{ required: true, message: 'Required' }]}>
          <DatePicker className="w-full" format="DD MMM YYYY" />
        </Form.Item>
        <Form.Item name="tvrStatusId" label="Status" rules={[{ required: true, message: 'Required' }]}>
          <Select placeholder="Select outcome" options={statuses.map((s) => ({ value: s.id, label: s.name }))} />
        </Form.Item>
        <Form.Item name="verificationNotes" label="Verification Notes (optional)">
          <Input.TextArea rows={3} placeholder="Any notes from the call" />
        </Form.Item>
      </Form>
    </Modal>
  );
}
