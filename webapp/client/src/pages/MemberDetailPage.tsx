import {
  BankOutlined,
  CalendarOutlined,
  CheckCircleFilled,
  CheckCircleOutlined,
  CheckSquareOutlined,
  ClockCircleOutlined,
  DollarCircleOutlined,
  EnvironmentOutlined,
  FileAddOutlined,
  FileImageOutlined,
  FileTextOutlined,
  HomeOutlined,
  IdcardOutlined,
  PhoneOutlined,
  SwapOutlined,
  TeamOutlined,
  UserAddOutlined,
  WalletOutlined,
} from '@ant-design/icons';
import { Alert, Empty, Image, Segmented, Spin, Table, Tabs, Tag, Timeline } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { apiErrorMessage } from '../api/client';
import { fetchMemberDetail, type CoBorrowerDetail, type MemberDetail, type MemberJourneyEvent, type MemberLoanRow } from '../api/members';
import { AppShell } from '../layouts/AppShell';
import { fdate, money, weekdayName } from '../utils/format';

const JOURNEY_ICONS: Record<string, React.ReactNode> = {
  'Lead Created': <UserAddOutlined />,
  'Stage Change': <SwapOutlined />,
  Interaction: <PhoneOutlined />,
  Note: <FileTextOutlined />,
  'KYC Document': <FileImageOutlined />,
  'Loan Applied': <FileAddOutlined />,
  'Loan Stage Change': <SwapOutlined />,
  GRT: <TeamOutlined />,
  TVR: <PhoneOutlined />,
  Disbursement: <WalletOutlined />,
  Payment: <DollarCircleOutlined />,
  'Task Created': <CheckSquareOutlined />,
  'Task Completed': <CheckCircleOutlined />,
};

const JOURNEY_COLORS: Record<string, string> = {
  'Lead Created': 'blue',
  'Stage Change': 'gold',
  Interaction: 'cyan',
  Note: 'gray',
  'KYC Document': 'purple',
  'Loan Applied': 'blue',
  'Loan Stage Change': 'blue',
  GRT: 'green',
  TVR: 'cyan',
  Disbursement: 'green',
  Payment: 'green',
  'Task Created': 'gray',
  'Task Completed': 'green',
};

const STAGE_COLORS: Record<string, string> = {
  'New Lead': 'gold',
  Contacted: 'blue',
  'KYC Pending': 'orange',
  Verified: 'cyan',
  Converted: 'green',
  Dropped: 'red',
};

const LOAN_STAGE_COLORS: Record<string, string> = {
  Applied: 'default',
  'GRT Pending': 'orange',
  Approved: 'blue',
  Disbursed: 'purple',
  Active: 'green',
  Closed: 'default',
  Rejected: 'red',
  Preclosed: 'purple',
};

export default function MemberDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [detail, setDetail] = useState<MemberDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchMemberDetail(Number(id))
      .then(setDetail)
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setLoading(false));
  }, [id]);

  return (
    <AppShell title="Member Profile">
      {loading ? (
        <div className="flex h-96 items-center justify-center">
          <Spin size="large" />
        </div>
      ) : error ? (
        <Alert type="error" showIcon message={error} />
      ) : detail ? (
        <MemberDetailContent detail={detail} onBack={() => navigate('/members')} />
      ) : (
        <Empty />
      )}
    </AppShell>
  );
}

