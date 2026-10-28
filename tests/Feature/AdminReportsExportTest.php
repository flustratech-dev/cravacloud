<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReportsExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@gmail.com')->first();

        $this->customer = User::create([
            'username' => 'reportcustomer',
            'email' => 'reportcustomer@example.com',
            'full_name' => 'Report Customer',
            'password' => Hash::make('password123'),
            'channel' => 'website',
            'is_admin' => false,
        ]);

        $order = Order::create([
            'customer_id' => $this->customer->id,
            'vps_spec_id' => 1,
            'control_panel' => 'coolify',
            'provider' => 'tencent',
            'hostname' => 'vps-report-test',
            'datacenter_location' => 'singapore',
            'os' => 'ubuntu2404',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
            'paid_at' => now(),
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-TEST-2026-001',
            'amount' => 150000,
            'subtotal' => 150000,
            'status' => 'paid',
            'issued_at' => now(),
            'paid_at' => now(),
        ]);

        SupportTicket::create([
            'customer_id' => $this->customer->id,
            'subject' => 'Pertanyaan setup reverse proxy',
            'priority' => 'high',
            'status' => 'open',
            'first_response_at' => now()->addMinutes(15),
        ]);
    }

    public function test_admin_can_view_reports_page_with_enterprise_elements(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertStatus(200);
        $response->assertSee('Pusat Laporan & Analitik', false);
        $response->assertSee('Preview & Cetak PDF', false);
        $response->assertSee('Word (.docx)');
        $response->assertSee('CSV Excel');
        $response->assertSee('reportPdfIframe');
    }

    public function test_admin_can_preview_pdf_report_inline(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.preview', [
            'type' => 'executive',
            'days' => 30,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('inline', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);

        // PDF starts with standard %PDF magic header
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_admin_can_preview_all_report_types(): void
    {
        foreach (['executive', 'orders', 'vps', 'ai_combo', 'database', 'invoices', 'tickets'] as $type) {
            $response = $this->actingAs($this->admin)->get(route('admin.reports.preview', [
                'type' => $type,
                'days' => 'all',
            ]));

            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'application/pdf');
            $disposition = $response->headers->get('Content-Disposition');
            $this->assertStringContainsString('inline', $disposition);
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_admin_can_export_specialized_vps_ai_and_database_reports(): void
    {
        foreach (['vps', 'ai_combo', 'database'] as $type) {
            // PDF export
            $pdfRes = $this->actingAs($this->admin)->get(route('admin.reports.export', [
                'type' => $type,
                'format' => 'pdf',
                'days' => 30,
            ]));
            $pdfRes->assertStatus(200);
            $pdfRes->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdfRes->getContent());

            // Word export
            $docRes = $this->actingAs($this->admin)->get(route('admin.reports.export', [
                'type' => $type,
                'format' => 'docx',
                'days' => 30,
            ]));
            $docRes->assertStatus(200);
            $docRes->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

            // CSV export
            $csvRes = $this->actingAs($this->admin)->get(route('admin.reports.export', [
                'type' => $type,
                'format' => 'csv',
                'days' => 30,
            ]));
            $csvRes->assertStatus(200);
            $content = $csvRes->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        }
    }

    public function test_admin_can_export_pdf_attachment(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', [
            'type' => 'executive',
            'format' => 'pdf',
            'days' => 90,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_admin_can_export_word_docx_attachment(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', [
            'type' => 'executive',
            'format' => 'docx',
            'days' => 30,
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.docx', $disposition);
    }

    public function test_admin_can_export_csv_with_utf8_bom(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', [
            'type' => 'executive',
            'format' => 'csv',
            'days' => 30,
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        // Check for UTF-8 BOM: \xEF\xBB\xBF
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('LAPORAN EKSEKUTIF CRAVACLOUD', $content);
        $this->assertStringContainsString('Total Omzet Lunas', $content);
    }

    public function test_non_admin_cannot_access_reports_preview_or_export(): void
    {
        $previewResponse = $this->actingAs($this->customer)->get(route('admin.reports.preview'));
        $previewResponse->assertStatus(403);

        $exportResponse = $this->actingAs($this->customer)->get(route('admin.reports.export', ['format' => 'pdf']));
        $exportResponse->assertStatus(403);

        $guestPreview = $this->get(route('admin.reports.preview'));
        $guestPreview->assertStatus(403);
    }
}
