import type { ReactNode } from 'react';
import { cn } from '@/utils/cn';

export interface Column<T> {
  key: string;
  header: string;
  render: (row: T) => ReactNode;
  className?: string;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  isLoading?: boolean;
  emptyMessage?: string;
  onRowClick?: (row: T) => void;
  rowKey: (row: T) => string;
}

/**
 * Headless data table — columns, sort, empty, loading.
 * Desktop-first for admin panel.
 */
export function DataTable<T>({
  columns,
  data,
  isLoading,
  emptyMessage = 'No data found',
  onRowClick,
  rowKey,
}: DataTableProps<T>) {
  if (isLoading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 5 }).map((_, i) => (
          <div key={i} className="h-12 animate-pulse rounded-lg bg-surface-800" />
        ))}
      </div>
    );
  }

  if (data.length === 0) {
    return (
      <div className="py-12 text-center text-surface-400">
        {emptyMessage}
      </div>
    );
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-surface-800">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-surface-800 bg-surface-900/50">
            {columns.map((col) => (
              <th
                key={col.key}
                className={cn(
                  'px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-surface-400',
                  col.className,
                )}
              >
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.map((row) => (
            <tr
              key={rowKey(row)}
              className={cn(
                'border-b border-surface-800/50 transition-colors',
                onRowClick && 'cursor-pointer hover:bg-surface-800/50',
              )}
              onClick={() => onRowClick?.(row)}
            >
              {columns.map((col) => (
                <td key={col.key} className={cn('px-4 py-3 text-surface-200', col.className)}>
                  {col.render(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
