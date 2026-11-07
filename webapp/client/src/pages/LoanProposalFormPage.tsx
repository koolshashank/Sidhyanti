import { BankOutlined, DollarCircleOutlined, SafetyCertificateOutlined, SaveOutlined, UserOutlined } from '@ant-design/icons';
import { Alert, Button, Col, Form, Input, InputNumber, Modal, Row, Select, Spin, message } from 'antd';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { fetchMemberList } from '../api/members';
import { apiErrorMessage } from '../api/client';
import {
  createLoanProposal,
  fetchLoanProposalLookups,
  fetchMemberLoanContext,
  type LoanSchemeOption,
  type LookupOption,
  type MemberLoanContext,
} from '../api/loanProposals';
import { FormCardHeader, FormSectionLabel } from '../components/FormCardHeader';
import { AppShell } from '../layouts/AppShell';
import { money } from '../utils/format';

interface MemberOption {
  value: number;
  label: string;
}

interface FormShape {
  memberId: number;
  schemeId: number;
  amount?: number;
  purposeId?: number;
  emiAmount?: number;
  selfIncome?: number;
  husbandSonIncome?: number;
  otherIncome?: number;
  residentIncome?: number;
  houseExpenses?: number;
  mfiExpenses?: number;
  businessExpenses?: number;
  sourceOfIncome?: string;
  ifscCode?: string;
  bankName?: string;
  bankBranch?: string;
  bankAddress?: string;
  accountNumber?: string;
  beneficiaryName?: string;
}

