import { CheckOutlined, CloseOutlined, SaveOutlined, SearchOutlined } from '@ant-design/icons';
import { Button, Input, message } from 'antd';
import { useMemo, useState } from 'react';

export interface AccessGridEntity {
  id: number;
  title: string;
  subtitle?: string | null;
}

export interface AccessGridColumn {
  key: string;
  label: string;
}

/**
 * Shared "card per entity, chip per column" UI for every deny-by-default
 * access matrix in this app (Role Permissions, Mobile Menu Access, AM
 * Branch Mapping) -- same pattern role_permissions.php / mobile_menu_access.php
 * / am_branch_mapping.php use: one card per row-entity, a flex-wrapped set of
 * toggle chips for the columns, per-card All/None, a client-side search box,
 * and one bulk Save for the whole grid.
 */
export function AccessGrid({
  entities,
  columns,
  initialMatrix,
  searchPlaceholder,
  onSave,
}: {
  entities: AccessGridEntity[];
  columns: AccessGridColumn[];
  initialMatrix: Record<number, Record<string, boolean>>;
  searchPlaceholder: string;
  onSave: (selectedPairs: string[]) => Promise<void>;
}) {
  const [matrix, setMatrix] = useState<Record<number, Record<string, boolean>>>(initialMatrix);
  const [search, setSearch] = useState('');
  const [saving, setSaving] = useState(false);

  const filteredEntities = useMemo(
    () => entities.filter((e) => e.title.toLowerCase().includes(search.toLowerCase())),
    [entities, search],
  );

  function toggle(entityId: number, columnKey: string) {
    setMatrix((prev) => ({
      ...prev,
      [entityId]: { ...prev[entityId], [columnKey]: !prev[entityId]?.[columnKey] },
    }));
  }

  function setAll(entityId: number, value: boolean) {
    setMatrix((prev) => ({
      ...prev,
      [entityId]: Object.fromEntries(columns.map((c) => [c.key, value])),
    }));
  }

  async function handleSave() {
    setSaving(true);
    try {
      const pairs: string[] = [];
      for (const entity of entities) {
        for (const col of columns) {
          if (matrix[entity.id]?.[col.key]) pairs.push(`${entity.id}:${col.key}`);
        }
      }
      await onSave(pairs);
      message.success('Saved');
    } catch {
      message.error('Failed to save');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-3">
        <Input
          placeholder={searchPlaceholder}
          prefix={<SearchOutlined className="text-slate-400" />}
          style={{ width: 280 }}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          allowClear
        />
        <Button type="primary" icon={<SaveOutlined />} loading={saving} onClick={handleSave}>
          Save Access
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {filteredEntities.map((entity) => {
          const rowMatrix = matrix[entity.id] ?? {};
          return (
            <div key={entity.id} className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
              <div className="mb-3 flex items-center justify-between">
                <div>
                  <div className="font-semibold text-slate-800">{entity.title}</div>
                  {entity.subtitle && <div className="text-xs text-slate-400">{entity.subtitle}</div>}
                </div>
                <div className="flex gap-1">
                  <Button size="small" icon={<CheckOutlined />} onClick={() => setAll(entity.id, true)}>
                    All
                  </Button>
                  <Button size="small" icon={<CloseOutlined />} onClick={() => setAll(entity.id, false)}>
                    None
                  </Button>
                </div>
              </div>
              <div className="flex flex-wrap gap-2">
                {columns.map((col) => {
                  const active = !!rowMatrix[col.key];
                  return (
                    <button
                      key={col.key}
                      type="button"
                      onClick={() => toggle(entity.id, col.key)}
                      className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                        active
                          ? 'border-blue-600 bg-blue-50 text-blue-700'
                          : 'border-slate-200 bg-slate-50 text-slate-500 hover:border-slate-300'
                      }`}
                      title={col.label}
                    >
                      {col.label}
                    </button>
                  );
                })}
              </div>
            </div>
          );
        })}
        {filteredEntities.length === 0 && <div className="text-sm text-slate-400">No matches.</div>}
      </div>
    </div>
  );
}
