import { ConfigProvider, theme } from 'antd';
import type { ReactNode } from 'react';

/**
 * Real dark-theme tokens for the Accounting module's Tally look -- Select/
 * DatePicker popups render into document.body via a portal by default, past
 * the reach of the scoped .tally-scope stylesheet, so token-based theming
 * (which antd applies via context, following the React tree rather than the
 * DOM tree) is what actually colors them correctly. getPopupContainer keeps
 * each popup mounted inside .tally-scope too, so the panel's position/size
 * behaves the same as it always has elsewhere in the app.
 */
export function TallyTheme({ children }: { children: ReactNode }) {
  return (
    <ConfigProvider
      getPopupContainer={(triggerNode) => (triggerNode?.closest('.tally-scope') as HTMLElement) ?? document.body}
      theme={{
        algorithm: theme.darkAlgorithm,
        token: {
          colorPrimary: '#f5c518',
          colorLink: '#f5c518',
          colorBgContainer: '#0f2a52',
          colorBgElevated: '#132f5c',
          colorBgLayout: '#0a1f3d',
          colorBgSpotlight: '#132f5c',
          colorText: '#e8edf5',
          colorTextSecondary: '#9fb3cc',
          colorTextPlaceholder: '#6f87a8',
          colorBorder: '#2d4d7a',
          colorBorderSecondary: '#24406a',
          colorSplit: '#24406a',
          borderRadius: 2,
          fontFamily: "'Consolas', 'Courier New', ui-monospace, monospace",
        },
        components: {
          Table: {
            headerBg: '#132f5c',
            headerColor: '#f5c518',
            rowHoverBg: '#17356a',
            borderColor: '#2d4d7a',
          },
          Tabs: {
            itemSelectedColor: '#f5c518',
            inkBarColor: '#f5c518',
            itemColor: '#9fb3cc',
          },
          Button: {
            primaryColor: '#0a1f3d',
          },
        },
      }}
    >
      {children}
    </ConfigProvider>
  );
}
