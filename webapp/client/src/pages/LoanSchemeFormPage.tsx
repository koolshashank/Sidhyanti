import { BoxPlotOutlined, CalendarOutlined, NumberOutlined, SafetyCertificateOutlined, SaveOutlined, TagOutlined } from '@ant-design/icons';
import { Alert, Button, Col, DatePicker, Form, Input, InputNumber, Row, Select, Spin, Switch } from 'antd';
import dayjs from 'dayjs';
import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import {
  createLoanScheme,
  fetchLoanScheme,
  fetchLoanSchemeLookups,
  updateLoanScheme,
  type LoanSchemeFormValues,
} from '../api/loanSchemes';
import { fetchPaymentFrequencies, type PaymentFrequencyRow } from '../api/paymentFrequencies';
import { FormCardHeader, FormSectionLabel } from '../components/FormCardHeader';
import { AppShell } from '../layouts/AppShell';

interface FormShape {
  schemeCode: string;
  schemeName: string;
  productTypeId?: number;
  paymentFrequency: string;
  interestMethod?: string;
  tenureMonths: number;
  interestRatePercent: number;
  minLoanAmount?: number;
  maxLoanAmount?: number;
  defaultLoanAmount?: number;
  processingFeeAmount?: number;
  processingFeeGstPercent?: number;
  documentChargeAmount?: number;
  documentChargeGstPercent?: number;
  otherChargesAmount?: number;
  otherChargesGstPercent?: number;
  loginFeeAmount?: number;
  loginFeeGstPercent?: number;
  insuranceFeeAmount?: number;
  insurancePeriodMonths?: number;
  sumAssuredClient?: number;
  sumAssuredGuarantor?: number;
  insuredBy?: string;
  numInstallmentsSlot1?: number;
  installmentAmountSlot1?: number;
  numInstallmentsSlot2?: number;
  installmentAmountSlot2?: number;
  numInstallmentsSlot3?: number;
  installmentAmountSlot3?: number;
  effectiveFrom?: dayjs.Dayjs;
  effectiveTo?: dayjs.Dayjs;
  isActive: boolean;
}

