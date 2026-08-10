import { RouterProvider } from 'react-router';
import { router } from '@/routes/router';

/**
 * App component — renders the router only.
 * All providers are in main.tsx.
 */
function App() {
  return <RouterProvider router={router} />;
}

export default App;
