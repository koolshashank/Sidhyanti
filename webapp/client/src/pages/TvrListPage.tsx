import { Tabs } from 'antd';
import { AppShell } from '../layouts/AppShell';
import TvrPendingTab from './tvr/TvrPendingTab';
import TvrRecordsTab from './tvr/TvrRecordsTab';

export default function TvrListPage() {
  return (
    <AppShell title="TVR (Telephonic Verification)">
      <Tabs
        defaultActiveKey="records"
        items={[
          { key: 'records', label: 'TVR Records', children: <TvrRecordsTab /> },
          { key: 'pending', label: 'Pending TVRs', children: <TvrPendingTab /> },
        ]}
      />
    </AppShell>
  );
}
