import { LockOutlined } from '@ant-design/icons';
import { Alert, Button, DatePicker, Modal, Table, message } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import type { Dayjs } from 'dayjs';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchPeriodLock, setPeriodLock, type PeriodLockHistoryRow } from '../../api/periodLock';
import { fdate } from '../../utils/format';

export default function PeriodLockTab() {
  const [currentLockDate, setCurrentLockDate] = useState<string | null>(null);
  const [history, setHistory] = useState<PeriodLockHistoryRow[]>([]);
  const [newLockDate, setNewLockDate] = useState<Dayjs | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  function load() {
    setLoading(true);
    fetchPeriodLock()
      .then((res) => {
        setCurrentLockDate(res.currentLockDate);
        setHistory(res.history);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  function handleSetLock() {
    if (!newLockDate) return;
    const dateStr = newLockDate.format('YYYY-MM-DD');
    Modal.confirm({
      title: `Lock all entries on or before ${fdate(dateStr)}?`,
      content: 'No new entry may be posted or reversed on/before this date until the lock is moved again.',
      onOk: async () => {
        setSaving(true);
        try {
          await setPeriodLock(dateStr);
          message.success('Period locked');
          setNewLockDate(null);
          load();
        } catch (err) {
          message.error(apiErrorMessage(err));
        } finally {
          setSaving(false);
        }
      },
    });
  }

  function handleClearLock() {
    Modal.confirm({
      title: 'Remove the period lock entirely?',
      content: 'Every past date becomes postable again.',
      onOk: async () => {
        setSaving(true);
        try {
          await setPeriodLock(null);
          message.success('Lock removed');
          load();
        } catch (err) {
          message.error(apiErrorMessage(err));
        } finally {
          setSaving(false);
        }
      },
    });
  }

  const columns: ColumnsType<PeriodLockHistoryRow> = [
    { title: 'Locked Up To', dataIndex: 'lockDate', render: (v) => fdate(v) },
    { title: 'Changed By', dataIndex: 'lockedByName', render: (v) => v ?? '-' },
    { title: 'Changed At', dataIndex: 'lockedAt', render: (v) => fdate(v) },
  ];

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-[#9fb3cc]">
        Lock a cut-off date so no one (including you) can accidentally post or reverse an entry dated on or before it -- e.g. once a month/year is
        closed and reviewed.
      </p>

      {error && <Alert type="error" message={error} showIcon />}

      <div className="rounded-sm border border-[#2d4d7a] bg-[#0f2a52] p-5">
        <div className="mb-4">
          <span className="tally-label">Current Lock</span>
          <div className="mt-1 text-lg font-bold">
            {currentLockDate ? `Locked up to ${fdate(currentLockDate)}` : 'No lock set -- every date is postable'}
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <DatePicker value={newLockDate} format="DD MMM YYYY" placeholder="New lock date" onChange={setNewLockDate} />
          <Button type="primary" icon={<LockOutlined />} loading={saving} disabled={!newLockDate} onClick={handleSetLock}>
            Set Lock
          </Button>
          {currentLockDate && (
            <Button danger loading={saving} onClick={handleClearLock}>
              Clear Lock
            </Button>
          )}
        </div>
      </div>

      <div>
        <h3 className="tally-label mb-2">History</h3>
        <Table<PeriodLockHistoryRow> rowKey="lockId" columns={columns} dataSource={history} loading={loading} pagination={false} />
      </div>
    </div>
  );
}
