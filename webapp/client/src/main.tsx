import { ConfigProvider } from 'antd'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App.tsx'
import './index.css'
import './styles/tally.css'
import { EmptyStateIllustration } from './components/illustrations/EmptyStateIllustration'

// Same navy/gold brand palette as the existing PHP web CRM (assets/css/style.css).
const theme = {
  token: {
    colorPrimary: '#0b2545',
    colorLink: '#0b2545',
    borderRadius: 8,
    fontFamily: "'Inter', system-ui, 'Segoe UI', Roboto, sans-serif",
  },
}

// Every antd Table/List/Select's empty state app-wide uses this instead of
// antd's plain grey default -- one place to change, no per-page wiring.
function renderEmpty() {
  return (
    <div className="flex flex-col items-center justify-center py-6">
      <EmptyStateIllustration className="h-28 w-auto" />
      <p className="mt-3 text-sm text-slate-400">No data found</p>
    </div>
  )
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ConfigProvider theme={theme} renderEmpty={renderEmpty}>
      <App />
    </ConfigProvider>
  </StrictMode>,
)
