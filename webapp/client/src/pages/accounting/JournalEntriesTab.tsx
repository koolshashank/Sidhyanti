import { PlusOutlined, SearchOutlined, UndoOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, Input, Modal, Table, Tag, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import type { Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiErrorMessage } from '../../api/client';
import { fetchJournalEntries, reverseJournalEntry, type JournalEntryRow, type VoucherType } from '../../api/journalEntries';
import { fdate, money } from '../../utils/format';

const { RangePicker } = DatePicker;

const VOUCHER_SHORTCUTS: { type: VoucherType; shortcut: string }[] = [
  { type: 'Payment', shortcut: 'F5' },
  { type: 'Receipt', shortcut: 'F6' },
  { type: 'Contra', shortcut: 'F4' },
  { type: 'Journal', shortcut: 'F7' },
];

export default function JournalEntriesTab() {
  const navigate = useNavigate();
  const [items, setItems] = useState<JournalEntryRow[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const [q, setQ] = useState('');
  const [dateRange, setDateRange] = useState<[Dayjs, Dayjs] | null>(null);
  const dateFrom = dateRange ? dateRange[0].format('YYYY-MM-DD') : undefined;
  const dateTo = dateRange ? dateRange[1].format('YYYY-MM-DD') : undefined;

  function load() {
    setLoading(true);
    setError(null);
    fetchJournalEntries({ page, perPage, q: q || undefined, dateFrom, dateTo })
      .then((res) => {
        setItems(res.items);
        setTotal(res.total);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, [page, q, dateFrom, dateTo]);

  function handleReverse(row: JournalEntryRow) {
    Modal.confirm({
      title: `Reverse entry ${row.entryNo}?`,
      content: 'This posts a new equal-and-opposite entry dated today -- the original stays in the ledger, marked as reversed.',
      onOk: async () => {
        try {
          await reverseJournalEntry(row.journalEntryId);
          message.success('Reversal posted');
          load();
        } catch (err) {
          message.error(apiErrorMessage(err));
        }
      },
    });
  }

  const columns: ColumnsType<JournalEntryRow> = [
    { title: 'Entry No', dataIndex: 'entryNo', render: (v, row) => <a onClick={() => navigate(`/journal-entries/${row.journalEntryId}`)}>{v}</a> },
    { title: 'Date', dataIndex: 'entryDate', render: (v) => fdate(v) },
    { title: 'Type', dataIndex: 'voucherType', render: (v) => <Tag color="gold">{v}</Tag> },
    { title: 'Narration', dataIndex: 'narration', render: (v) => v ?? '-' },
    { title: 'Amount', dataIndex: 'totalAmount', align: 'right', render: (v: number) => money(v) },
    { title: 'Created By', dataIndex: 'createdByName', render: (v) => v ?? '-' },
    {
      title: 'Status',
      key: 'status',
      render: (_, row) =>
        row.isReversal ? (
          <Tag color="purple">Reversal</Tag>
        ) : row.isReversed ? (
          <Tag color="default">Reversed</Tag>
        ) : (
          <Tag color="success">Posted</Tag>
        ),
    },
    {
      title: '',
      key: 'actions',
      render: (_, row) =>
        !row.isReversed && !row.isReversal ? (
          <Button size="small" icon={<UndoOutlined />} onClick={() => handleReverse(row)}>
            Reverse
          </Button>
        ) : null,
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-3">
        <Input
          placeholder="Entry no. or narration..."
          prefix={<SearchOutlined className="text-[#9fb3cc]" />}
          style={{ width: 240 }}
          value={q}
          onChange={(e) => {
            setPage(1);
            setQ(e.target.value);
          }}
          allowClear
        />
        <RangePicker
          value={dateRange ?? undefined}
          format="DD MMM YYYY"
          allowClear
          onChange={(values) => {
            setPage(1);
            setDateRange(values && values[0] && values[1] ? [values[0], values[1]] : null);
          }}
        />
        <div className="flex gap-2" style={{ marginLeft: 'auto' }}>
          {VOUCHER_SHORTCUTS.map((v) => (
            <Button key={v.type} icon={<PlusOutlined />} onClick={() => navigate(`/journal-entries/new?type=${v.type}`)}>
              {v.type} ({v.shortcut})
            </Button>
          ))}
        </div>
      </div>

      {error && <Alert type="error" message={error} showIcon />}

      <Table<JournalEntryRow>
        rowKey="journalEntryId"
        columns={columns}
        dataSource={items}
        loading={loading}
        pagination={{
          current: page,
          pageSize: perPage,
          total,
          onChange: (p, ps) => {
            setPage(p);
            setPerPage(ps);
          },
          showTotal: (t) => `${t} entries`,
        }}
      />
    </div>
  );
}
