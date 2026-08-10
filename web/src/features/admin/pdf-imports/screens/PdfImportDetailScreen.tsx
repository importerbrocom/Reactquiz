import { useParams } from 'react-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getPdfImport, getImportItems, approveImport, retryImport } from '@/api/endpoints/admin-pdf-imports.api';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { StatusBadge } from '../../components/StatusBadge';
import { PageSpinner } from '@/components/ui/Spinner';

function PdfImportDetailScreen() {
  const { importId } = useParams<{ importId: string }>();
  const queryClient = useQueryClient();

  const { data: imp, isLoading } = useQuery({
    queryKey: queryKeys.admin.pdfImports.detail(importId!),
    queryFn: () => getPdfImport(importId!),
    enabled: !!importId,
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === 'processing' || status === 'queued' ? 3000 : false;
    },
  });

  const { data: items } = useQuery({
    queryKey: queryKeys.admin.pdfImports.items(importId!),
    queryFn: () => getImportItems(importId!),
    enabled: !!importId && imp?.status === 'needs_review',
  });

  const approveMut = useMutation({
    mutationFn: () => approveImport(importId!),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.pdfImports.detail(importId!) }),
  });

  const retryMut = useMutation({
    mutationFn: () => retryImport(importId!),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.pdfImports.detail(importId!) }),
  });

  if (isLoading || !imp) return <PageSpinner />;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-white">{imp.filename}</h1>
        <StatusBadge status={imp.status} />
      </div>

      <ProgressBar value={imp.progress_percent} showLabel />

      <div className="grid grid-cols-4 gap-4">
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-white">{imp.total_items}</p>
          <p className="text-xs text-surface-400">Total</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-white">{imp.parsed_count}</p>
          <p className="text-xs text-surface-400">Parsed</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-success-500">{imp.approved_count}</p>
          <p className="text-xs text-surface-400">Approved</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-danger-500">{imp.rejected_count}</p>
          <p className="text-xs text-surface-400">Rejected</p>
        </Card>
      </div>

      {/* Items for review */}
      {items && items.data.length > 0 && (
        <Card variant="outlined">
          <h3 className="mb-3 font-medium text-white">Items for Review (sorted by confidence)</h3>
          <div className="max-h-96 space-y-2 overflow-y-auto">
            {items.data.map((item) => (
              <div key={item.id} className="rounded-lg border border-surface-700 bg-surface-800 p-3">
                <div className="flex items-start justify-between gap-2">
                  <p className="text-sm text-surface-200 line-clamp-2">{item.question_text}</p>
                  <Badge variant={item.confidence > 0.8 ? 'success' : item.confidence > 0.5 ? 'warning' : 'danger'}>
                    {Math.round(item.confidence * 100)}%
                  </Badge>
                </div>
                {item.issues.length > 0 && (
                  <div className="mt-1 flex flex-wrap gap-1">
                    {item.issues.map((issue, i) => (
                      <span key={i} className="text-xs text-warning-500">{issue}</span>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* Actions */}
      <div className="flex gap-3">
        {imp.status === 'needs_review' && (
          <Button onClick={() => approveMut.mutate()} loading={approveMut.isPending}>
            Approve All
          </Button>
        )}
        {imp.status === 'failed' && (
          <Button variant="secondary" onClick={() => retryMut.mutate()} loading={retryMut.isPending}>
            Retry
          </Button>
        )}
      </div>
    </div>
  );
}

export const Component = PdfImportDetailScreen;
export default PdfImportDetailScreen;
