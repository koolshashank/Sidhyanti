import { Alert, Spin } from 'antd';
import { useEffect, useState } from 'react';
import { fetchAmBranchMapping, saveAmBranchMapping, type AmBranchMappingData } from '../../api/settings';
import { apiErrorMessage } from '../../api/client';
import { AccessGrid } from '../../components/AccessGrid';

export default function AmBranchMappingTab() {
  const [data, setData] = useState<AmBranchMappingData | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchAmBranchMapping()
      .then(setData)
      .catch((err) => setError(apiErrorMessage(err)));
  }, []);

  if (error) return <Alert type="error" message={error} showIcon />;
  if (!data) return <Spin />;

  if (data.staff.length === 0) {
    return <Alert type="info" showIcon message="No Area Manager staff found" description="Assign the 'Area Manager' role to a staff member first." />;
  }

  return (
    <div>
      <p className="mb-4 text-sm text-slate-500">
        Map each Area Manager to the branches they cover beyond their own home branch -- used by the mobile app's cross-branch approval, collection
        and TVR screens.
      </p>
      <AccessGrid
        entities={data.staff.map((s) => ({ id: s.staffId, title: s.fullName }))}
        columns={data.branches.map((b) => ({ key: String(b.branchId), label: b.branchName.trim() }))}
        initialMatrix={data.matrix}
        searchPlaceholder="Search area manager..."
        onSave={(pairs) => saveAmBranchMapping(pairs)}
      />
    </div>
  );
}
