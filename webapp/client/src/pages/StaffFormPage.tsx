import { EnvironmentOutlined, IdcardOutlined, PlusOutlined, SaveOutlined, SearchOutlined, TeamOutlined } from '@ant-design/icons';
import { Alert, Button, Col, DatePicker, Form, Input, Row, Select, Spin, Switch } from 'antd';
import dayjs from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import {
  createStaff,
  fetchStaffDetail,
  fetchStaffLookups,
  updateStaff,
  type StaffFormValues,
  type StaffLookups,
} from '../api/staff';
import { FormCardHeader, FormSectionLabel } from '../components/FormCardHeader';
import { AppShell } from '../layouts/AppShell';

interface FormShape {
  fullName: string;
  username: string;
  password?: string;
  email?: string;
  phone?: string;
  dateOfJoining?: dayjs.Dayjs;
  roleId: number;
  designationId?: number;
  branchId?: number;
  reportingManagerId?: number;
  isActive: boolean;
  pincode?: string;
  city?: string;
  district?: string;
  state?: string;
  address?: string;
}

export default function StaffFormPage() {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const [form] = Form.useForm<FormShape>();

  const [lookups, setLookups] = useState<StaffLookups | null>(null);
  const [loading, setLoading] = useState(isEdit);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pincodeStatus, setPincodeStatus] = useState<{ text: string; ok: boolean } | null>(null);
  const selectedRoleId = Form.useWatch('roleId', form);

  useEffect(() => {
    fetchStaffLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    if (!isEdit) {
      form.setFieldsValue({ isActive: true, dateOfJoining: dayjs() });
      return;
    }
    fetchStaffDetail(Number(id))
      .then((staff) => {
        form.setFieldsValue({
          fullName: staff.fullName.trim(),
          username: staff.username,
          email: staff.email ?? undefined,
          phone: staff.phone ?? undefined,
          dateOfJoining: staff.dateOfJoining ? dayjs(staff.dateOfJoining) : undefined,
          roleId: staff.roleId,
          designationId: staff.designationId ?? undefined,
          branchId: staff.branchId ?? undefined,
          reportingManagerId: staff.reportingManagerId ?? undefined,
          isActive: staff.isActive,
          pincode: staff.pincode ?? undefined,
          city: staff.city ?? undefined,
          district: staff.district ?? undefined,
          state: staff.state ?? undefined,
          address: staff.address ?? undefined,
        });
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const filteredDesignations = useMemo(
    () => (selectedRoleId ? lookups?.designations.filter((d) => d.roleId === selectedRoleId) : lookups?.designations) ?? [],
    [lookups, selectedRoleId],
  );

  async function lookupPincode(code: string) {
    if (!/^\d{6}$/.test(code)) {
      setPincodeStatus(code ? { text: 'Enter a valid 6-digit pincode', ok: false } : null);
      return;
    }
    setPincodeStatus({ text: 'Looking up...', ok: true });
    try {
      const res = await fetch(`https://api.postalpincode.in/pincode/${code}`);
      const data = await res.json();
      const po = data?.[0]?.Status === 'Success' ? data[0].PostOffice[0] : null;
      if (!po) throw new Error('not found');
      form.setFieldsValue({ city: po.Name || po.Block || '', district: po.District || '', state: po.State || '' });
      setPincodeStatus({ text: `${po.District}, ${po.State}`, ok: true });
    } catch {
      setPincodeStatus({ text: 'Pincode not found -- please enter address manually', ok: false });
    }
  }

  async function onFinish(values: FormShape) {
    setSubmitting(true);
    setError(null);
    const payload: StaffFormValues = {
      fullName: values.fullName,
      username: values.username,
      password: values.password || undefined,
      email: values.email,
      phone: values.phone,
      roleId: values.roleId,
      designationId: values.designationId ?? null,
      branchId: values.branchId ?? null,
      reportingManagerId: values.reportingManagerId ?? null,
      dateOfJoining: values.dateOfJoining ? values.dateOfJoining.format('YYYY-MM-DD') : null,
      isActive: values.isActive,
      address: values.address,
      city: values.city,
      pincode: values.pincode,
      district: values.district,
      state: values.state,
    };
    try {
      if (isEdit) {
        await updateStaff(Number(id), payload);
      } else {
        await createStaff(payload);
      }
      navigate('/staff');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AppShell title={isEdit ? 'Edit Employee' : 'Add Employee'}>
      <div className="rounded-2xl border border-slate-100 bg-white p-7 shadow-sm">
        <FormCardHeader
          icon={<IdcardOutlined />}
          title="Employee Details"
          subtitle={isEdit ? "Update this employee's role, assignment and contact details." : 'Fill in the information below to onboard a new employee.'}
        />

        {loading ? (
          <div className="flex h-60 items-center justify-center">
            <Spin size="large" />
          </div>
        ) : (
          <Form<FormShape> form={form} layout="vertical" onFinish={onFinish} disabled={submitting}>
            {error && <Alert type="error" message={error} showIcon className="mb-5" />}

            <FormSectionLabel icon={<IdcardOutlined />} label="Basic Information" />
            <Row gutter={16}>
              <Col span={8}>
                <Form.Item name="fullName" label="Full Name" rules={[{ required: true, message: 'Required' }]}>
                  <Input placeholder="e.g. Pradeep Pandey" />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="username" label="Username" rules={[{ required: true, message: 'Required' }]}>
                  <Input placeholder="e.g. pradeep1051" />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item
                  name="password"
                  label={isEdit ? 'New Password (optional)' : 'Password'}
                  rules={isEdit ? [] : [{ required: true, message: 'Required' }]}
                >
                  <Input.Password placeholder={isEdit ? 'Leave blank to keep current' : 'Set a login password'} />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="email" label="Email">
                  <Input type="email" placeholder="name@sidhyanti.org" />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="phone" label="Phone">
                  <Input placeholder="10-digit number" />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="dateOfJoining" label="Date of Joining">
                  <DatePicker className="w-full" format="DD MMM YYYY" />
                </Form.Item>
              </Col>
            </Row>

            <FormSectionLabel icon={<TeamOutlined />} label="Role & Assignment" />
            <Row gutter={16}>
              <Col span={8}>
                <Form.Item name="roleId" label="Role" rules={[{ required: true, message: 'Required' }]}>
                  <Select
                    placeholder="Select Role"
                    options={lookups?.roles.map((r) => ({ value: r.id, label: r.name }))}
                    onChange={() => form.setFieldValue('designationId', undefined)}
                  />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="designationId" label="Designation">
                  <Select placeholder="Select Designation" allowClear options={filteredDesignations.map((d) => ({ value: d.id, label: d.name }))} />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="branchId" label="Branch">
                  <Select placeholder="Unassigned" allowClear options={lookups?.branches.map((b) => ({ value: b.id, label: b.name }))} />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="reportingManagerId" label="Reporting Manager">
                  <Select placeholder="None" allowClear showSearch optionFilterProp="label" options={lookups?.managers.map((m) => ({ value: m.id, label: m.name }))} />
                </Form.Item>
              </Col>
              <Col span={8} className="flex items-end">
                <Form.Item name="isActive" label="Employee is active" valuePropName="checked">
                  <Switch />
                </Form.Item>
              </Col>
            </Row>

            <FormSectionLabel icon={<EnvironmentOutlined />} label="Address" />
            <Row gutter={16}>
              <Col span={8}>
                <Form.Item name="pincode" label="Pincode">
                  <Input
                    maxLength={6}
                    placeholder="221001"
                    suffix={<SearchOutlined className="cursor-pointer text-slate-400" onClick={() => lookupPincode(form.getFieldValue('pincode') ?? '')} />}
                    onBlur={(e) => lookupPincode(e.target.value)}
                  />
                </Form.Item>
                {pincodeStatus && (
                  <div className={`-mt-3 mb-3 text-xs ${pincodeStatus.ok ? 'text-emerald-600' : 'text-red-500'}`}>{pincodeStatus.text}</div>
                )}
              </Col>
              <Col span={8}>
                <Form.Item name="city" label="City / Area">
                  <Input readOnly className="bg-slate-50" />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="district" label="District">
                  <Input readOnly className="bg-slate-50" />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="state" label="State">
                  <Input readOnly className="bg-slate-50" />
                </Form.Item>
              </Col>
              <Col span={16}>
                <Form.Item name="address" label="Address">
                  <Input placeholder="House no., street, landmark..." />
                </Form.Item>
              </Col>
            </Row>

            <div className="mt-2 flex gap-3 border-t border-slate-100 pt-5">
              <Button
                type="primary"
                htmlType="submit"
                icon={isEdit ? <SaveOutlined /> : <PlusOutlined />}
                loading={submitting}
                style={{ background: '#0b2545' }}
              >
                {isEdit ? 'Save Changes' : 'Create Employee'}
              </Button>
              <Button onClick={() => navigate('/staff')}>Cancel</Button>
            </div>
          </Form>
        )}
      </div>
    </AppShell>
  );
}
