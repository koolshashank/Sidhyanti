import { BankOutlined, EnvironmentOutlined, PhoneOutlined, PlusOutlined, SaveOutlined } from '@ant-design/icons';
import { Alert, Button, Col, DatePicker, Form, Input, Row, Select, Spin, Switch } from 'antd';
import dayjs from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import {
  createBranch,
  fetchBranchDetail,
  fetchBranchLookups,
  updateBranch,
  type BranchFormValues,
  type BranchLookups,
} from '../api/branches';
import { FormCardHeader, FormSectionLabel } from '../components/FormCardHeader';
import { AppShell } from '../layouts/AppShell';

interface FormShape {
  branchCode: string;
  branchName: string;
  address?: string;
  stateId?: number;
  districtId?: number;
  pincode?: string;
  phone?: string;
  email?: string;
  dateOpened?: dayjs.Dayjs;
  isActive: boolean;
}

export default function BranchFormPage() {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const [form] = Form.useForm<FormShape>();

  const [lookups, setLookups] = useState<BranchLookups | null>(null);
  const [loading, setLoading] = useState(isEdit);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const selectedStateId = Form.useWatch('stateId', form);

  useEffect(() => {
    fetchBranchLookups().then(setLookups).catch(() => {});
  }, []);

  useEffect(() => {
    if (!isEdit) {
      form.setFieldsValue({ isActive: true, dateOpened: dayjs() });
      return;
    }
    fetchBranchDetail(Number(id))
      .then((b) => {
        form.setFieldsValue({
          branchCode: b.branchCode,
          branchName: b.branchName.trim(),
          address: b.address ?? undefined,
          stateId: b.stateId ?? undefined,
          districtId: b.districtId ?? undefined,
          pincode: b.pincode ?? undefined,
          phone: b.phone ?? undefined,
          email: b.email ?? undefined,
          dateOpened: b.dateOpened ? dayjs(b.dateOpened) : undefined,
          isActive: b.isActive,
        });
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  const filteredDistricts = useMemo(
    () => (selectedStateId ? lookups?.districts.filter((d) => d.stateId === selectedStateId) : lookups?.districts) ?? [],
    [lookups, selectedStateId],
  );

  async function onFinish(values: FormShape) {
    setSubmitting(true);
    setError(null);
    const payload: BranchFormValues = {
      branchCode: values.branchCode,
      branchName: values.branchName,
      address: values.address,
      stateId: values.stateId ?? null,
      districtId: values.districtId ?? null,
      pincode: values.pincode,
      phone: values.phone,
      email: values.email,
      dateOpened: values.dateOpened ? values.dateOpened.format('YYYY-MM-DD') : null,
      isActive: values.isActive,
    };
    try {
      if (isEdit) {
        await updateBranch(Number(id), payload);
      } else {
        await createBranch(payload);
      }
      navigate('/branches');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AppShell title={isEdit ? 'Edit Branch' : 'New Branch'}>
      <div className="rounded-2xl border border-slate-100 bg-white p-7 shadow-sm">
        <FormCardHeader
          icon={<BankOutlined />}
          title="Branch Details"
          subtitle={isEdit ? "Update this branch office's information below." : 'Fill in the information below to open a new branch office.'}
        />

        {loading ? (
          <div className="flex h-60 items-center justify-center">
            <Spin size="large" />
          </div>
        ) : (
          <Form<FormShape> form={form} layout="vertical" onFinish={onFinish} disabled={submitting}>
            {error && <Alert type="error" message={error} showIcon className="mb-5" />}

            <FormSectionLabel icon={<BankOutlined />} label="Basic Information" />
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item name="branchName" label="Branch Name" rules={[{ required: true, message: 'Required' }]}>
                  <Input placeholder="e.g. Kashi Vidyapeeth" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="branchCode" label="Branch Code" rules={[{ required: true, message: 'Required' }]}>
                  <Input placeholder="e.g. SIDH-6" />
                </Form.Item>
              </Col>
              <Col span={24}>
                <Form.Item name="address" label="Address">
                  <Input placeholder="Street, locality" />
                </Form.Item>
              </Col>
            </Row>

            <FormSectionLabel icon={<EnvironmentOutlined />} label="Location" />
            <Row gutter={16}>
              <Col span={8}>
                <Form.Item name="stateId" label="State">
                  <Select
                    placeholder="Select State"
                    allowClear
                    options={lookups?.states.map((s) => ({ value: s.id, label: s.name }))}
                    onChange={() => form.setFieldValue('districtId', undefined)}
                  />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="districtId" label="District">
                  <Select placeholder="Select District" allowClear options={filteredDistricts.map((d) => ({ value: d.id, label: d.name }))} />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="pincode" label="Pincode">
                  <Input maxLength={6} placeholder="221001" />
                </Form.Item>
              </Col>
            </Row>

            <FormSectionLabel icon={<PhoneOutlined />} label="Contact & Status" />
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item name="phone" label="Phone">
                  <Input placeholder="10-digit number" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="email" label="Email">
                  <Input type="email" placeholder="branch@sidhyanti.org" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="dateOpened" label="Date Opened">
                  <DatePicker className="w-full" format="DD MMM YYYY" />
                </Form.Item>
              </Col>
              <Col span={12} className="flex items-end">
                <Form.Item name="isActive" label="Branch is active" valuePropName="checked">
                  <Switch />
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
                {isEdit ? 'Save Changes' : 'Create Branch'}
              </Button>
              <Button onClick={() => navigate('/branches')}>Cancel</Button>
            </div>
          </Form>
        )}
      </div>
    </AppShell>
  );
}
