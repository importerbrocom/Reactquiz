import type { ReactNode } from 'react';

interface AdminTableToolbarProps {
  title: string;
  count?: number;
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  searchPlaceholder?: string;
  actions?: ReactNode;
}

/**
 * Toolbar above admin tables — title, count, search, and action buttons.
 */
export function AdminTableToolbar({
  title,
  count,
  searchValue,
  onSearchChange,
  searchPlaceholder = 'Search...',
  actions,
}: AdminTableToolbarProps) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-4">
      <div className="flex items-center gap-3">
        <h1 className="text-xl font-bold text-white">{title}</h1>
        {count !== undefined && (
          <span className="rounded-full bg-surface-800 px-2.5 py-0.5 text-xs text-surface-400">
            {count}
          </span>
        )}
      </div>
      <div className="flex items-center gap-3">
        {onSearchChange && (
          <input
            type="search"
            value={searchValue ?? ''}
            onChange={(e) => onSearchChange(e.target.value)}
            placeholder={searchPlaceholder}
            className="min-h-[40px] rounded-lg border border-surface-700 bg-surface-900 px-3 text-sm text-surface-200 placeholder:text-surface-500 focus:border-primary-500 focus:outline-none"
          />
        )}
        {actions}
      </div>
    </div>
  );
}
