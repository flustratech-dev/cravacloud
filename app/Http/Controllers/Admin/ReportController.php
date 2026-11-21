<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use Concerns\LogsAdminAudit;

    public function __construct(
        protected ReportExportService $reportExportService
    ) {
    }

    public function reports(Request $request)
    {
        $days = $request->input('days', 30);
        $days = ($days === 'all') ? 'all' : (int) $days;
        abort_unless(in_array($days, [30, 90, 365, 'all'], true), 422);

        $data = $this->reportExportService->getReportData('executive', $days);

        return view('admin.reports', [
            'days' => $days,
            'periodLabel' => $data['periodLabel'],
            'revenue' => $data['revenue'],
            'paidInvoices' => $data['paidInvoices'],
            'orders' => $data['orders'],
            'invoices' => $data['invoices'],
            'tickets' => $data['tickets'],
            'vpsOrders' => $data['vpsOrders'] ?? collect(),
            'vpsRevenue' => $data['vpsRevenue'] ?? 0,
            'avgFirstResponse' => $data['avgFirstResponse'] ?? null,
            'dailyRevenue' => $data['dailyRevenue'] ?? collect(),
            'orderStatuses' => $data['orderStatuses'] ?? collect(),
            'ticketStatuses' => $data['ticketStatuses'] ?? collect(),
            'reportRef' => $data['reportRef'] ?? 'CVC-RPT',
            'generatedBy' => $data['generatedBy'] ?? 'Administrator',
        ]);
    }

    public function previewReport(Request $request)
    {
        $type = $request->input('type', 'executive');
        abort_unless(in_array($type, ['executive', 'orders', 'vps', 'invoices', 'tickets'], true), 404);

        $days = $request->input('days', 30);
        $days = ($days === 'all') ? 'all' : (int) $days;
        abort_unless(in_array($days, [30, 90, 365, 'all'], true), 422);

        return $this->reportExportService->generatePdf($type, $days, inline: true);
    }

    public function exportReport(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:executive,orders,vps,invoices,tickets',
            'format' => 'nullable|in:pdf,docx,word,csv',
            'days' => 'nullable',
        ]);

        $type = $validated['type'] ?? 'executive';
        $format = strtolower($validated['format'] ?? 'csv');

        $days = $request->input('days', 30);
        $days = ($days === 'all') ? 'all' : (int) $days;
        if (!in_array($days, [30, 90, 365, 'all'], true)) {
            $days = 30;
        }

        if ($format === 'pdf') {
            return $this->reportExportService->generatePdf($type, $days, inline: false);
        }

        if ($format === 'docx' || $format === 'word') {
            return $this->reportExportService->generateDocx($type, $days);
        }

        return $this->reportExportService->streamCsv($type, $days);
    }
}
