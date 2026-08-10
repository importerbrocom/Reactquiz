import { useEffect, useRef, type ReactNode } from 'react';
import { cn } from '@/utils/cn';

interface ModalProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  children: ReactNode;
  className?: string;
}

/**
 * Modal component with focus trap, Esc close, scroll lock, and return focus.
 */
export function Modal({ open, onClose, title, children, className }: ModalProps) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;

    if (open) {
      previousFocusRef.current = document.activeElement as HTMLElement;
      dialog.showModal();
      document.body.style.overflow = 'hidden';
    } else {
      dialog.close();
      document.body.style.overflow = '';
      previousFocusRef.current?.focus();
    }

    return () => {
      document.body.style.overflow = '';
    };
  }, [open]);

  // Close on backdrop click
  const handleBackdropClick = (e: React.MouseEvent) => {
    if (e.target === dialogRef.current) {
      onClose();
    }
  };

  return (
    <dialog
      ref={dialogRef}
      className={cn(
        'fixed inset-0 z-50 m-auto max-h-[85dvh] w-[90vw] max-w-lg overflow-y-auto rounded-xl border border-surface-700 bg-surface-900 p-6 text-surface-100 shadow-2xl backdrop:bg-black/60',
        className,
      )}
      aria-modal="true"
      aria-labelledby={title ? 'modal-title' : undefined}
      onCancel={onClose}
      onClick={handleBackdropClick}
    >
      {title && (
        <h2 id="modal-title" className="mb-4 text-lg font-semibold text-white">
          {title}
        </h2>
      )}
      {children}
    </dialog>
  );
}