function MemberDetailContent({ detail }: { detail: MemberDetail; onBack: () => void }) {
  const { profile, photoUrl, loans, kycDocuments, bankDetails, journey, coBorrowers, center } = detail;
  const fullName = `${profile.FirstName} ${profile.MiddleName ?? ''} ${profile.LastName}`.replace(/\s+/g, ' ').trim();

  const totalLoanAmount = loans.reduce((s, l) => s + (l.loanAmountSanctioned ?? l.loanAmountApplied ?? 0), 0);
  const activeLoanCount = loans.filter((l) => l.stageName === 'Active' || l.stageName === 'Disbursed').length;

  return (
    <div className="flex flex-col gap-6">
      {/* Hero header */}
      <div className="overflow-hidden rounded-2xl shadow-sm">
        <div className="relative px-6 pb-16 pt-8" style={{ background: 'linear-gradient(135deg, #0b2545 0%, #13315c 60%, #0d3b66 100%)' }}>
          <div
            className="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full opacity-20"
            style={{ background: 'radial-gradient(circle, #e8b95a 0%, transparent 70%)' }}
          />
          <div className="relative flex flex-wrap items-center gap-3">
            <Tag color={STAGE_COLORS[profile.CurrentStage ?? ''] ?? 'default'}>{profile.CurrentStage ?? '-'}</Tag>
            {!profile.IsActive && <Tag color="default">Inactive</Tag>}
            <span className="text-xs font-semibold uppercase tracking-widest text-[#e8b95a]">{profile.EnrollmentNo}</span>
          </div>
          <h2 className="relative mt-2 text-2xl font-bold text-white">{fullName}</h2>
          <div className="relative mt-3 flex flex-wrap gap-x-6 gap-y-1.5 text-sm text-white/70">
            <span className="flex items-center gap-1.5">
              <PhoneOutlined /> {profile.MobileNo ?? '-'}
            </span>
            <span className="flex items-center gap-1.5">
              <EnvironmentOutlined /> {profile.BranchName ?? '-'} / {profile.CenterName ?? '-'}
            </span>
            <span className="flex items-center gap-1.5">
              <IdcardOutlined /> {profile.AssignedOfficer?.trim() || 'Unassigned'}
            </span>
          </div>
        </div>

        {/* Stat strip overlapping the banner, photo on the left */}
        <div className="relative -mt-10 flex items-end gap-5 bg-white px-6 pb-5 pt-0">
          {photoUrl ? (
            <Image
              src={photoUrl}
              alt={fullName}
              width={84}
              height={84}
              className="-mt-2 rounded-2xl border-4 border-white object-cover shadow-md"
              style={{ objectFit: 'cover' }}
              fallback="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4OCIgaGVpZ2h0PSI4OCI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2U1ZTdlYiIvPjwvc3ZnPg=="
            />
          ) : (
            <div className="-mt-2 flex h-[84px] w-[84px] items-center justify-center rounded-2xl border-4 border-white bg-slate-100 text-2xl font-bold text-slate-400 shadow-md">
              {profile.FirstName.charAt(0)}
            </div>
          )}
          <div className="grid flex-1 grid-cols-2 gap-4 pt-3 sm:grid-cols-4">
            <StatPill label="Total Loans" value={String(loans.length)} />
            <StatPill label="Active Loans" value={String(activeLoanCount)} />
            <StatPill label="Disbursed Value" value={money(totalLoanAmount)} />
            <StatPill label="KYC Documents" value={String(kycDocuments.length)} />
          </div>
        </div>
      </div>

      <Tabs
        defaultActiveKey="overview"
        items={[
          {
            key: 'overview',
            label: 'Overview',
            children: <OverviewTab profile={profile} coBorrowers={coBorrowers} bankDetails={bankDetails} center={center} />,
          },
          {
            key: 'loans',
            label: `Loans (${loans.length})`,
            children: <LoansTab loans={loans} />,
          },
          {
            key: 'documents',
            label: `Documents (${kycDocuments.length})`,
            children: <DocumentsTab kycDocuments={kycDocuments} />,
          },
          {
            key: 'activity',
            label: `Activity (${journey.length})`,
            children: <ActivityTab journey={journey} />,
          },
        ]}
      />
    </div>
  );
}

function StatPill({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-lg font-bold text-slate-800">{value}</div>
      <div className="text-[11px] uppercase tracking-wide text-slate-400">{label}</div>
    </div>
  );
}

