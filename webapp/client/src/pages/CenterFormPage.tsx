import { EnvironmentOutlined, HomeOutlined, SaveOutlined, UserOutlined } from '@ant-design/icons';
import { Alert, Button, Col, DatePicker, Form, Input, Row, Select, Spin, Switch } from 'antd';
import dayjs from 'dayjs';
import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { fetchCenterDetail, fetchCenterLookups, updateCenter, type CenterFormValues, type CenterLookups } from '../api/centers';
import { apiErrorMessage } from '../api/client';
import { FormCardHeader, FormSectionLabel } from '../components/FormCardHeader';
import { AppShell } from '../layouts/AppShell';

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
  centerCode: string;
  centerName: string;
  branchId: number;
  address?: string;
  centerPlace?: string;
  meetingDay?: number;
  meetingTime?: string;
  managedByStaffId?: number;
  contactPerson?: string;
  contactNo?: string;
  latitude?: string;
  longitude?: string;
  isActive: boolean;
  dissolvedDate?: dayjs.Dayjs;
}

export default function CenterFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [form] = Form.useForm<FormShape>();

  const [lookups, setLookups] = useState<CenterLookups | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchCenterLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    fetchCenterDetail(Number(id))
      .then((c) => {
        form.setFieldsValue({
          centerCode: c.centerCode,
          centerName: c.centerName.trim(),
          branchId: c.branchId,
          address: c.address ?? undefined,
          centerPlace: c.centerPlace ?? undefined,
          meetingDay: c.meetingDay != null ? Number(c.meetingDay) : undefined,
          meetingTime: c.meetingTime ?? undefined,
          managedByStaffId: c.managedByStaffId ?? undefined,
          contactPerson: c.contactPerson ?? undefined,
          contactNo: c.contactNo ?? undefined,
          latitude: c.latitude ?? undefined,
          longitude: c.longitude ?? undefined,
          isActive: c.isActive,
          dissolvedDate: c.dissolvedDate ? dayjs(c.dissolvedDate) : undefined,
        });
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  async function onFinish(values: FormShape) {
    setSubmitting(true);
    setError(null);
    const payload: CenterFormValues = {
      centerCode: values.centerCode,
      centerName: values.centerName,
      branchId: values.branchId,
      address: values.address,
      centerPlace: values.centerPlace,
      meetingDay: values.meetingDay ?? null,
      meetingTime: values.meetingTime,
      managedByStaffId: values.managedByStaffId ?? null,
      contactPerson: values.contactPerson,
      contactNo: values.contactNo,
      latitude: values.latitude,
      longitude: values.longitude,
      isActive: values.isActive,
      dissolvedDate: values.dissolvedDate ? values.dissolvedDate.format('YYYY-MM-DD') : null,
    };
    try {
      await updateCenter(Number(id), payload);
      navigate('/centers');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AppShell title="Edit Center">
      <div className="rounded-2xl border border-slate-100 bg-white p-7 shadow-sm">
        <FormCardHeader icon={<HomeOutlined />} title="Center Details" subtitle="Update this center's meeting schedule, contact and status." />

        {loading ? (
          <div className="flex h-60 items-center justify-center">
            <Spin size="large" />
          </div>
        ) : (
          <Form<FormShape> form={form} layout="vertical" onFinish={onFinish} disabled={submitting}>
            {error && <Alert type="error" message={error} showIcon className="mb-5" />}

            <FormSectionLabel icon={<HomeOutlined />} label="Basic Information" />
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item name="centerName" label="Center Name" rules={[{ required: true, message: 'Required' }]}>
                  <Input />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="centerCode" label="Center Code" rules={[{ required: true, message: 'Required' }]}>
                  <Input />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="branchId" label="Branch" rules={[{ required: true, message: 'Required' }]}>
                  <Select placeholder="Select Branch" options={lookups?.branches.map((b) => ({ value: b.id, label: b.name }))} />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="managedByStaffId" label="Managed By">
                  <Select
                    placeholder="Unassigned"
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    options={lookups?.staff.map((s) => ({ value: s.id, label: s.name }))}
                  />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="centerPlace" label="Place / Area">
                  <Input placeholder="e.g. Shivo Rajbhar" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="address" label="Address">
                  <Input placeholder="Street, locality" />
                </Form.Item>
              </Col>
            </Row>

            <FormSectionLabel icon={<EnvironmentOutlined />} label="Meeting Schedule & Location" />
            <Row gutter={16}>
              <Col span={8}>
                <Form.Item name="meetingDay" label="Meeting Day">
                  <Select placeholder="Select Day" allowClear options={WEEKDAY_OPTIONS} />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="meetingTime" label="Meeting Time">
                  <Input placeholder="e.g. 10:00 AM" />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="dissolvedDate" label="Dissolved Date">
                  <DatePicker className="w-full" format="DD MMM YYYY" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="latitude" label="Latitude">
                  <Input placeholder="25.398490" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="longitude" label="Longitude">
                  <Input placeholder="83.027746" />
                </Form.Item>
              </Col>
            </Row>

            <FormSectionLabel icon={<UserOutlined />} label="Contact & Status" />
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item name="contactPerson" label="Contact Person">
                  <Input />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="contactNo" label="Contact No.">
                  <Input placeholder="10-digit number" />
                </Form.Item>
              </Col>
              <Col span={24}>
                <Form.Item name="isActive" label="Center is active" valuePropName="checked">
                  <Switch />
                </Form.Item>
              </Col>
            </Row>

            <div className="mt-2 flex gap-3 border-t border-slate-100 pt-5">
              <Button type="primary" htmlType="submit" icon={<SaveOutlined />} loading={submitting} style={{ background: '#0b2545' }}>
                Save Changes
              </Button>
              <Button onClick={() => navigate('/centers')}>Cancel</Button>
            </div>
          </Form>
        )}
      </div>
    </AppShell>
  );
}
