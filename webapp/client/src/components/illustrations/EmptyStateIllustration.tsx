/**
 * Shared "nothing here yet" illustration -- used as the app-wide default
 * empty state (see main.tsx's ConfigProvider.renderEmpty) so every antd
 * Table/List/Select across the app gets this instead of antd's plain grey
 * default, without touching each list page individually.
 */
export function EmptyStateIllustration({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 220 160" fill="none" xmlns="http://www.w3.org/2000/svg" className={className}>
      <ellipse cx="110" cy="138" rx="70" ry="10" fill="#0b2545" opacity="0.06" />

      {/* clipboard */}
      <rect x="62" y="30" width="96" height="112" rx="12" fill="#eef2f7" />
      <rect x="62" y="30" width="96" height="112" rx="12" stroke="#cbd5e1" strokeWidth="1.5" />
      <rect x="92" y="22" width="36" height="16" rx="6" fill="#c8952c" />

      {/* rows */}
      {[0, 1, 2].map((i) => (
        <g key={i} transform={`translate(0, ${58 + i * 22})`}>
          <rect x="78" y="0" width="12" height="12" rx="4" fill="#dbe5f1" />
          <rect x="98" y="2" width="48" height="7" rx="3.5" fill="#cbd5e1" />
        </g>
      ))}

      {/* magnifying glass */}
      <circle cx="146" cy="104" r="22" fill="#ffffff" stroke="#0b2545" strokeWidth="4" />
      <line x1="162" y1="120" x2="178" y2="136" stroke="#0b2545" strokeWidth="5" strokeLinecap="round" />
      <path d="M136 100a10 10 0 0 1 10-10" stroke="#c8952c" strokeWidth="3" strokeLinecap="round" />
    </svg>
  );
}
