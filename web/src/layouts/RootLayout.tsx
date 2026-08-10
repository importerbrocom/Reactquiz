import { Outlet } from 'react-router';

/**
 * Root layout — wraps the entire app.
 * Houses global concerns: toasts, network banner, SW update prompt.
 * Individual providers are in main.tsx; this is the layout boundary.
 */
function RootLayout() {
  return (
    <>
      <Outlet />
      {/* Toast container and global modals can be added here */}
    </>
  );
}

export const Component = RootLayout;
export default RootLayout;