function OverviewTab({
  profile,
  coBorrowers,
  bankDetails,
  center,
}: {
  profile: MemberDetail['profile'];
  coBorrowers: CoBorrowerDetail[];
  bankDetails: MemberDetail['bankDetails'];
  center: MemberDetail['center'];
}) {
  const fullName = `${profile.FirstName} ${profile.MiddleName ?? ''} ${profile.LastName}`.replace(/\s+/g, ' ').trim();
  const [personTab, setPersonTab] = useState<string>('member');
  const activeCoBorrower = coBorrowers.find((cb) => `co-${cb.coBorrowerId}` === personTab);

  return (
    <div className="flex flex-col gap-6">
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Personal Information -- Member / Co-Borrower toggle */}
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm lg:col-span-2">
          <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
            <SectionTitle icon={<IdcardOutlined />} title="Profile" className="!mb-0" />
            {coBorrowers.length > 0 && (
              <Segmented
                value={personTab}
                onChange={(v) => setPersonTab(v as string)}
                options={[
                  { label: 'Member', value: 'member' },
                  ...coBorrowers.map((cb, i) => ({
                    label: coBorrowers.length > 1 ? `Co-Borrower ${i + 1}` : 'Co-Borrower',
                    value: `co-${cb.coBorrowerId}`,
                  })),
                ]}
              />
            )}
          </div>

          {personTab === 'member' || !activeCoBorrower ? (
            <>
              <div className="mb-1 text-sm font-bold text-slate-800">{fullName}</div>
              <div className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                <Field label="Father / Husband" value={profile.FatherHusbandName} />
                <Field label="Gender" value={profile.Gender} />
                <Field label="Date of Birth" value={profile.DOB ? fdate(profile.DOB) : null} />
                <Field label="Marital Status" value={profile.MaritalStatus} />
                <Field label="Occupation" value={profile.OccupationName} />
                <Field label="Lead Source" value={profile.LeadSource} />
                <Field label="Aadhar No." value={profile.AadharNo} />
                <Field label="PAN No." value={profile.PANNo} />
                <Field label="Joined" value={fdate(profile.CreatedAt)} />
              </div>

              <SectionTitle icon={<EnvironmentOutlined />} title="Address" className="mt-6" />
              <p className="text-sm text-slate-600">
                {[profile.Address, profile.VillageCity, profile.DistrictName, profile.StateName].filter(Boolean).join(', ')}
                {profile.Pincode ? ` - ${profile.Pincode}` : ''}
              </p>
            </>
          ) : (
            <CoBorrowerFields coBorrower={activeCoBorrower} />
          )}
        </div>

        {/* Bank Details */}
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <SectionTitle icon={<BankOutlined />} title="Bank Details" />
          {bankDetails ? (
            <div className="flex flex-col gap-3">
              <Field label="Bank Name" value={bankDetails.bankName} />
              <Field label="Account Number" value={bankDetails.accountNumber} />
              <Field label="IFSC Code" value={bankDetails.ifscCode} />
              <Field label="Branch" value={bankDetails.bankBranch} />
              <Field label="Beneficiary" value={bankDetails.beneficiaryName} />
            </div>
          ) : (
            <p className="text-sm text-slate-400">No bank details on file.</p>
          )}
        </div>
      </div>

      {/* Center Info */}
      {center && (
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
          <SectionTitle icon={<HomeOutlined />} title="Center Info" />
          <div className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
            <Field label="Center Code" value={center.centerCode} />
            <Field label="Meeting Day" value={weekdayName(center.meetingDay)} />
            <Field label="Meeting Time" value={center.meetingTime} />
            <Field label="Place" value={center.place} />
            <Field label="Contact Person" value={center.contactPerson} />
            <Field label="Contact No." value={center.contactNo} />
          </div>
        </div>
      )}
    </div>
  );
}

function LoansTab({ loans }: { loans: MemberLoanRow[] }) {
  const loanColumns: ColumnsType<MemberLoanRow> = [
    { title: 'Loan No.', dataIndex: 'loanNo', render: (v) => <span className="font-semibold text-slate-700">{v}</span> },
    { title: 'Amount', key: 'amount', render: (_, r) => money(r.loanAmountSanctioned ?? r.loanAmountApplied ?? 0) },
    {
      title: 'Stage',
      dataIndex: 'stageName',
      render: (v: string | null) => <Tag color={LOAN_STAGE_COLORS[v ?? ''] ?? 'default'}>{v ?? '-'}</Tag>,
    },
    { title: 'Applied', dataIndex: 'applicationDate', render: (v) => fdate(v) },
    { title: 'Disbursed', dataIndex: 'disbursementDate', render: (v) => (v ? fdate(v) : '-') },
  ];

  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
      {loans.length === 0 ? (
        <p className="text-sm text-slate-400">No loans yet.</p>
      ) : (
        <Table<MemberLoanRow> rowKey="loanId" columns={loanColumns} dataSource={loans} pagination={false} size="small" />
      )}
    </div>
  );
}