export default function LoanProposalFormPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const [form] = Form.useForm<FormShape>();

  const [schemes, setSchemes] = useState<LoanSchemeOption[]>([]);
  const [purposes, setPurposes] = useState<LookupOption[]>([]);
  const [memberOptions, setMemberOptions] = useState<MemberOption[]>([]);
  const [memberSearching, setMemberSearching] = useState(false);
  const [context, setContext] = useState<MemberLoanContext | null>(null);
  const [contextLoading, setContextLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const schemeId = Form.useWatch('schemeId', form);
  const selectedScheme = useMemo(() => schemes.find((s) => s.id === schemeId), [schemes, schemeId]);

  useEffect(() => {
    fetchLoanProposalLookups()
      .then((res) => {
        setSchemes(res.schemes);
        setPurposes(res.purposes);
      })
      .catch((err) => setError(apiErrorMessage(err)));
  }, []);

  const preselectMemberId = searchParams.get('memberId');
  useEffect(() => {
    if (preselectMemberId) {
      const id = Number(preselectMemberId);
      form.setFieldsValue({ memberId: id });
      handleMemberSelect(id, `Member #${id}`);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [preselectMemberId]);

  function searchMembers(q: string) {
    if (searchTimer.current) clearTimeout(searchTimer.current);
    if (!q || q.length < 2) {
      setMemberOptions([]);
      return;
    }
    searchTimer.current = setTimeout(async () => {
      setMemberSearching(true);
      try {
        const res = await fetchMemberList({ q, page: 1, perPage: 15 });
        setMemberOptions(res.items.map((m) => ({ value: m.memberId, label: `${m.enrollmentNo} - ${m.firstName} ${m.lastName}` })));
      } finally {
        setMemberSearching(false);
      }
    }, 300);
  }

  async function handleMemberSelect(memberId: number, label?: string) {
    if (label) setMemberOptions((prev) => (prev.find((o) => o.value === memberId) ? prev : [...prev, { value: memberId, label }]));
    setContextLoading(true);
    setContext(null);
    try {
      const ctx = await fetchMemberLoanContext(memberId);
      setContext(ctx);
      if (ctx.bankDetails) {
        form.setFieldsValue({
          bankName: ctx.bankDetails.bankName ?? undefined,
          ifscCode: ctx.bankDetails.ifscCode ?? undefined,
          bankBranch: ctx.bankDetails.bankBranch ?? undefined,
          bankAddress: ctx.bankDetails.bankAddress ?? undefined,
          accountNumber: ctx.bankDetails.accountNumber ?? undefined,
          beneficiaryName: ctx.bankDetails.beneficiaryName ?? undefined,
        });
      }
      if (ctx.isDeceased) {
        Modal.error({
          title: 'Member Deceased',
          content: 'This member has an approved death record on file. A new loan cannot be proposed for a deceased member.',
        });
      } else if (ctx.openLoanNo) {
        Modal.warning({
          title: 'Open Loan Exists',
          content: `This member already has an open loan (${ctx.openLoanNo}). It must be closed, preclosed, or rejected before a new loan can be proposed.`,
        });
      }
    } catch (err) {
      message.error(apiErrorMessage(err));
    } finally {
      setContextLoading(false);
    }
  }

  function onSchemeChange(id: number) {
    const scheme = schemes.find((s) => s.id === id);
    if (scheme && !context?.reloanEligibility) {
      form.setFieldsValue({ amount: undefined });
    }
    if (scheme?.emiAmount) form.setFieldsValue({ emiAmount: scheme.emiAmount });
  }

  const blocked = Boolean(context?.isDeceased || context?.openLoanNo);

  async function onFinish(values: FormShape) {
    setSubmitting(true);
    setError(null);
    try {
      const result = await createLoanProposal(values);
      message.success('Loan proposal saved');
      navigate(`/members/${values.memberId}`);
      void result;
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AppShell title="New Loan Proposal">
      <div className="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
        <FormCardHeader icon={<DollarCircleOutlined />} title="Loan Proposal" subtitle="Propose a new loan for a verified/converted member." />

        {error && <Alert type="error" message={error} showIcon className="mb-5" />}

        <Form<FormShape> form={form} layout="vertical" onFinish={onFinish}>
          <FormSectionLabel icon={<UserOutlined />} label="Member & Product" />
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="memberId" label="Member" rules={[{ required: true, message: 'Required' }]}>
                <Select
                  showSearch
                  placeholder="Search by name, mobile or enrollment no..."
                  filterOption={false}
                  notFoundContent={memberSearching ? <Spin size="small" /> : null}
                  onSearch={searchMembers}
                  onChange={(id) => handleMemberSelect(id)}
                  options={memberOptions}
                />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="schemeId" label="Product" rules={[{ required: true, message: 'Required' }]}>
                <Select placeholder="Select Product" onChange={onSchemeChange} options={schemes.map((s) => ({ value: s.id, label: s.name }))} />
              </Form.Item>
            </Col>
          </Row>

          {contextLoading && <Spin className="mb-4" />}

          {context?.reloanEligibility && (
            <Alert
              type="info"
              showIcon
              className="mb-4"
              message="Reloan Eligibility"
              description={
                <div className="text-sm">
                  Previous loan {context.reloanEligibility.previousLoanNo} ({money(context.reloanEligibility.previousLoanAmount)}), cycle{' '}
                  {context.reloanEligibility.cycleNumber}, {context.reloanEligibility.latePayments} late payment(s) -- eligible for{' '}
                  <strong>{money(context.reloanEligibility.eligibleAmount)}</strong> ({context.reloanEligibility.ruleName}, +
                  {context.reloanEligibility.increasePercent}%).
                </div>
              }
            />
          )}

          {selectedScheme && (
            <Row gutter={16}>
              <Col span={8}>
                <Form.Item
                  name="amount"
                  label={context?.reloanEligibility ? 'Amount (up to eligible amount)' : 'Amount (scheme default)'}
                  tooltip={!context?.reloanEligibility ? 'A custom amount is only allowed for a reloan' : undefined}
                >
                  <InputNumber
                    className="w-full"
                    min={1}
                    max={context?.reloanEligibility?.eligibleAmount}
                    disabled={!context?.reloanEligibility}
                    placeholder={String(selectedScheme.defaultAmount)}
                  />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="purposeId" label="Loan Purpose">
                  <Select allowClear placeholder="Select Purpose" options={purposes.map((p) => ({ value: p.id, label: p.name }))} />
                </Form.Item>
              </Col>
              <Col span={8}>
                <Form.Item name="emiAmount" label="EMI Amount">
                  <InputNumber className="w-full" min={0} />
                </Form.Item>
              </Col>
              <Col span={24}>
                <p className="-mt-2 text-xs text-slate-400">
                  Tenure {selectedScheme.tenureMonths} months · Interest {selectedScheme.interestRatePercent}%
                </p>
              </Col>
            </Row>
          )}

          <FormSectionLabel icon={<SafetyCertificateOutlined />} label="Income Assessment (optional)" />
          <Row gutter={16}>
            <Col span={8}>
              <Form.Item name="selfIncome" label="Self Income (monthly)">
                <InputNumber className="w-full" min={0} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="husbandSonIncome" label="Husband/Son Income (monthly)">
                <InputNumber className="w-full" min={0} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="otherIncome" label="Other Income (monthly)">
                <InputNumber className="w-full" min={0} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="residentIncome" label="Resident Income (monthly)">
                <InputNumber className="w-full" min={0} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="houseExpenses" label="House Expenses (monthly)">
                <InputNumber className="w-full" min={0} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="mfiExpenses" label="MFI Expenses (monthly)">
                <InputNumber className="w-full" min={0} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="businessExpenses" label="Business Expenses (monthly)">
                <InputNumber className="w-full" min={0} />
              </Form.Item>
            </Col>
            <Col span={16}>
              <Form.Item name="sourceOfIncome" label="Source of Income">
                <Input placeholder="e.g. Tailoring, Dairy, Vegetable vending" />
              </Form.Item>
            </Col>
          </Row>

          <FormSectionLabel icon={<BankOutlined />} label="Bank Details (optional)" />
          <Row gutter={16}>
            <Col span={8}>
              <Form.Item name="bankName" label="Bank Name">
                <Input />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="ifscCode" label="IFSC Code">
                <Input />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="bankBranch" label="Bank Branch">
                <Input />
              </Form.Item>
            </Col>
            <Col span={16}>
              <Form.Item name="bankAddress" label="Bank Address">
                <Input />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item name="accountNumber" label="Account Number">
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="beneficiaryName" label="Beneficiary Name">
                <Input />
              </Form.Item>
            </Col>
          </Row>

          <div className="mt-4 flex gap-3 border-t border-slate-100 pt-5">
            <Button type="primary" htmlType="submit" icon={<SaveOutlined />} loading={submitting} disabled={blocked}>
              Submit Proposal
            </Button>
            <Button onClick={() => navigate('/loans')}>Cancel</Button>
          </div>
        </Form>
      </div>
    </AppShell>
  );
}
