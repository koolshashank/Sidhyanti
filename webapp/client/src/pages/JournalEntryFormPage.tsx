import { ArrowLeftOutlined, DeleteOutlined, PlusOutlined, UploadOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, Form, Input, InputNumber, Select, Upload } from 'antd';
import dayjs from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import {
  createJournalEntry,
  fetchJournalEntryLookups,
  uploadJournalEntryAttachment,
  type AccountOption,
  type VoucherType,
} from '../api/journalEntries';
import { TallyTheme } from '../components/TallyTheme';
import { AppShell } from '../layouts/AppShell';
import { money } from '../utils/format';

const VOUCHER_META: Record<VoucherType, { label: string; shortcut: string }> = {
  Payment: { label: 'Payment', shortcut: 'F5' },
  Receipt: { label: 'Receipt', shortcut: 'F6' },
  Contra: { label: 'Contra', shortcut: 'F4' },
  Journal: { label: 'Journal', shortcut: 'F7' },
};

interface LineShape {
  accountId?: number;
  debitAmount?: number;
  creditAmount?: number;
  lineNarration?: string;
}

interface FormShape {
  entryDate: dayjs.Dayjs;
  voucherType: VoucherType;
  narration?: string;
  referenceNo?: string;
  lines: LineShape[];
}

export default function JournalEntryFormPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const initialVoucherType = (searchParams.get('type') as VoucherType | null) ?? 'Journal';
  const [form] = Form.useForm<FormShape>();
  const [accounts, setAccounts] = useState<AccountOption[]>([]);
  const [attachment, setAttachment] = useState<File | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const voucherType: VoucherType = Form.useWatch('voucherType', form) ?? initialVoucherType;

  useEffect(() => {
    fetchJournalEntryLookups()
      .then((res) => setAccounts(res.accounts))
      .catch((err) => setError(apiErrorMessage(err)));
  }, []);

  const lines: LineShape[] = Form.useWatch('lines', form) ?? [];
  const totals = useMemo(() => {
    let debit = 0;
    let credit = 0;
    for (const l of lines) {
      debit += Number(l?.debitAmount) || 0;
      credit += Number(l?.creditAmount) || 0;
    }
    return { debit: Math.round(debit * 100) / 100, credit: Math.round(credit * 100) / 100 };
  }, [lines]);
  const balanced = totals.debit === totals.credit && totals.debit > 0;

  async function onFinish(values: FormShape) {
    setSubmitting(true);
    setError(null);
    try {
      const result = await createJournalEntry({
        entryDate: values.entryDate.format('YYYY-MM-DD'),
        narration: values.narration,
        referenceNo: values.referenceNo,
        voucherType: values.voucherType,
        lines: (values.lines ?? [])
          .filter((l) => l.accountId)
          .map((l) => ({ accountId: l.accountId!, debitAmount: l.debitAmount, creditAmount: l.creditAmount, lineNarration: l.lineNarration })),
      });
      if (attachment) {
        await uploadJournalEntryAttachment(result.journalEntryId, attachment).catch(() => {});
      }
      navigate(`/journal-entries/${result.journalEntryId}`);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <AppShell title="Journal Voucher">
      <TallyTheme>
      <div className="tally-scope overflow-x-auto">
        <div className="tally-titlebar flex items-center gap-3">
          <button type="button" onClick={() => navigate('/accounting')} className="flex items-center gap-1 text-[#f5c518] hover:text-white">
            <ArrowLeftOutlined /> Back
          </button>
          <span>&middot; Accounting Voucher Creation &middot; {VOUCHER_META[voucherType].label} ({VOUCHER_META[voucherType].shortcut})</span>
        </div>

        {error && (
          <div className="p-4 pb-0">
            <Alert type="error" message={error} showIcon />
          </div>
        )}

        <Form<FormShape>
          form={form}
          layout="vertical"
          onFinish={onFinish}
          initialValues={{ entryDate: dayjs(), voucherType: initialVoucherType, lines: [{}, {}] }}
          className="p-5"
        >
          <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
            <Form.Item name="voucherType" label={<span className="tally-label">Voucher Type</span>} rules={[{ required: true, message: 'Required' }]}>
              <Select
                options={(Object.keys(VOUCHER_META) as VoucherType[]).map((t) => ({ value: t, label: `${VOUCHER_META[t].label} (${VOUCHER_META[t].shortcut})` }))}
              />
            </Form.Item>
            <Form.Item name="entryDate" label={<span className="tally-label">Date</span>} rules={[{ required: true, message: 'Required' }]}>
              <DatePicker className="w-full" format="DD-MMM-YYYY" />
            </Form.Item>
            <Form.Item name="referenceNo" label={<span className="tally-label">Reference No.</span>}>
              <Input placeholder="voucher/bill no." />
            </Form.Item>
            <Form.Item name="narration" label={<span className="tally-label">Narration</span>}>
              <Input placeholder="What is this entry for?" />
            </Form.Item>
          </div>

          <Form.Item label={<span className="tally-label">Attach Bill / Receipt (optional)</span>}>
            <Upload
              beforeUpload={(file) => {
                setAttachment(file);
                return false;
              }}
              onRemove={() => setAttachment(null)}
              maxCount={1}
              accept="image/jpeg,image/png,image/webp,application/pdf"
            >
              <Button icon={<UploadOutlined />}>Choose File</Button>
            </Upload>
          </Form.Item>

          <div className="min-w-[760px]">
          <div className="mb-2 grid grid-cols-12 gap-3 border-b border-[#2d4d7a] pb-2 text-[11px] font-bold uppercase tracking-wide text-[#9fb3cc]">
            <div className="col-span-5">Particulars (Account)</div>
            <div className="col-span-2">Debit</div>
            <div className="col-span-2">Credit</div>
            <div className="col-span-2">Narration</div>
            <div className="col-span-1"></div>
          </div>

          <Form.List name="lines">
            {(fields, { add, remove }) => (
              <div className="flex flex-col gap-2">
                {fields.map((field) => (
                  <div key={field.key} className="grid grid-cols-12 items-center gap-3 border-b border-[#1d3a66] py-2">
                    <div className="col-span-5">
                      <Form.Item name={[field.name, 'accountId']} noStyle rules={[{ required: true, message: 'Account required' }]}>
                        <Select
                          showSearch
                          className="w-full"
                          placeholder="To / By Account"
                          optionFilterProp="label"
                          options={accounts.map((a) => ({ value: a.id, label: `${a.code} - ${a.name}` }))}
                        />
                      </Form.Item>
                    </div>
                    <div className="col-span-2">
                      <Form.Item name={[field.name, 'debitAmount']} noStyle>
                        <InputNumber className="w-full" min={0} placeholder="Dr." />
                      </Form.Item>
                    </div>
                    <div className="col-span-2">
                      <Form.Item name={[field.name, 'creditAmount']} noStyle>
                        <InputNumber className="w-full" min={0} placeholder="Cr." />
                      </Form.Item>
                    </div>
                    <div className="col-span-2">
                      <Form.Item name={[field.name, 'lineNarration']} noStyle>
                        <Input placeholder="note" />
                      </Form.Item>
                    </div>
                    <div className="col-span-1 flex justify-end">
                      <Button danger type="text" icon={<DeleteOutlined />} onClick={() => remove(field.name)} disabled={fields.length <= 2} />
                    </div>
                  </div>
                ))}
                <Button icon={<PlusOutlined />} onClick={() => add({})} className="mt-2 w-fit">
                  Add Row
                </Button>
              </div>
            )}
          </Form.List>
          </div>

          <div className="mt-5 flex items-center justify-end gap-10 border-t-2 border-[#f5c518] pt-3">
            <div className="tally-amount text-sm">
              <span className="tally-label mr-2">Total Dr.</span>
              <span className="font-bold text-[#e8edf5]">{money(totals.debit)}</span>
            </div>
            <div className="tally-amount text-sm">
              <span className="tally-label mr-2">Total Cr.</span>
              <span className="font-bold text-[#e8edf5]">{money(totals.credit)}</span>
            </div>
            <div className={`text-sm font-bold ${balanced ? 'text-emerald-400' : 'text-red-400'}`}>{balanced ? '✓ Balanced' : '✗ Not Balanced'}</div>
          </div>

          <div className="mt-5 flex gap-3">
            <Button type="primary" htmlType="submit" loading={submitting} disabled={!balanced} style={{ background: '#f5c518', borderColor: '#f5c518', color: '#0a1f3d', fontWeight: 700 }}>
              Accept (Ctrl+A)
            </Button>
            <Button onClick={() => navigate('/accounting')} ghost>
              Quit (Esc)
            </Button>
          </div>
        </Form>

        <div className="tally-funcbar">
          <span><b>Ctrl+A</b>: Accept</span>
          <span><b>Esc</b>: Quit</span>
          <span><b>Alt+D</b>: Delete Row</span>
          <span><b>Alt+I</b>: Insert Row</span>
        </div>
      </div>
      </TallyTheme>
    </AppShell>
  );
}
