import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import { ProtectedRoute } from './auth/ProtectedRoute';
import AttendanceListPage from './pages/AttendanceListPage';
import BranchFormPage from './pages/BranchFormPage';
import CollectionsListPage from './pages/CollectionsListPage';
import GrtListPage from './pages/GrtListPage';
import CgtListPage from './pages/CgtListPage';
import SettingsPage from './pages/SettingsPage';
import TvrListPage from './pages/TvrListPage';
import DeathEntriesPage from './pages/DeathEntriesPage';
import AuditLogPage from './pages/AuditLogPage';
import IncentivePage from './pages/IncentivePage';
import LoanSchemesListPage from './pages/LoanSchemesListPage';
import LoanSchemeFormPage from './pages/LoanSchemeFormPage';
import BillingPage from './pages/BillingPage';
import LoanProposalFormPage from './pages/LoanProposalFormPage';
import LoanApprovalsPage from './pages/LoanApprovalsPage';
import LoanWriteoffsPage from './pages/LoanWriteoffsPage';
import LoanProductTypesPage from './pages/LoanProductTypesPage';
import PaymentFrequenciesPage from './pages/PaymentFrequenciesPage';
import AccountingPage from './pages/AccountingPage';
import JournalEntryFormPage from './pages/JournalEntryFormPage';
import JournalEntryDetailPage from './pages/JournalEntryDetailPage';
import BranchesListPage from './pages/BranchesListPage';
import CenterFormPage from './pages/CenterFormPage';
import CentersListPage from './pages/CentersListPage';
import DashboardPage from './pages/DashboardPage';
import LoginPage from './pages/LoginPage';
import LoansListPage from './pages/LoansListPage';
import MemberDetailPage from './pages/MemberDetailPage';
import MembersListPage from './pages/MembersListPage';
import StaffFormPage from './pages/StaffFormPage';
import StaffListPage from './pages/StaffListPage';

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <DashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/staff"
            element={
              <ProtectedRoute>
                <StaffListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/staff/new"
            element={
              <ProtectedRoute>
                <StaffFormPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/staff/:id/edit"
            element={
              <ProtectedRoute>
                <StaffFormPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/members"
            element={
              <ProtectedRoute>
                <MembersListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/members/:id"
            element={
              <ProtectedRoute>
                <MemberDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/loans"
            element={
              <ProtectedRoute>
                <LoansListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/branches"
            element={
              <ProtectedRoute>
                <BranchesListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/branches/new"
            element={
              <ProtectedRoute>
                <BranchFormPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/branches/:id/edit"
            element={
              <ProtectedRoute>
                <BranchFormPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/centers"
            element={
              <ProtectedRoute>
                <CentersListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/centers/:id/edit"
            element={
              <ProtectedRoute>
                <CenterFormPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/attendance"
            element={
              <ProtectedRoute>
                <AttendanceListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/collections"
            element={
              <ProtectedRoute>
                <CollectionsListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/grt"
            element={
              <ProtectedRoute>
                <GrtListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/cgt"
            element={
              <ProtectedRoute>
                <CgtListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings"
            element={
              <ProtectedRoute>
                <SettingsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/tvr"
            element={
              <ProtectedRoute>
                <TvrListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/death-entries"
            element={
              <ProtectedRoute>
                <DeathEntriesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/audit-log"
            element={
              <ProtectedRoute>
                <AuditLogPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/incentive"
            element={
              <ProtectedRoute>
                <IncentivePage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/loan-schemes"
            element={
              <ProtectedRoute>
                <LoanSchemesListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/loan-schemes/new"
            element={
              <ProtectedRoute>
                <LoanSchemeFormPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/loan-schemes/:id/edit"
            element={
              <ProtectedRoute>
                <LoanSchemeFormPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/billing"
            element={
              <ProtectedRoute>
                <BillingPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/loans/new"
            element={
              <ProtectedRoute>
                <LoanProposalFormPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/loan-approvals"
            element={
              <ProtectedRoute>
                <LoanApprovalsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/loan-writeoffs"
            element={
              <ProtectedRoute>
                <LoanWriteoffsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/loan-product-types"
            element={
              <ProtectedRoute>
                <LoanProductTypesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/accounting"
            element={
              <ProtectedRoute>
                <AccountingPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/journal-entries/new"
            element={
              <ProtectedRoute>
                <JournalEntryFormPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/journal-entries/:id"
            element={
              <ProtectedRoute>
                <JournalEntryDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="/payment-frequencies"
            element={
              <ProtectedRoute>
                <PaymentFrequenciesPage />
              </ProtectedRoute>
            }
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
