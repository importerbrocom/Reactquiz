import { cn } from '@/utils/cn';

/**
 * ERO logo — single source of truth for the brand mark across the web app.
 *
 * PLACEHOLDER: currently points at the existing PWA app icon. To use the real
 * "ERO — Elior Research Orbit" logo, drop the file at `web/public/ero-logo.png`
 * and change LOGO_SRC below to '/ero-logo.png'. Every usage updates at once.
 */
const LOGO_SRC = '/icons/icon-192.png';

export interface LogoProps {
  /** pixel size of the square mark */
  size?: number;
  /** show the "ERO" wordmark beside the mark */
  showWordmark?: boolean;
  /** optional tagline under/next to the wordmark */
  tagline?: string;
  className?: string;
}

export function Logo({ size = 36, showWordmark = false, tagline, className }: LogoProps) {
  return (
    <div className={cn('flex items-center gap-2.5', className)}>
      <img
        src={LOGO_SRC}
        alt="ERO"
        width={size}
        height={size}
        className="rounded-xl object-cover"
        style={{ width: size, height: size }}
      />
      {showWordmark && (
        <div className="leading-tight">
          <span className="text-lg font-extrabold tracking-tight text-primary-400">ERO</span>
          {tagline && <p className="text-[11px] text-surface-400">{tagline}</p>}
        </div>
      )}
    </div>
  );
}

export default Logo;
