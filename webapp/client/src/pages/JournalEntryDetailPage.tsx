import { ArrowLeftOutlined, FileTextOutlined, PrinterOutlined, UndoOutlined, UploadOutlined } from '@ant-design/icons';
import { Alert, Button, Checkbox, Modal, Spin, Table, Tag, Upload, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import {
  fetchJournalEntry,
  reconcileLine,
  reverseJournalEntry,
  uploadJournalEntryAttachment,
  type JournalEntryDetail,
  type JournalEntryLine,
} from '../api/journalEntries';
import { TallyTheme } from '../components/TallyTheme';
import { AppShell } from '../layouts/AppShell';
import { fdate, money } from '../utils/format';

export default function JournalEntryDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [entry, setEntry] = useState<JournalEntryDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function load() {
    setLoading(true);
    fetchJournalEntry(Number(id))
      .then(setEntry)
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, [id]);

  function handleReverse() {
    if (!entry) return;
    Modal.confirm({
      title: `Reverse entry ${entry.entryNo}?`,
      content: 'This posts a new equal-and-opposite entry dated today -- the original stays in the ledger, marked as reversed.',
      onOk: async () => {
        try {
          const result = await reverseJournalEntry(entry.journalEntryId);
          message.success('Reversal posted');
          navigate(`/journal-entries/${result.journalEntryId}`);
        } catch (err) {
          message.error(apiErrorMessage(err));
        }
      },
    });
  }

  async function handleToggleReconcile(line: JournalEntryLine) {
    try {
      await reconcileLine(line.lineId, !line.isReconciled);
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
  }

  async function handleUploadAttachment(file: File) {
    if (!entry) return false;
    try {
      await uploadJournalEntryAttachment(entry.journalEntryId, file);
      message.success('Attachment uploaded');
      load();
    } catch (err) {
      message.error(apiErrorMessage(err));
    }
    return false;
  }

  const columns: ColumnsType<JournalEntryLine> = [
    { title: 'Particulars', key: 'account', render: (_, l) => `${l.accountCode} - ${l.accountName}` },
    { title: 'Debit', dataIndex: 'debitAmount', align: 'right', render: (v: number) => (v > 0 ? money(v) : '-') },
    { title: 'Credit', dataIndex: 'creditAmount', align: 'right', render: (v: number) => (v > 0 ? money(v) : '-') },
    { title: 'Note', dataIndex: 'lineNarration', render: (v) => v ?? '-' },
    {
      title: 'Reconciled',
      key: 'reconciled',
      render: (_, l) =>
        l.isCashBankAccount ? (
          <Checkbox checked={l.isReconciled} onChange={() => handleToggleReconcile(l)}>
            {l.isReconciled ? fdate(l.reconciledDate) : 'Unticked'}
          </Checkbox>
        ) : (
          '-'
        ),
    },
  ];

  if (loading) {
    return (
      <AppShell title="Journal Voucher">
        <div className="flex justify-center py-20">
          <Spin size="large" />
        </div>
      </AppShell>
    );
  }
  if (error || !entry) {
    return (
      <AppShell title="Journal Voucher">
        <Button icon={<ArrowLeftOutlined />} onClick={() => navigate('/accounting')} className="mb-4">
          Back
        </Button>
        <Alert type="error" message={error ?? 'Not found'} showIcon />
      </AppShell>
    );
  }

  const totalDebit = entry.lines.reduce((a, l) => a + l.debitAmount, 0);
  const totalCredit = entry.lines.reduce((a, l) => a + l.creditAmount, 0);

  return (
    <AppShell title={`Journal Voucher ${entry.entryNo}`}>
      <TallyTheme>
      <div className="tally-scope overflow-x-auto">
        <div className="tally-titlebar flex items-center gap-3">
          <button type="button" onClick={() => navigate('/accounting')} className="flex items-center gap-1 text-[#f5c518] hover:text-white">
            <ArrowLeftOutlined /> Back
          </button>
          <span>&middot; Journal Voucher &middot; {entry.entryNo}</span>
        </div>

        <div className="p-5">
          <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
              <div className="tally-label">Date</div>
              <div className="font-bold">{fdate(entry.entryDate)}</div>
              <div className="mt-1 text-xs text-[#9fb3cc]">Voucher Type: {entry.voucherType}</div>
              {entry.referenceNo && (
                <div className="mt-1 text-xs text-[#9fb3cc]">Ref: {entry.referenceNo}</div>
              )}
              <div className="mt-1 text-xs text-[#9fb3cc]">Entered by {entry.createdByName ?? '-'}</div>
              {entry.narration && <p className="mt-2 text-sm">{entry.narration}</p>}
            </div>
            <div className="flex flex-wrap items-center gap-2 print:hidden">
              {entry.isReversal && <Tag color="purple">Reversal</Tag>}
              {entry.isReversed && <Tag color="default">Reversed</Tag>}
              {entry.voucherFilePath ? (
                <a href={entry.voucherFilePath} target="_blank" rel="noopener noreferrer">
                  <Button icon={<FileTextOutlined />}>View Attachment</Button>
                </a>
              ) : (
                <Upload beforeUpload={handleUploadAttachment} showUploadList={false} accept="image/jpeg,image/png,image/webp,application/pdf">
                  <Button icon={<UploadOutlined />}>Attach Bill</Button>
                </Upload>
              )}
              <Button icon={<PrinterOutlined />} onClick={() => window.print()}>
                Print
              </Button>
              {!entry.isReversal && !entry.isReversed && (
                <Button icon={<UndoOutlined />} onClick={handleReverse}>
                  Reverse
                </Button>
              )}
            </div>
          </div>

          <Table<JournalEntryLine>
            rowKey="lineId"
            columns={columns}
            dataSource={entry.lines}
            pagination={false}
            summary={() => (
              <Table.Summary.Row>
                <Table.Summary.Cell index={0}>
                  <strong>Total</strong>
                </Table.Summary.Cell>
                <Table.Summary.Cell index={1} align="right">
                  <strong>{money(totalDebit)}</strong>
                </Table.Summary.Cell>
                <Table.Summary.Cell index={2} align="right">
                  <strong>{money(totalCredit)}</strong>
                </Table.Summary.Cell>
                <Table.Summary.Cell index={3} />
              </Table.Summary.Row>
            )}
          />

          <Button className="mt-4 print:hidden" onClick={() => navigate('/accounting')} ghost>
            Quit (Esc)
          </Button>
        </div>
      </div>
      </TallyTheme>
    </AppShell>
  );
}
