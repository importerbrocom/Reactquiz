import { cn } from '@/utils/cn';

interface ProgressBarProps {
  /** Current value (0-100 or use value/max) */
  value: number;
  /** Maximum value. Defaults to 100. */
  max?: number;
  /** Visual variant */
  variant?: 'primary' | 'success' | 'danger' | 'warning';
  /** Size */
  size?: 'sm' | 'md' | 'lg';
  /** Show percentage label */
  showLabel?: boolean;
  /** Custom label */
  label?: string;
  /** Accessible label */
  ariaLabel?: string;
  className?: string;
}

const barColors = {
  primary: 'bg-primary-500',
  success: 'bg-success-500',
  danger: 'bg-danger-500',
  warning: 'bg-warning-500',
};

const barSizes = {
  sm: 'h-1.5',
  md: 'h-2.5',
  lg: 'h-4',
};

export function ProgressBar({
  value,
  max = 100,
  variant = 'primary',
  size = 'md',
  showLabel = false,
  label,
  ariaLabel,
  className,
}: ProgressBarProps) {
  const percentage = Math.min(100, Math.max(0, (value / max) * 100));

  return (
    <div className={cn('w-full', className)}>
      {(showLabel || label) && (
        <div className="mb-1 flex items-center justify-between text-xs text-surface-400">
          <span>{label ?? ''}</span>
          {showLabel && <span>{Math.round(percentage)}%</span>}
        </div>
      )}
      <div
        className={cn('w-full overflow-hidden rounded-full bg-surface-800', barSizes[size])}
        role="progressbar"
        aria-valuenow={Math.round(percentage)}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={ariaLabel ?? label ?? 'Progress'}
      >
        <div
          className={cn('h-full rounded-full transition-all duration-300 ease-out', barColors[variant])}
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  );
}
