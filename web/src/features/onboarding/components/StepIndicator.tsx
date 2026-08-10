import { cn } from '@/utils/cn';

interface StepIndicatorProps {
  currentStep: number;
  totalSteps: number;
}

/**
 * Visual step indicator for the onboarding wizard.
 */
export function StepIndicator({ currentStep, totalSteps }: StepIndicatorProps) {
  return (
    <div className="flex items-center justify-center gap-2" aria-label={`Step ${currentStep} of ${totalSteps}`}>
      {Array.from({ length: totalSteps }, (_, i) => (
        <div
          key={i}
          className={cn(
            'h-2 rounded-full transition-all duration-300',
            i + 1 === currentStep
              ? 'w-8 bg-primary-500'
              : i + 1 < currentStep
                ? 'w-2 bg-primary-400'
                : 'w-2 bg-surface-700',
          )}
        />
      ))}
    </div>
  );
}
