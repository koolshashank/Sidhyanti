import { SaveOutlined } from '@ant-design/icons';
import { Alert, Button, Form, Input, InputNumber, Spin, message } from 'antd';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchKfs, saveKfs, type KfsProfile } from '../../api/settings';

export default function KfsSettingsTab() {
  const [form] = Form.useForm<KfsProfile>();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchKfs()
      .then((profile) => form.setFieldsValue(profile))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleSave() {
    const values = await form.validateFields();
    setSaving(true);
    try {
      await saveKfs(values);
      message.success('Saved');
    } catch (err) {
      message.error(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  if (error) return <Alert type="error" message={error} showIcon />;
  if (loading) return <Spin />;

  return (
    <div className="max-w-2xl rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
      <p className="mb-4 text-sm text-slate-500">
        NBFC company profile printed on every loan's Key Fact Sheet (KFS) -- the regulatory disclosure document.
      </p>
      <Form<KfsProfile> form={form} layout="vertical">
        <Form.Item name="companyName" label="Company Name" rules={[{ required: true, message: 'Required' }]}>
          <Input />
        </Form.Item>
        <Form.Item name="nbfcRegistrationNo" label="NBFC Registration No. / CIN" rules={[{ required: true, message: 'Required' }]}>
          <Input />
        </Form.Item>
        <Form.Item name="registeredOfficeAddress" label="Registered Office Address" rules={[{ required: true, message: 'Required' }]}>
          <Input.TextArea rows={2} />
        </Form.Item>
        <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
          <Form.Item name="customerCarePhone" label="Customer Care Phone" rules={[{ required: true, message: 'Required' }]}>
            <Input />
          </Form.Item>
          <Form.Item name="customerCareEmail" label="Customer Care Email" rules={[{ required: true, message: 'Required' }]}>
            <Input />
          </Form.Item>
          <Form.Item name="grievanceOfficerName" label="Grievance Redressal Officer Name" rules={[{ required: true, message: 'Required' }]}>
            <Input />
          </Form.Item>
          <Form.Item name="grievanceOfficerPhone" label="Grievance Officer Phone" rules={[{ required: true, message: 'Required' }]}>
            <Input />
          </Form.Item>
        </div>
        <Form.Item name="coolingOffPeriodDays" label="Cooling-off / Look-up Period (days)" rules={[{ required: true, message: 'Required' }]}>
          <InputNumber min={0} className="w-full" />
        </Form.Item>
      </Form>
      <Button type="primary" icon={<SaveOutlined />} loading={saving} onClick={handleSave}>
        Save
      </Button>
    </div>
  );
}
