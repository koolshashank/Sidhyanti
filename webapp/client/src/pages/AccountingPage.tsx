import { useState } from 'react';
import { TallyTheme } from '../components/TallyTheme';
import { AppShell } from '../layouts/AppShell';
import BalanceSheetTab from './accounting/BalanceSheetTab';
import CashBankBookTab from './accounting/CashBankBookTab';
import ChartOfAccountsTab from './accounting/ChartOfAccountsTab';
import DayBookTab from './accounting/DayBookTab';
import GstSummaryTab from './accounting/GstSummaryTab';
import JournalEntriesTab from './accounting/JournalEntriesTab';
import LedgerTab from './accounting/LedgerTab';
import PeriodLockTab from './accounting/PeriodLockTab';
import ProfitLossTab from './accounting/ProfitLossTab';
import TrialBalanceTab from './accounting/TrialBalanceTab';

const GATEWAY_ITEMS = [
  { key: 'day-book', label: 'Day Book', children: <DayBookTab /> },
  { key: 'journal', label: 'Vouchers (Journal Entries)', children: <JournalEntriesTab /> },
  { key: 'ledger', label: 'Ledger', children: <LedgerTab /> },
  { key: 'cash-bank-book', label: 'Cash/Bank Book & Reconciliation', children: <CashBankBookTab /> },
  { key: 'trial-balance', label: 'Trial Balance', children: <TrialBalanceTab /> },
  { key: 'profit-loss', label: 'Profit & Loss A/c', children: <ProfitLossTab /> },
  { key: 'balance-sheet', label: 'Balance Sheet', children: <BalanceSheetTab /> },
  { key: 'gst-summary', label: 'GST Summary', children: <GstSummaryTab /> },
  { key: 'coa', label: 'Chart of Accounts (Masters)', children: <ChartOfAccountsTab /> },
  { key: 'period-lock', label: 'Period Lock (Admin)', children: <PeriodLockTab /> },
];

export default function AccountingPage() {
  const [activeKey, setActiveKey] = useState(GATEWAY_ITEMS[0].key);
  const active = GATEWAY_ITEMS.find((i) => i.key === activeKey) ?? GATEWAY_ITEMS[0];

  return (
    <AppShell title="Accounting">
      <TallyTheme>
      <div className="tally-scope flex min-h-[640px] flex-col overflow-hidden">
        <div className="tally-titlebar">Sidhyanti CRM &middot; Gateway of Tally</div>
        <div className="flex flex-1 flex-col md:flex-row">
          <div className="w-full shrink-0 border-b border-[#2d4d7a] md:w-72 md:border-b-0 md:border-r">
            {GATEWAY_ITEMS.map((item, i) => (
              <div
                key={item.key}
                className={`tally-gateway-item ${activeKey === item.key ? 'active' : ''}`}
                onClick={() => setActiveKey(item.key)}
              >
                <span className="num">{i + 1}.</span>
                <span>{item.label}</span>
              </div>
            ))}
          </div>
          <div className="flex-1 overflow-x-auto p-5">{active.children}</div>
        </div>
        <div className="tally-funcbar">
          <span><b>Esc</b>: Quit</span>
          <span><b>Enter</b>: Select Menu</span>
          <span><b>Alt+C</b>: Create Master</span>
          <span><b>F12</b>: Configure</span>
          <span><b>Ctrl+A</b>: Accept</span>
        </div>
      </div>
      </TallyTheme>
    </AppShell>
  );
}