export default function LoanSchemeFormPage() {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const [form] = Form.useForm<FormShape>();

  const [productTypes, setProductTypes] = useState<{ id: number; name: string }[]>([]);
  const [frequencies, setFrequencies] = useState<PaymentFrequencyRow[]>([]);
  const [loading, setLoading] = useState(isEdit);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchLoanSchemeLookups().then((res) => setProductTypes(res.productTypes)).catch(() => {});
    fetchPaymentFrequencies().then((res) => setFrequencies(res.items.filter((f) => f.isActive))).catch(() => {});
  }, []);

  useEffect(() => {
    if (!isEdit) {
      form.setFieldsValue({ isActive: true, tenureMonths: 26, interestRatePercent: 24, paymentFrequency: 'Weekly', effectiveFrom: dayjs() });
      return;
    }
    fetchLoanScheme(Number(id))
      .then((s) => {
        form.setFieldsValue({
          schemeCode: s.schemeCode,
          schemeName: s.schemeName,
          productTypeId: s.productTypeId ?? undefined,
          paymentFrequency: s.paymentFrequency,
          interestMethod: s.interestMethod ?? undefined,
          tenureMonths: s.tenureMonths,
          interestRatePercent: s.interestRatePercent,
          minLoanAmount: s.minLoanAmount ?? undefined,
          maxLoanAmount: s.maxLoanAmount ?? undefined,
          defaultLoanAmount: s.defaultLoanAmount ?? undefined,
          processingFeeAmount: s.processingFeeAmount ?? undefined,
          processingFeeGstPercent: s.processingFeeGstPercent ?? undefined,
          documentChargeAmount: s.documentChargeAmount ?? undefined,
          documentChargeGstPercent: s.documentChargeGstPercent ?? undefined,
          otherChargesAmount: s.otherChargesAmount ?? undefined,
          otherChargesGstPercent: s.otherChargesGstPercent ?? undefined,
          loginFeeAmount: s.loginFeeAmount ?? undefined,
          loginFeeGstPercent: s.loginFeeGstPercent ?? undefined,
          insuranceFeeAmount: s.insuranceFeeAmount ?? undefined,
          insurancePeriodMonths: s.insurancePeriodMonths ?? undefined,
          sumAssuredClient: s.sumAssuredClient ?? undefined,
          sumAssuredGuarantor: s.sumAssuredGuarantor ?? undefined,
          insuredBy: s.insuredBy ?? undefined,
          numInstallmentsSlot1: s.numInstallmentsSlot1 ?? undefined,
          installmentAmountSlot1: s.installmentAmountSlot1 ?? undefined,
          numInstallmentsSlot2: s.numInstallmentsSlot2 ?? undefined,
          installmentAmountSlot2: s.installmentAmountSlot2 ?? undefined,
          numInstallmentsSlot3: s.numInstallmentsSlot3 ?? undefined,
          installmentAmountSlot3: s.installmentAmountSlot3 ?? undefined,
          effectiveFrom: s.effectiveFrom ? dayjs(s.effectiveFrom) : undefined,
          effectiveTo: s.effectiveTo ? dayjs(s.effectiveTo) : undefined,
          isActive: s.isActive,
        });
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  async function onFinish(values: FormShape) {
    setSubmitting(true);
    setError(null);
    const payload: LoanSchemeFormValues = {
      ...values,
      productTypeId: values.productTypeId ?? null,
      interestMethod: values.interestMethod ?? null,
      minLoanAmount: values.minLoanAmount ?? null,
      maxLoanAmount: values.maxLoanAmount ?? null,
      defaultLoanAmount: values.defaultLoanAmount ?? null,
      processingFeeAmount: values.processingFeeAmount ?? null,
      processingFeeGstPercent: values.processingFeeGstPercent ?? null,
      documentChargeAmount: values.documentChargeAmount ?? null,
      documentChargeGstPercent: values.documentChargeGstPercent ?? null,
      otherChargesAmount: values.otherChargesAmount ?? null,
      otherChargesGstPercent: values.otherChargesGstPercent ?? null,
      loginFeeAmount: values.loginFeeAmount ?? null,
      loginFeeGstPercent: values.loginFeeGstPercent ?? null,
      insuranceFeeAmount: values.insuranceFeeAmount ?? null,
      insurancePeriodMonths: values.insurancePeriodMonths ?? null,
      sumAssuredClient: values.sumAssuredClient ?? null,
      sumAssuredGuarantor: values.sumAssuredGuarantor ?? null,
      insuredBy: values.insuredBy ?? null,
      numInstallmentsSlot1: values.numInstallmentsSlot1 ?? null,
      installmentAmountSlot1: values.installmentAmountSlot1 ?? null,
      numInstallmentsSlot2: values.numInstallmentsSlot2 ?? null,
      installmentAmountSlot2: values.installmentAmountSlot2 ?? null,
      numInstallmentsSlot3: values.numInstallmentsSlot3 ?? null,
      installmentAmountSlot3: values.installmentAmountSlot3 ?? null,
      effectiveFrom: values.effectiveFrom ? values.effectiveFrom.format('YYYY-MM-DD') : null,
      effectiveTo: values.effectiveTo ? values.effectiveTo.format('YYYY-MM-DD') : null,
    };
    try {
      if (isEdit) await updateLoanScheme(Number(id), payload);
      else await createLoanScheme(payload);
      navigate('/loan-schemes');
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <AppShell title="Loan Scheme">
        <div className="flex justify-center py-20">
          <Spin size="large" />
        </div>
      </AppShell>
    );
  }

  return (
    <AppShell title={isEdit ? 'Edit Loan Scheme' : 'New Loan Scheme'}>
      <div className="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
        <FormCardHeader
          icon={<BoxPlotOutlined />}
          title="Scheme Details"
          subtitle="Configure a lending product that loans can be issued against."
        />

        {error && <Alert type="error" message={error} showIcon className="mb-5" />}

        <Form<FormShape> form={form} layout="vertical" onFinish={onFinish}>
          <FormSectionLabel icon={<TagOutlined />} label="Basic Information" />
          <Row gutter={16}>
            <Col span={8}>
              <Form.Item name="schemeCode" label="Scheme Code" rules={[{ required: true, message: 'Required' }]}>
                <Input placeholder="e.g. SCH-35" />
              </Form.Item>
            </Col>
            <Col span={16}>
              <Form.Item name="schemeName" label="Scheme Name" rules={[{ required: true, message: 'Required' }]}>
                <Input placeholder="e.g. JLG #5" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="productTypeId" label="Product Type">
                <Select placeholder="Select Product Type" allowClear options={productTypes.map((p) => ({ value: p.id, label: p.name }))} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="paymentFrequency" label="Payment Frequency" rules={[{ required: true, message: 'Required' }]}>
                <Select
                  placeholder="Select Payment Frequency"
                  options={frequencies.map((f) => ({ value: f.frequencyName, label: f.frequencyName }))}
                />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="interestMethod" label="Interest Method">
                <Select allowClear placeholder="Not set" options={['Flat', 'Reducing'].map((m) => ({ value: m, label: m }))} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="tenureMonths" label="Tenure (months)" rules={[{ required: true, message: 'Required' }]}>
                <InputNumber min={1} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="interestRatePercent" label="Interest Rate (%)" rules={[{ required: true, message: 'Required' }]}>
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
          </Row>

          <FormSectionLabel icon={<NumberOutlined />} label="Loan Amount" />
          <Row gutter={16}>
            <Col span={8}>
              <Form.Item name="minLoanAmount" label="Min Amount">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="maxLoanAmount" label="Max Amount">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="defaultLoanAmount" label="Default Amount">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
          </Row>

          <FormSectionLabel icon={<NumberOutlined />} label="Fees & Charges" />
          <Row gutter={16}>
            <Col span={6}>
              <Form.Item name="processingFeeAmount" label="Processing Fee (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="processingFeeGstPercent" label="Processing Fee GST (%)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="documentChargeAmount" label="Document Charge (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="documentChargeGstPercent" label="Document Charge GST (%)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="otherChargesAmount" label="Other Charges (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="otherChargesGstPercent" label="Other Charges GST (%)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="loginFeeAmount" label="Login Fee (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="loginFeeGstPercent" label="Login Fee GST (%)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
          </Row>

          <FormSectionLabel icon={<SafetyCertificateOutlined />} label="Insurance" />
          <Row gutter={16}>
            <Col span={6}>
              <Form.Item name="insuranceFeeAmount" label="Insurance Fee (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="insurancePeriodMonths" label="Insurance Period (months)">
                <InputNumber min={0} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="sumAssuredClient" label="Sum Assured -- Client (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={6}>
              <Form.Item name="sumAssuredGuarantor" label="Sum Assured -- Guarantor (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="insuredBy" label="Insured By">
                <Input placeholder="e.g. LIC, HDFC Ergo" />
              </Form.Item>
            </Col>
          </Row>

          <FormSectionLabel icon={<NumberOutlined />} label="Installment Slots (optional -- for step-up EMI schemes)" />
          <Row gutter={16}>
            <Col span={4}>
              <Form.Item name="numInstallmentsSlot1" label="Slot 1 -- # Inst.">
                <InputNumber min={0} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="installmentAmountSlot1" label="Slot 1 -- Amount (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={4}>
              <Form.Item name="numInstallmentsSlot2" label="Slot 2 -- # Inst.">
                <InputNumber min={0} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="installmentAmountSlot2" label="Slot 2 -- Amount (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={4}>
              <Form.Item name="numInstallmentsSlot3" label="Slot 3 -- # Inst.">
                <InputNumber min={0} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="installmentAmountSlot3" label="Slot 3 -- Amount (Rs.)">
                <InputNumber min={0} step={0.01} className="w-full" />
              </Form.Item>
            </Col>
          </Row>

          <FormSectionLabel icon={<CalendarOutlined />} label="Validity & Status" />
          <Row gutter={16}>
            <Col span={8}>
              <Form.Item name="effectiveFrom" label="Effective From">
                <DatePicker className="w-full" format="DD MMM YYYY" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="effectiveTo" label="Effective To">
                <DatePicker className="w-full" format="DD MMM YYYY" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="isActive" label="Scheme is active" valuePropName="checked">
                <Switch />
              </Form.Item>
            </Col>
          </Row>

          <div className="mt-4 flex gap-3 border-t border-slate-100 pt-5">
            <Button type="primary" htmlType="submit" icon={<SaveOutlined />} loading={submitting}>
              {isEdit ? 'Save Changes' : 'Create Scheme'}
            </Button>
            <Button onClick={() => navigate('/loan-schemes')}>Cancel</Button>
          </div>
        </Form>
      </div>
    </AppShell>
  );
}
