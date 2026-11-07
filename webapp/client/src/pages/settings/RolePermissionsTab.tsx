import { Alert, Spin } from 'antd';
import { useEffect, useState } from 'react';
import { fetchPermissions, savePermissions, type PermissionsData } from '../../api/settings';
import { apiErrorMessage } from '../../api/client';
import { AccessGrid } from '../../components/AccessGrid';

export default function RolePermissionsTab() {
  const [data, setData] = useState<PermissionsData | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchPermissions()
      .then(setData)
      .catch((err) => setError(apiErrorMessage(err)));
  }, []);

  if (error) return <Alert type="error" message={error} showIcon />;
  if (!data) return <Spin />;

  return (
    <div>
      <p className="mb-4 text-sm text-slate-500">
        Toggle which sensitive actions each role is allowed to perform. A role with no permission checked for an action cannot perform it --
        enforced identically by the web CRM and the mobile app. <strong>SuperAdmin</strong> always has every permission regardless of this grid.
      </p>
      <AccessGrid
        entities={data.roles.map((r) => ({ id: r.roleId, title: r.roleName }))}
        columns={data.permissions.map((p) => ({ key: p.key, label: p.label }))}
        initialMatrix={data.matrix}
        searchPlaceholder="Search role..."
        onSave={(pairs) => savePermissions(pairs)}
      />
    </div>
  );
}
