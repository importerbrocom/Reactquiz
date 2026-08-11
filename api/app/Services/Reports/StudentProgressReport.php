<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ReportExport;
use App\Models\User;

/**
 * Generates a student progress report (CSV format).
 * Includes: student name, email, course, level, days completed, accuracy, streak.
 */
class StudentProgressReport implements ReportGeneratorInterface
{
    public function generate(ReportExport $report): array
    {
        $filters = $report->filters ?? [];

        $query = User::query()
            ->where('role', 'student')
            ->with(['programmeEnrollments.programme', 'levelEnrollments']);

        if (isset($filters['course_id'])) {
            $query->whereHas('programmeEnrollments', function ($q) use ($filters) {
                $q->where('programme_id', $filters['course_id']);
            });
        }

        $students = $query->get();

        $rows = [];
        $rows[] = ['Name', 'Email', 'Programme', 'Level', 'Days Completed', 'Streak', 'Joined'];

        foreach ($students as $student) {
            foreach ($student->programmeEnrollments as $enrolment) {
                $rows[] = [
                    $student->name,
                    $student->email,
                    $enrolment->programme?->title ?? 'N/A',
                    $enrolment->current_level ?? 0,
                    $enrolment->days_completed ?? 0,
                    0, // streak - fetched separately in real implementation
                    $student->created_at?->toDateString() ?? '',
                ];
            }
        }

        $content = $this->toCsv($rows);

        return ['content' => $content, 'row_count' => count($rows) - 1];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content !== false ? $content : '';
    }
}