function DocumentsTab({ kycDocuments }: { kycDocuments: MemberDetail['kycDocuments'] }) {
  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
      {kycDocuments.length === 0 ? (
        <p className="text-sm text-slate-400">No documents uploaded.</p>
      ) : (
        <div className="grid grid-cols-[repeat(auto-fill,minmax(96px,1fr))] gap-3">
          {kycDocuments.map((doc) => (
            <div key={doc.kycDocId} className="overflow-hidden rounded-lg border border-slate-100">
              <div className="aspect-square overflow-hidden">
                <Image src={doc.docFilePath} alt={doc.docTypeName} className="h-full w-full object-cover" />
              </div>
              <div className="p-1.5">
                <div className="truncate text-[10px] font-semibold text-slate-700" title={doc.docTypeName}>
                  {doc.docTypeName}
                </div>
                <div className="mt-0.5 flex items-center justify-between">
                  <span className="flex items-center gap-1 text-[9px] text-slate-400">
                    <CalendarOutlined /> {fdate(doc.uploadedAt)}
                  </span>
                  {doc.isVerified && <CheckCircleFilled className="text-[10px] text-emerald-500" />}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function ActivityTab({ journey }: { journey: MemberJourneyEvent[] }) {
  return (
    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
      {journey.length === 0 ? (
        <p className="text-sm text-slate-400">No activity recorded yet.</p>
      ) : (
        <div className="max-h-[640px] overflow-y-auto pr-2 pt-2">
          <Timeline
            items={journey.map((event, i) => ({
              key: i,
              color: journeyColor(event),
              dot: <span className="text-sm">{JOURNEY_ICONS[event.eventType] ?? <ClockCircleOutlined />}</span>,
              children: <JourneyItem event={event} />,
            }))}
          />
        </div>
      )}
    </div>
  );
}

function journeyColor(event: MemberJourneyEvent): string {
  if (event.eventType === 'GRT') return event.eventTitle.includes('Failed') ? 'red' : 'green';
  if (event.eventType === 'Stage Change' || event.eventType === 'Loan Stage Change') {
    return event.eventTitle.includes('moved back') ? 'red' : (JOURNEY_COLORS[event.eventType] ?? 'blue');
  }
  return JOURNEY_COLORS[event.eventType] ?? 'blue';
}

function JourneyItem({ event }: { event: MemberJourneyEvent }) {
  return (
    <div className="pb-1">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-sm font-semibold text-slate-800">{event.eventTitle}</span>
        <Tag color="default" className="!m-0 !text-[10px]">
          {event.eventType}
        </Tag>
        {event.relatedLoanNo && (
          <Tag color="geekblue" className="!m-0 !text-[10px]">
            {event.relatedLoanNo}
          </Tag>
        )}
      </div>
      {event.eventDetail && <div className="mt-0.5 text-xs text-slate-500">{event.eventDetail}</div>}
      <div className="mt-1 text-[11px] text-slate-400">
        {fdate(event.eventDate)}
        {event.performedBy ? ` · ${event.performedBy.trim()}` : ''}
      </div>
    </div>
  );
}

function CoBorrowerFields({ coBorrower }: { coBorrower: CoBorrowerDetail }) {
  const name = `${coBorrower.firstName} ${coBorrower.middleName ?? ''} ${coBorrower.lastName}`.replace(/\s+/g, ' ').trim();
  return (
    <>
      <div className="mb-1 text-sm font-bold text-slate-800">{name || '-'}</div>
      <div className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
        <Field label="Relation to Member" value={coBorrower.relationToMember} />
        <Field label="Mobile" value={coBorrower.mobileNo} />
        <Field label="Date of Birth" value={coBorrower.dob ? fdate(coBorrower.dob) : null} />
        <Field label="Gender" value={coBorrower.gender} />
        <Field label="Marital Status" value={coBorrower.maritalStatus} />
        <Field label="Aadhar No." value={coBorrower.aadharNo} />
        <Field label="PAN No." value={coBorrower.panNo} />
        <Field label="Voter ID" value={coBorrower.voterId} />
      </div>

      <SectionTitle icon={<EnvironmentOutlined />} title="Address" className="mt-6" />
      <p className="text-sm text-slate-600">
        {[coBorrower.address, coBorrower.villageCity].filter(Boolean).join(', ')}
        {coBorrower.pincode ? ` - ${coBorrower.pincode}` : ''}
      </p>
    </>
  );
}

function SectionTitle({ icon, title, className = '' }: { icon: React.ReactNode; title: string; className?: string }) {
  return (
    <div className={`mb-4 flex items-center gap-2 ${className}`}>
      <span className="flex h-6 w-6 items-center justify-center rounded-md bg-amber-50 text-xs text-[#c8952c]">{icon}</span>
      <span className="text-xs font-bold uppercase tracking-wider text-[#0b2545]">{title}</span>
    </div>
  );
}

function Field({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <div className="text-[11px] uppercase tracking-wide text-slate-400">{label}</div>
      <div className="text-sm font-medium text-slate-700">{value || '-'}</div>
    </div>
  );
}
