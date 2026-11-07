import { Tabs } from 'antd';
import { AppShell } from '../layouts/AppShell';
import IncentiveReportTab from './incentive/IncentiveReportTab';
import IncentiveSlabsTab from './incentive/IncentiveSlabsTab';

export default function IncentivePage() {
  return (
    <AppShell title="Incentive">
      <Tabs
        defaultActiveKey="report"
        items={[
          { key: 'report', label: 'Incentive Report', children: <IncentiveReportTab /> },
          { key: 'slabs', label: 'Incentive Slabs', children: <IncentiveSlabsTab /> },
        ]}
      />
    </AppShell>
  );
}
