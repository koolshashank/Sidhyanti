/**
 * Hand-drawn flat illustration for the login page's brand panel -- a field
 * officer collecting an EMI on their phone, in the app's own navy/gold
 * palette (no external asset/library, so there's nothing to license or
 * download). Purely decorative; safe to drop anywhere with a dark backdrop.
 */
export function CollectionIllustration({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 560 420" fill="none" xmlns="http://www.w3.org/2000/svg" className={className}>
      {/* soft ground shadow */}
      <ellipse cx="280" cy="392" rx="190" ry="16" fill="#ffffff" opacity="0.06" />

      {/* seated officer */}
      <g>
        {/* chair */}
        <rect x="120" y="300" width="110" height="14" rx="7" fill="#ffffff" opacity="0.12" />
        <rect x="128" y="200" width="14" height="110" rx="7" fill="#ffffff" opacity="0.12" />
        {/* legs */}
        <rect x="150" y="300" width="22" height="70" rx="10" fill="#0d3b66" />
        <rect x="190" y="300" width="22" height="70" rx="10" fill="#0a2e52" />
        {/* body / shirt */}
        <path d="M120 230c0-28 24-50 60-50s60 22 60 50v70H120v-70z" fill="#e8b95a" />
        {/* collar */}
        <path d="M160 182l20 18 20-18" stroke="#0b2545" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" />
        {/* arms holding phone */}
        <path d="M146 232c-14 10-22 28-20 46" stroke="#e8b95a" strokeWidth="18" strokeLinecap="round" />
        <path d="M214 232c14 10 20 26 18 44" stroke="#e8b95a" strokeWidth="18" strokeLinecap="round" />
        {/* hands */}
        <circle cx="124" cy="282" r="10" fill="#f4c98b" />
        <circle cx="236" cy="278" r="10" fill="#f4c98b" />
        {/* neck + head */}
        <rect x="168" y="162" width="24" height="22" rx="8" fill="#f4c98b" />
        <circle cx="180" cy="148" r="30" fill="#f4c98b" />
        {/* hair */}
        <path d="M150 140a30 30 0 0 1 60-4c-6-4-14-6-24-6-18 0-30 8-36 10z" fill="#0b2545" />
        {/* phone */}
        <rect x="150" y="258" width="60" height="44" rx="8" fill="#ffffff" />
        <rect x="156" y="264" width="48" height="26" rx="3" fill="#0b2545" />
        <circle cx="180" cy="296" r="3" fill="#cbd5e1" />
      </g>

      {/* floating app card -- collection list */}
      <g>
        <rect x="300" y="70" width="220" height="190" rx="18" fill="#ffffff" />
        <rect x="300" y="70" width="220" height="48" rx="18" fill="#f3e3c3" />
        <rect x="300" y="100" width="220" height="18" fill="#f3e3c3" />
        <circle cx="328" cy="94" r="12" fill="#e8b95a" />
        <rect x="350" y="86" width="90" height="8" rx="4" fill="#0b2545" opacity="0.6" />
        <rect x="350" y="100" width="60" height="6" rx="3" fill="#0b2545" opacity="0.3" />

        {/* rows */}
        {[0, 1, 2].map((i) => (
          <g key={i} transform={`translate(0, ${140 + i * 36})`}>
            <circle cx="324" cy="0" r="10" fill="#dbe5f1" />
            <rect x="344" y="-8" width="100" height="7" rx="3.5" fill="#1e3a5f" opacity="0.55" />
            <rect x="344" y="4" width="60" height="6" rx="3" fill="#94a3b8" />
            <circle cx="498" cy="0" r="11" fill="#22a06b" opacity="0.15" />
            <path d="M493 0l4 4 7-8" stroke="#1e9d6b" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
          </g>
        ))}
      </g>

      {/* floating rupee badge */}
      <g>
        <circle cx="450" cy="300" r="34" fill="#e8b95a" />
        <text x="450" y="310" textAnchor="middle" fontSize="30" fontWeight="700" fill="#0b2545" fontFamily="Inter, sans-serif">
          ₹
        </text>
      </g>

      {/* up-trend chevron */}
      <path
        d="M330 330l22-24 18 14 34-38"
        stroke="#ffffff"
        strokeWidth="5"
        strokeLinecap="round"
        strokeLinejoin="round"
        opacity="0.85"
        fill="none"
      />
      <path d="M390 282h18v18" stroke="#ffffff" strokeWidth="5" strokeLinecap="round" strokeLinejoin="round" opacity="0.85" fill="none" />

      {/* decorative dots */}
      <circle cx="80" cy="120" r="5" fill="#e8b95a" opacity="0.6" />
      <circle cx="60" cy="200" r="4" fill="#ffffff" opacity="0.3" />
      <circle cx="500" cy="80" r="4" fill="#ffffff" opacity="0.3" />
    </svg>
  );
}
