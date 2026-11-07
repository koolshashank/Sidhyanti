import { SaveOutlined } from '@ant-design/icons';
import { Alert, Button, Spin, Switch, message } from 'antd';
import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../api/client';
import { fetchAppSettings, saveAppSettings, type AppSettingRow } from '../../api/settings';

export default function AppSettingsTab() {
  const [settings, setSettings] = useState<AppSettingRow[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchAppSettings()
      .then((res) => setSettings(res.settings))
      .catch((err) => setError(apiErrorMessage(err)));
  }, []);

  if (error) return <Alert type="error" message={error} showIcon />;
  if (!settings) return <Spin />;

  async function handleSave() {
    if (!settings) return;
    setSaving(true);
    try {
      const values = Object.fromEntries(settings.map((s) => [s.key, s.value]));
      await saveAppSettings(values);
      message.success('Saved');
    } catch (err) {
      message.error(apiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="max-w-xl rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
      <p className="mb-4 text-sm text-slate-500">App-wide toggles read once per mobile app launch.</p>
      <div className="flex flex-col gap-4">
        {settings.map((s) => (
          <div key={s.key} className="flex items-center justify-between border-b border-slate-50 pb-3 last:border-0">
            <span className="text-sm text-slate-700">{s.label}</span>
            <Switch
              checked={s.value}
              onChange={(checked) => setSettings((prev) => prev!.map((x) => (x.key === s.key ? { ...x, value: checked } : x)))}
            />
          </div>
        ))}
      </div>
      <Button type="primary" icon={<SaveOutlined />} loading={saving} onClick={handleSave} className="mt-4">
        Save
      </Button>
    </div>
  );
}
