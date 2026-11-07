import { Alert, Form, Input, Modal, Select, TimePicker } from 'antd';
import dayjs from 'dayjs';
import { useState } from 'react';
import { shiftCenterMeeting } from '../api/centers';
import { apiErrorMessage } from '../api/client';
import type { CenterRow } from '../api/centers';

const WEEKDAY_OPTIONS = [
  { value: 1, label: 'Monday' },
  { value: 2, label: 'Tuesday' },
  { value: 3, label: 'Wednesday' },
  { value: 4, label: 'Thursday' },
  { value: 5, label: 'Friday' },
  { value: 6, label: 'Saturday' },
  { value: 7, label: 'Sunday' },
];

interface FormShape {
  newDay: number;
  newTime?: dayjs.Dayjs;
  remark?: string;
}

/**
 * Same realignment centers.php's "Shift Meeting" action does -- moving the
 * day/time doesn't just relabel the center, it rolls every still-open
 * loan's upcoming due dates in this center onto the new weekday too, so
 * this is worth a clear confirmation, not a quiet inline edit.
 */
export function ShiftMeetingModal({ center, onClose, onDone }: { center: CenterRow; onClose: () => void; onDone: () => void }) {
  const [form] = Form.useForm<FormShape>();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleOk() {
    const values = await form.validateFields();
    setSubmitting(true);
    setError(null);
    try {
      const result = await shiftCenterMeeting(center.centerId, {
        newDay: values.newDay,
        newTime: values.newTime ? values.newTime.format('h:mm A') : undefined,
        remark: values.remark,
      });
      Modal.success({
        title: 'Meeting shifted',
        content: `${result.shiftedCount} upcoming repayment date(s) were updated to match the new day.`,
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
      title={`Shift Meeting -- ${center.centerName.trim()}`}
      open
      onCancel={onClose}
      onOk={handleOk}
      confirmLoading={submitting}
      okText="Shift Meeting"
    >
      <p className="mb-4 text-sm text-slate-500">
        Currently meets on <strong>{center.meetingDay ? WEEKDAY_OPTIONS.find((d) => String(d.value) === center.meetingDay)?.label : 'an unset day'}</strong>
        {center.meetingTime ? ` at ${center.meetingTime}` : ''}. Every open loan's upcoming due date in this center will move to the new day.
      </p>
      {error && <Alert type="error" message={error} showIcon className="mb-4" />}
      <Form<FormShape> form={form} layout="vertical">
        <Form.Item name="newDay" label="New Meeting Day" rules={[{ required: true, message: 'Required' }]}>
          <Select placeholder="Select Day" options={WEEKDAY_OPTIONS} />
        </Form.Item>
        <Form.Item name="newTime" label="New Meeting Time (optional)">
          <TimePicker use12Hours format="h:mm A" className="w-full" />
        </Form.Item>
        <Form.Item name="remark" label="Remark (optional)">
          <Input.TextArea rows={2} placeholder="Reason for the change" />
        </Form.Item>
      </Form>
    </Modal>
  );
}
