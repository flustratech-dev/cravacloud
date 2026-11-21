<?php

namespace Tests\Feature;

use App\Jobs\SendBroadcastChunkJob;
use App\Models\BroadcastLog;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Notifications\AdminNewPaidOrderNotification;
use App\Notifications\AdminProvisioningFailedNotification;
use App\Notifications\AdminRenewalDigestNotification;
use App\Notifications\AdminShopeeOrderNotification;
use App\Notifications\AdminSlaBreachNotification;
use App\Notifications\AdminTicketNotification;
use App\Notifications\BillingReminderNotification;
use App\Notifications\InstanceRenewalReminderNotification;
use App\Notifications\InvoiceCreatedNotification;
use App\Notifications\MaintenanceScheduledNotification;
use App\Notifications\NewLoginAlertNotification;
use App\Notifications\OrganizationInvitationNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\RenewalGeneratedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\ServiceSuspendedNotification;
use App\Notifications\ServiceTerminatedNotification;
use App\Notifications\TemplatedNotification;
use App\Notifications\TicketRepliedNotification;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\VpsPasswordResetNotification;
use App\Notifications\VpsProvisionedNotification;
use App\Notifications\VpsSshUsernameChangedNotification;
use App\Notifications\VpsUpgradedNotification;
use App\Notifications\WelcomeCredentialsNotification;
use App\Services\AdminAlertService;
use App\Services\SettingsService;
use App\Services\WhatsAppGateway;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class Wave2NotificationOverhaulTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_14_customer_notifications_never_use_whatsapp_and_all_24_queued_classes_use_notifications_queue(): void
    {
        $customerClasses = [
            BillingReminderNotification::class,
            InstanceRenewalReminderNotification::class,
            InvoiceCreatedNotification::class,
            MaintenanceScheduledNotification::class,
            PaymentReceivedNotification::class,
            PaymentRejectedNotification::class,
            ServiceSuspendedNotification::class,
            ServiceTerminatedNotification::class,
            TicketRepliedNotification::class,
            VpsPasswordResetNotification::class,
            VpsProvisionedNotification::class,
            VpsSshUsernameChangedNotification::class,
            VpsUpgradedNotification::class,
            WelcomeCredentialsNotification::class,
        ];

        $otherQueuedClasses = [
            NewLoginAlertNotification::class,
            OrganizationInvitationNotification::class,
            RenewalGeneratedNotification::class,
            TemplatedNotification::class,
            AdminNewPaidOrderNotification::class,
            AdminProvisioningFailedNotification::class,
            AdminShopeeOrderNotification::class,
            AdminSlaBreachNotification::class,
            AdminTicketNotification::class,
            AdminRenewalDigestNotification::class,
        ];

        foreach ($customerClasses as $class) {
            $ref = new ReflectionClass($class);
            $this->assertTrue($ref->implementsInterface(ShouldQueue::class), "{$class} must implement ShouldQueue");
            $this->assertFalse($ref->hasMethod('toWhatsApp'), "{$class} must not define toWhatsApp()");
            $source = file_get_contents($ref->getFileName());
            $this->assertStringNotContainsString('WhatsAppChannel', $source, "{$class} must not reference WhatsAppChannel");
            $this->assertStringNotContainsString('WhatsAppMessage', $source, "{$class} must not reference WhatsAppMessage");
            $this->assertStringContainsString("onQueue('notifications')", $source, "{$class} must queue on 'notifications'");
        }

        foreach ($otherQueuedClasses as $class) {
            $ref = new ReflectionClass($class);
            $this->assertTrue($ref->implementsInterface(ShouldQueue::class), "{$class} must implement ShouldQueue");
            $source = file_get_contents($ref->getFileName());
            $this->assertStringContainsString("onQueue('notifications')", $source, "{$class} must queue on 'notifications'");
        }

        // Auth UX emails stay synchronous
        $this->assertFalse((new ReflectionClass(VerifyEmailNotification::class))->implementsInterface(ShouldQueue::class));
        $this->assertFalse((new ReflectionClass(ResetPasswordNotification::class))->implementsInterface(ShouldQueue::class));

        // User model must no longer route notifications to WhatsApp
        $this->assertFalse(method_exists(User::class, 'routeNotificationForWhatsApp'));
    }

    public function test_payment_received_blade_renders_renewal_upgrade_and_new_copy(): void
    {
        $customer = User::create([
            'full_name' => 'Budi Santoso',
            'username' => 'budi' . uniqid(),
            'email' => 'budi' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);
        $spec = VpsSpec::first();

        $newOrder = Order::create([
            'customer_id' => $customer->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'order_type' => 'new',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
        ]);
        $renewOrder = Order::create([
            'customer_id' => $customer->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'order_type' => 'renewal',
            'status' => 'active',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 150000,
        ]);
        $upgradeOrder = Order::create([
            'customer_id' => $customer->id,
            'vps_spec_id' => $spec->id,
            'control_panel' => 'none',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'order_type' => 'upgrade',
            'status' => 'paid',
            'channel' => 'website',
            'payment_method' => 'qris',
            'amount' => 75000,
        ]);

        $newHtml = (string) (new PaymentReceivedNotification($newOrder->load('customer', 'vpsSpec')))->toMail($customer)->render();
        $renewHtml = (string) (new PaymentReceivedNotification($renewOrder->load('customer', 'vpsSpec')))->toMail($customer)->render();
        $upgradeHtml = (string) (new PaymentReceivedNotification($upgradeOrder->load('customer', 'vpsSpec')))->toMail($customer)->render();

        $this->assertStringNotContainsString('Password)', $newHtml);
        $this->assertStringContainsString('diperpanjang secara otomatis', $renewHtml);
        $this->assertStringContainsString('ditingkatkan', $upgradeHtml);
    }

    public function test_admin_alert_router_honors_toggle_dedupe_html_mail_and_ring_buffer(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url' => 'https://wa.cravacloud.id',
            'whatsapp.key' => 'secret-wa-key',
            'whatsapp.session' => 'ops',
        ]);

        Http::fake([
            'https://wa.cravacloud.id/api/v1/messages/text' => Http::response(['success' => true, 'data' => ['id' => 'wa_1']], 200),
        ]);

        Mail::fake();

        app(SettingsService::class)->setMany([
            'admin_notification_whatsapp' => '081234567890',
            'mail_admin_addresses' => 'ops@cravacloud.id',
        ]);

        $alerts = app(AdminAlertService::class);
        $this->assertCount(15, AdminAlertService::EVENTS);

        // 1. Event with default WA ON sends both Email & WA, dedupes on second call
        $res1 = $alerts->alert('payment.failed', [
            'title' => 'Amount Mismatch Test',
            'details' => ['Order' => '#999'],
            'action_url' => url('/admin/webhooks?status=failed'),
        ], dedupeKey: 'test-key-1', cooldownSeconds: 3600);

        $this->assertTrue($res1['email']);
        $this->assertTrue($res1['whatsapp']);
        $this->assertFalse($res1['skipped_dedupe']);
        Http::assertSentCount(1);

        $res2 = $alerts->alert('payment.failed', [
            'title' => 'Amount Mismatch Test Duplicate',
        ], dedupeKey: 'test-key-1', cooldownSeconds: 3600);

        $this->assertTrue($res2['skipped_dedupe']);
        Http::assertSentCount(1);

        // 2. Event toggled OFF in admin_alert_wa_events sends Email only, ZERO WA
        app(SettingsService::class)->set('admin_alert_wa_events', ['payment.failed' => false], 'json', ['group' => 'notification']);

        $res3 = $alerts->alert('payment.failed', [
            'title' => 'Email Only When WA Off',
        ], dedupeKey: 'test-key-2', cooldownSeconds: 3600);

        $this->assertTrue($res3['email']);
        $this->assertFalse($res3['whatsapp']);
        Http::assertSentCount(1);

        // 3. Recent alerts ring buffer recorded entries
        $recent = $alerts->recentAlerts();
        $this->assertGreaterThanOrEqual(2, count($recent));
        $this->assertSame('payment.failed', $recent[0]['event_key']);
    }

    public function test_tickets_check_sla_command_sends_alert_without_crash_and_dedupes(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.url' => 'https://wa.cravacloud.id',
            'whatsapp.key' => 'secret-wa-key',
        ]);
        Http::fake([
            'https://wa.cravacloud.id/api/v1/messages/text' => Http::response(['success' => true, 'data' => ['id' => 'wa_sla_1']], 200),
        ]);
        Notification::fake();

        app(SettingsService::class)->setMany([
            'admin_notification_whatsapp' => '081234567890',
            'mail_admin_addresses' => 'ops@cravacloud.id',
        ]);

        $customer = User::create([
            'full_name' => 'Klien SLA',
            'username' => 'sla' . uniqid(),
            'email' => 'sla' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);

        SupportTicket::create([
            'customer_id' => $customer->id,
            'ticket_number' => 'TK-SLA-' . strtoupper(Str::random(5)),
            'subject' => 'Server SSH Timeout',
            'status' => 'open',
            'priority' => 'high',
            'first_response_at' => null,
            'sla_due_at' => now()->subMinutes(10),
        ]);

        $this->artisan('tickets:check-sla')->assertSuccessful();
        $this->artisan('tickets:check-sla')->assertSuccessful();

        Notification::assertSentOnDemandTimes(AdminSlaBreachNotification::class, 1);
        Http::assertSentCount(1);
    }

    public function test_send_broadcast_chunk_job_uses_full_name_and_tracks_counts(): void
    {
        Notification::fake();

        $admin = User::create([
            'full_name' => 'Admin Broadcast',
            'username' => 'admbc' . uniqid(),
            'email' => 'admbc' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $user = User::create([
            'full_name' => 'Siti Aminah',
            'username' => 'sitiaminah',
            'email' => 'siti' . uniqid() . '@test.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);

        $log = BroadcastLog::create([
            'sent_by' => $admin->id,
            'audience' => 'all',
            'subject' => 'Halo {{nama}}',
            'body' => 'Informasi penting untuk {{nama}} ({{email}}).',
            'recipient_count' => 1,
            'sent_count' => 0,
            'failed_count' => 0,
            'sent_at' => now(),
        ]);

        (new SendBroadcastChunkJob($log->id, [$user->id], 'Halo {{nama}}', 'Informasi penting untuk {{nama}} ({{email}}).'))->handle();

        Notification::assertSentTo($user, TemplatedNotification::class, function ($n) {
            return str_contains($n->mailSubject, 'Halo Siti Aminah')
                && str_contains($n->mailBody, 'Siti Aminah');
        });

        $this->assertSame(1, $log->fresh()->sent_count);
    }
}
