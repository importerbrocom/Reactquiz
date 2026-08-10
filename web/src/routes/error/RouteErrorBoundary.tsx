import { useRouteError, isRouteErrorResponse, Link } from 'react-router';
import { ROUTES } from '@/config/routes.config';

/**
 * Global route error boundary.
 * Catches unhandled errors at the route level and shows a recovery UI.
 */
export function RouteErrorBoundary() {
  const error = useRouteError();

  if (isRouteErrorResponse(error)) {
    if (error.status === 404) {
      return <NotFound />;
    }
    return (
      <ErrorPage
        title={`${error.status} Error`}
        message={error.statusText || 'Something went wrong'}
      />
    );
  }

  return (
    <ErrorPage
      title="Unexpected Error"
      message="Something went wrong. Please try again."
    />
  );
}

function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-4 text-center">
      <h1 className="text-6xl font-bold text-primary-500">404</h1>
      <p className="text-xl text-surface-400">Page not found</p>
      <Link
        to={ROUTES.DASHBOARD}
        className="mt-4 rounded-lg bg-primary-600 px-6 py-3 font-medium text-white transition-colors hover:bg-primary-700"
      >
        Go to Dashboard
      </Link>
    </div>
  );
}

function ErrorPage({ title, message }: { title: string; message: string }) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-4 text-center">
      <h1 className="text-4xl font-bold text-danger-500">{title}</h1>
      <p className="text-lg text-surface-400">{message}</p>
      <button
        onClick={() => window.location.reload()}
        className="mt-4 rounded-lg bg-primary-600 px-6 py-3 font-medium text-white transition-colors hover:bg-primary-700"
      >
        Reload Page
      </button>
    </div>
  );
}
