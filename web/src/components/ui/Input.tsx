import { forwardRef, type InputHTMLAttributes } from 'react';
import { cn } from '@/utils/cn';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  description?: string;
  error?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ className, label, description, error, id, ...props }, ref) => {
    const inputId = id ?? label?.toLowerCase().replace(/\s+/g, '-');
    const descriptionId = description ? `${inputId}-description` : undefined;
    const errorId = error ? `${inputId}-error` : undefined;

    return (
      <div className="space-y-1.5">
        {label && (
          <label
            htmlFor={inputId}
            className="block text-sm font-medium text-surface-200"
          >
            {label}
          </label>
        )}
        {description && (
          <p id={descriptionId} className="text-xs text-surface-400">
            {description}
          </p>
        )}
        <input
          ref={ref}
          id={inputId}
          aria-describedby={cn(descriptionId, errorId) || undefined}
          aria-invalid={!!error}
          className={cn(
            'min-h-[44px] w-full rounded-lg border bg-surface-900 px-4 py-2.5 text-surface-100 placeholder:text-surface-500 transition-colors',
            'focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500',
            error
              ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500'
              : 'border-surface-700 hover:border-surface-600',
            className,
          )}
          {...props}
        />
        {error && (
          <p id={errorId} role="alert" className="text-xs text-danger-500">
            {error}
          </p>
        )}
      </div>
    );
  },
);
Input.displayName = 'Input';
