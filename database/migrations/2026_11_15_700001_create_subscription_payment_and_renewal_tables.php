<?php

use App\Support\SchemaIntrospector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function getConnection(): ?string
    {
        return 'mysql_migrator';
    }

    private function guardSandbox($conn): void
    {
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        if ($db !== 'stockora_sandbox') {
            throw new \RuntimeException("SAFETY ABORT: Round 23 migration refused on non-sandbox database '{$db}'.");
        }
    }

    public function up(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $schema = $conn->getSchemaBuilder();
        $intro = new SchemaIntrospector($conn);

        if (!$schema->hasTable('payment_instructions')) {
            $schema->create('payment_instructions', function (Blueprint $table) {
                $table->id();
                $table->string('method_code', 50)->unique('uq_pay_instr_code');
                $table->string('method_name', 120);
                $table->string('channel_type', 30)->default('BANK_TRANSFER');
                $table->string('bank_name', 120)->nullable();
                $table->string('account_name', 150)->nullable();
                $table->string('account_number', 100)->nullable();
                $table->text('instructions')->nullable();
                $table->text('instructions_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by_platform_admin_id')->nullable();
                $table->unsignedBigInteger('updated_by_platform_admin_id')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'idx_pay_instr_active_sort');
                $table->foreign('created_by_platform_admin_id', 'fk_pay_instr_created_admin')
                    ->references('id')->on('platform_admins')->nullOnDelete();
                $table->foreign('updated_by_platform_admin_id', 'fk_pay_instr_updated_admin')
                    ->references('id')->on('platform_admins')->nullOnDelete();
            });
        }

        if (!$schema->hasTable('platform_settings')) {
            $schema->create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key', 100)->unique('uq_platform_settings_key');
                $table->text('setting_value')->nullable();
                $table->string('description', 255)->nullable();
                $table->unsignedBigInteger('updated_by_platform_admin_id')->nullable();
                $table->timestamps();

                $table->foreign('updated_by_platform_admin_id', 'fk_platform_settings_admin')
                    ->references('id')->on('platform_admins')->nullOnDelete();
            });
        }

        if (!$schema->hasTable('subscription_payments')) {
            $schema->create('subscription_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_reference', 40)->unique('uq_sub_payments_reference');
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('subscription_plan_id');
                $table->unsignedBigInteger('payment_instruction_id')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->unsignedBigInteger('recorded_by_platform_admin_id')->nullable();

                $table->string('source', 30)->default('CUSTOMER_SUBMISSION');
                $table->string('request_type', 30)->default('RENEWAL');
                $table->string('status', 25)->default('PENDING');

                $table->string('plan_code_snapshot', 50);
                $table->string('plan_name_snapshot', 150);
                $table->string('plan_tier_snapshot', 40);
                $table->string('billing_interval', 20)->default('MONTHLY');
                $table->unsignedInteger('duration_days')->default(30);
                $table->decimal('base_plan_price', 15, 2)->default(0);
                $table->decimal('amount', 15, 2);
                $table->string('currency', 10)->default('IDR');

                $table->string('payment_method_code', 50)->nullable();
                $table->string('payment_method_name', 120);
                $table->string('customer_transfer_reference', 120)->nullable();
                $table->string('payer_name', 150)->nullable();
                $table->date('payment_date');
                $table->boolean('payment_received_confirmed')->default(false);
                $table->text('customer_note')->nullable();
                $table->text('admin_note')->nullable();

                $table->string('payment_proof_disk', 30)->nullable();
                $table->string('payment_proof_path', 500)->nullable();
                $table->string('payment_proof_original_name', 255)->nullable();
                $table->string('payment_proof_mime', 100)->nullable();
                $table->unsignedBigInteger('payment_proof_size')->nullable();
                $table->string('payment_proof_hash', 64)->nullable();

                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by_platform_admin_id')->nullable();
                $table->text('verification_note')->nullable();

                $table->timestamp('rejected_at')->nullable();
                $table->unsignedBigInteger('rejected_by_platform_admin_id')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
                $table->unsignedBigInteger('cancelled_by_platform_admin_id')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->unsignedBigInteger('replaced_by_payment_id')->nullable();

                $table->timestamp('applied_to_subscription_at')->nullable();
                $table->unsignedBigInteger('applied_by_platform_admin_id')->nullable();
                $table->string('applied_action', 40)->nullable();
                $table->timestamp('period_starts_at')->nullable();
                $table->timestamp('period_ends_at')->nullable();
                $table->unsignedBigInteger('previous_plan_id')->nullable();
                $table->timestamp('previous_expires_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'status'], 'idx_sub_pay_company_status');
                $table->index(['status', 'payment_date'], 'idx_sub_pay_status_date');
                $table->index(['subscription_plan_id', 'status'], 'idx_sub_pay_plan_status');
                $table->index('submitted_at', 'idx_sub_pay_submitted');

                $table->foreign('company_id', 'fk_sub_pay_company')->references('id')->on('companies')->restrictOnDelete();
                $table->foreign('subscription_plan_id', 'fk_sub_pay_plan')->references('id')->on('subscription_plans')->restrictOnDelete();
                $table->foreign('payment_instruction_id', 'fk_sub_pay_instr')->references('id')->on('payment_instructions')->nullOnDelete();
                $table->foreign('requested_by_user_id', 'fk_sub_pay_req_user')->references('id')->on('users')->nullOnDelete();
                $table->foreign('recorded_by_platform_admin_id', 'fk_sub_pay_rec_admin')->references('id')->on('platform_admins')->nullOnDelete();
                $table->foreign('verified_by_platform_admin_id', 'fk_sub_pay_ver_admin')->references('id')->on('platform_admins')->nullOnDelete();
                $table->foreign('rejected_by_platform_admin_id', 'fk_sub_pay_rej_admin')->references('id')->on('platform_admins')->nullOnDelete();
                $table->foreign('cancelled_by_user_id', 'fk_sub_pay_can_user')->references('id')->on('users')->nullOnDelete();
                $table->foreign('cancelled_by_platform_admin_id', 'fk_sub_pay_can_admin')->references('id')->on('platform_admins')->nullOnDelete();
                $table->foreign('replaced_by_payment_id', 'fk_sub_pay_replaced_by')->references('id')->on('subscription_payments')->nullOnDelete();
                $table->foreign('applied_by_platform_admin_id', 'fk_sub_pay_app_admin')->references('id')->on('platform_admins')->nullOnDelete();
                $table->foreign('previous_plan_id', 'fk_sub_pay_prev_plan')->references('id')->on('subscription_plans')->nullOnDelete();
            });
        }

        if (!$schema->hasTable('subscription_renewal_requests')) {
            $schema->create('subscription_renewal_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number', 40)->unique('uq_sub_ren_req_number');
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('current_plan_id')->nullable();
                $table->unsignedBigInteger('requested_plan_id');
                $table->unsignedBigInteger('subscription_payment_id')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();

                $table->string('request_type', 30)->default('RENEWAL');
                $table->string('billing_interval', 20)->default('MONTHLY');
                $table->unsignedInteger('duration_days')->default(30);
                $table->decimal('quoted_amount', 15, 2)->default(0);
                $table->string('status', 30)->default('PENDING_VERIFICATION');

                $table->string('current_status_snapshot', 30)->nullable();
                $table->timestamp('current_expires_at_snapshot')->nullable();
                $table->timestamp('approved_starts_at')->nullable();
                $table->timestamp('approved_ends_at')->nullable();
                $table->text('customer_note')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('admin_decision_note')->nullable();
                $table->unsignedBigInteger('processed_by_platform_admin_id')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'idx_sub_ren_company_status');
                $table->index('subscription_payment_id', 'idx_sub_ren_payment');

                $table->foreign('company_id', 'fk_sub_ren_company')->references('id')->on('companies')->restrictOnDelete();
                $table->foreign('current_plan_id', 'fk_sub_ren_curr_plan')->references('id')->on('subscription_plans')->nullOnDelete();
                $table->foreign('requested_plan_id', 'fk_sub_ren_req_plan')->references('id')->on('subscription_plans')->restrictOnDelete();
                $table->foreign('subscription_payment_id', 'fk_sub_ren_payment')->references('id')->on('subscription_payments')->nullOnDelete();
                $table->foreign('requested_by_user_id', 'fk_sub_ren_user')->references('id')->on('users')->nullOnDelete();
                $table->foreign('processed_by_platform_admin_id', 'fk_sub_ren_admin')->references('id')->on('platform_admins')->nullOnDelete();
            });
        }

        if (!$intro->hasColumn('platform_audit_logs', 'subscription_payment_id')) {
            $schema->table('platform_audit_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('subscription_payment_id')->nullable()->after('subscription_feature_id');
                $table->index('subscription_payment_id', 'idx_pal_subscription_payment');
                $table->foreign('subscription_payment_id', 'fk_pal_subscription_payment')
                    ->references('id')->on('subscription_payments')->nullOnDelete();
            });
        }

        $this->seedInitialBillingDefaults($conn);
    }

    private function seedInitialBillingDefaults($conn): void
    {
        $now = now();

        if ((int) $conn->table('payment_instructions')->count() === 0) {
            $conn->table('payment_instructions')->insert([
                [
                    'method_code' => 'BANK_BCA_MANUAL',
                    'method_name' => 'Bank Transfer — BCA (Sandbox Demo Placeholder)',
                    'channel_type' => 'BANK_TRANSFER',
                    'bank_name' => 'Bank Central Asia (BCA)',
                    'account_name' => 'PT Stockora SaaS Indonesia (Demo Placeholder)',
                    'account_number' => '000-123-4567 (Sandbox Placeholder)',
                    'instructions' => 'Transfer the exact subscription amount and include your company code in the transfer description. Upload your receipt screenshot or PDF after transfer for manual verification by Stockora Platform Admin.',
                    'instructions_id' => 'Transfer nominal paket secara tepat dan cantumkan kode perusahaan Anda pada berita transfer. Unggah bukti transfer (gambar/PDF) setelah melakukan pembayaran untuk verifikasi manual Admin Platform Stockora.',
                    'is_active' => true,
                    'is_default' => true,
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'method_code' => 'BANK_MANDIRI_MANUAL',
                    'method_name' => 'Bank Transfer — Mandiri (Sandbox Demo Placeholder)',
                    'channel_type' => 'BANK_TRANSFER',
                    'bank_name' => 'Bank Mandiri',
                    'account_name' => 'PT Stockora SaaS Indonesia (Demo Placeholder)',
                    'account_number' => '000-987-6543 (Sandbox Placeholder)',
                    'instructions' => 'Use Bank Mandiri transfer or BI-FAST. Retain your transfer reference code and submit confirmation through Stockora billing.',
                    'instructions_id' => 'Gunakan transfer Bank Mandiri atau BI-FAST. Simpan nomor referensi transfer Anda lalu kirim konfirmasi pembayaran melalui menu langganan Stockora.',
                    'is_active' => true,
                    'is_default' => false,
                    'sort_order' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'method_code' => 'QRIS_MANUAL_PLACEHOLDER',
                    'method_name' => 'Manual QRIS / Invoice Transfer (Sandbox Demo Placeholder)',
                    'channel_type' => 'QRIS_MANUAL',
                    'bank_name' => 'Manual Merchant QR / Desk',
                    'account_name' => 'Stockora Billing Desk (Demo Placeholder)',
                    'account_number' => 'STKR-DEMO-QRIS-001',
                    'instructions' => 'For demo & offline verification testing. Always upload transfer slip or reference number so admin can verify manually.',
                    'instructions_id' => 'Untuk pengujian demo & pembayaran manual. Selalu sertakan bukti atau referensi transaksi agar dapat diverifikasi admin.',
                    'is_active' => true,
                    'is_default' => false,
                    'sort_order' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        $defaultSettings = [
            'renewal_reminder_windows' => [
                'value' => json_encode([30, 7, 3]),
                'description' => 'Days before subscription expiration to trigger renewal reminder stages.',
            ],
            'allow_customer_payment_submissions' => [
                'value' => '1',
                'description' => 'Allow authenticated customer owners/billing admins to submit manual payment confirmations.',
            ],
            'payment_proof_max_kb' => [
                'value' => '5120',
                'description' => 'Maximum uploaded payment proof size in KB (server hard-capped at 5120 KB).',
            ],
            'verification_sla_note' => [
                'value' => 'Manual payment verification takes up to 1 business day during Stockora operating hours.',
                'description' => 'Customer-facing EN verification expectation note.',
            ],
            'verification_sla_note_id' => [
                'value' => 'Verifikasi pembayaran manual memerlukan waktu maksimal 1 hari kerja sesuai jam operasional Stockora.',
                'description' => 'Customer-facing ID verification expectation note.',
            ],
        ];

        foreach ($defaultSettings as $key => $meta) {
            $exists = $conn->table('platform_settings')->where('setting_key', $key)->exists();
            if (!$exists) {
                $conn->table('platform_settings')->insert([
                    'setting_key' => $key,
                    'setting_value' => $meta['value'],
                    'description' => $meta['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $schema = $conn->getSchemaBuilder();
        $intro = new SchemaIntrospector($conn);

        if ($intro->hasColumn('platform_audit_logs', 'subscription_payment_id')) {
            $intro->dropForeignKeyIfExists('platform_audit_logs', 'fk_pal_subscription_payment');
            $schema->table('platform_audit_logs', function (Blueprint $table) use ($intro) {
                if ($intro->hasIndexName('platform_audit_logs', 'idx_pal_subscription_payment')) {
                    $table->dropIndex('idx_pal_subscription_payment');
                }
                $table->dropColumn('subscription_payment_id');
            });
        }

        $schema->dropIfExists('subscription_renewal_requests');
        $schema->dropIfExists('subscription_payments');
        $schema->dropIfExists('platform_settings');
        $schema->dropIfExists('payment_instructions');
    }
};
