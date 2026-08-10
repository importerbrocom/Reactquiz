import { cn } from '@/utils/cn';
import type { ReactNode } from 'react';

interface BadgeProps {
  variant?: 'primary' | 'success' | 'danger' | 'warning' | 'neutral';
  children: ReactNode;
  className?: string;
}

const variants = {
  primary: 'bg-primary-500/15 text-primary-300 border-primary-500/30',
  success: 'bg-success-500/15 text-success-500 border-success-500/30',
  danger: 'bg-danger-500/15 text-danger-500 border-danger-500/30',
  warning: 'bg-warning-500/15 text-warning-500 border-warning-500/30',
  neutral: 'bg-surface-700/50 text-surface-300 border-surface-600',
};

export function Badge({ variant = 'neutral', children, className }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium',
        variants[variant],
        className,
      )}
    >
      {children}
    </span>
  );
}
