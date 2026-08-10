import { useState } from 'react';
import { useInfiniteQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { queryKeys } from '@/api/query-keys';
import { getPdfImports, uploadPdf } from '@/api/endpoints/admin-pdf-imports.api';
import { AdminTableToolbar } from '../../components/AdminTableToolbar';
import { DataTable, type Column } from '../../components/DataTable';
import { StatusBadge } from '../../components/StatusBadge';
import { Button } from '@/components/ui/Button';
import { ProgressBar } from '@/components/ui/ProgressBar';
import type { PdfImport } from '@/api/endpoints/admin-pdf-imports.api';

function PdfImportsScreen() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [uploading, setUploading] = useState(false);

  const { data, isLoading } = useInfiniteQuery({
    queryKey: queryKeys.admin.pdfImports.list({}),
    queryFn: ({ pageParam }) => getPdfImports({ cursor: pageParam as string | undefined }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  });

  const imports = data?.pages.flatMap((p) => p.data) ?? [];

  const handleUpload = async () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pdf';
    input.onchange = async (e) => {
      const file = (e.target as HTMLInputElement).files?.[0];
      if (!file) return;
      setUploading(true);
      try {
        await uploadPdf(file);
        queryClient.invalidateQueries({ queryKey: queryKeys.admin.pdfImports.all });
      } finally {
        setUploading(false);
      }
    };
    input.click();
  };

  const columns: Column<PdfImport>[] = [
    { key: 'filename', header: 'File', render: (r) => <span className="font-medium">{r.filename}</span> },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'progress', header: 'Progress', render: (r) => <ProgressBar value={r.progress_percent} size="sm" /> },
    { key: 'items', header: 'Items', render: (r) => `${r.approved_count}/${r.total_items}` },
    { key: 'date', header: 'Uploaded', render: (r) => new Date(r.created_at).toLocaleDateString() },
  ];

  return (
    <div className="space-y-6">
      <AdminTableToolbar
        title="PDF Imports"
        count={imports.length}
        actions={<Button onClick={handleUpload} loading={uploading}>+ Upload PDF</Button>}
      />
      <DataTable columns={columns} data={imports} isLoading={isLoading} rowKey={(r) => r.id} onRowClick={(r) => navigate(`/admin/pdf-imports/${r.id}`)} emptyMessage="No imports yet" />
    </div>
  );
}

export const Component = PdfImportsScreen;
export default PdfImportsScreen;
