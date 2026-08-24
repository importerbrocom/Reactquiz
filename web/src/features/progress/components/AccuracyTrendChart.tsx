import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  type TooltipProps,
} from 'recharts';

interface TrendPoint {
  day: number;
  score: number;
}

interface AccuracyTrendChartProps {
  data: TrendPoint[];
}

/**
 * Line chart of daily accuracy (score as a percentage) across completed days.
 * Colours pull from the surface/primary theme tokens so it matches the dark UI.
 */
export function AccuracyTrendChart({ data }: AccuracyTrendChartProps) {
  if (data.length === 0) {
    return (
      <div className="flex h-40 items-center justify-center text-sm text-surface-500">
        No completed days yet
      </div>
    );
  }

  // A single data point renders no line; nudge the user with a hint but still plot the dot.
  return (
    <div className="h-48 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={data} margin={{ top: 8, right: 8, bottom: 8, left: -20 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" vertical={false} />
          <XAxis
            dataKey="day"
            stroke="#64748b"
            fontSize={11}
            tickLine={false}
            axisLine={{ stroke: '#334155' }}
            tickFormatter={(v: number) => `${v}`}
          />
          <YAxis
            domain={[0, 100]}
            ticks={[0, 25, 50, 75, 100]}
            stroke="#64748b"
            fontSize={11}
            tickLine={false}
            axisLine={false}
            tickFormatter={(v: number) => `${v}%`}
          />
          <Tooltip content={<ChartTooltip />} cursor={{ stroke: '#475569', strokeWidth: 1 }} />
          <Line
            type="monotone"
            dataKey="score"
            stroke="#6366f1"
            strokeWidth={2}
            dot={{ r: 3, fill: '#6366f1', strokeWidth: 0 }}
            activeDot={{ r: 5, fill: '#818cf8' }}
            isAnimationActive
            animationDuration={400}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

function ChartTooltip({ active, payload, label }: TooltipProps<number, string>) {
  if (!active || !payload || payload.length === 0) return null;
  const score = payload[0].value ?? 0;
  return (
    <div className="rounded-lg border border-surface-700 bg-surface-900 px-3 py-2 text-xs shadow-lg">
      <p className="font-medium text-white">Day {label}</p>
      <p className="text-primary-400">{score}% accuracy</p>
    </div>
  );
}
