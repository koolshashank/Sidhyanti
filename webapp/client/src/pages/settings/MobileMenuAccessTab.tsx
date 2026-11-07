import { Alert, Spin } from 'antd';
import { useEffect, useState } from 'react';
import { fetchMobileMenu, saveMobileMenu, type MobileMenuData } from '../../api/settings';
import { apiErrorMessage } from '../../api/client';
import { AccessGrid } from '../../components/AccessGrid';

export default function MobileMenuAccessTab() {
  const [data, setData] = useState<MobileMenuData | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchMobileMenu()
      .then(setData)
      .catch((err) => setError(apiErrorMessage(err)));
  }, []);

  if (error) return <Alert type="error" message={error} showIcon />;
  if (!data) return <Spin />;

  return (
    <div>
      <p className="mb-4 text-sm text-slate-500">
        Control which mobile-app screens each Designation can see. A designation with no menu item checked sees nothing (deny-by-default).
      </p>
      <AccessGrid
        entities={data.designations.map((d) => ({ id: d.designationId, title: d.designationName, subtitle: d.roleName }))}
        columns={data.menuKeys.map((m) => ({ key: m.key, label: m.label }))}
        initialMatrix={data.matrix}
        searchPlaceholder="Search designation..."
        onSave={(pairs) => saveMobileMenu(pairs)}
      />
    </div>
  );
}
