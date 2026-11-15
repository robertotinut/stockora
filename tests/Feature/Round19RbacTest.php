<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockAdjustment;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\CompanyInvitationNotification;
use App\Services\InvitationService;
use App\Services\PermissionRegistry;
use App\Services\RbacService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Round19RbacTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        app(RbacService::class)->clearCache();
        parent::tearDown();
    }

    private function createWorkspaceWithOwner(?string $suffix = null): array
    {
        $tag = $suffix ?? strtoupper(substr(uniqid(), -5));

        $owner = User::factory()->create([
            'email' => "owner-{$tag}@stockoratest.com",
            'name' => "Owner {$tag}",
        ]);

        $enterprisePlan = SubscriptionPlan::where('code', Company::ACCESS_ENTERPRISE)->first();

        $company = Company::create([
            'code' => "R19{$tag}",
            'name' => "R19 Company {$tag}",
            'is_active' => true,
            'account_status' => Company::STATUS_ACTIVE,
            'access_type' => $enterprisePlan?->tier ?? Company::ACCESS_ENTERPRISE,
            'subscription_plan_id' => $enterprisePlan?->id,
        ]);

        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouse = app(TenantContext::class)->withoutScope(function () use ($company) {
            return Warehouse::create([
                'company_id' => $company->id,
                'code' => 'WH-MAIN',
                'name' => 'Main Warehouse',
                'is_default' => true,
                'is_active' => true,
            ]);
        });

        return [$company, $owner, $warehouse];
    }

    private function addMember(Company $company, string $role, bool $active = true, ?string $emailPrefix = null): array
    {
        $tag = uniqid();
        $user = User::factory()->create([
            'email' => ($emailPrefix ?? $role) . "-{$tag}@stockoratest.com",
            'name' => ucfirst($role) . " {$tag}",
        ]);

        $membershipId = DB::table('company_user')->insertGetId([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_default' => 1,
            'is_active' => $active ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $membershipId];
    }

    public function test_owner_can_view_team_invitations_and_permissions_pages(): void
    {
        [$company, $owner] = $this->createWorkspaceWithOwner('VIEW');
        [$staff] = $this->addMember($company, 'staff');

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('team.index'))
            ->assertOk()
            ->assertSee($owner->email)
            ->assertSee($staff->email);

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('team.invitations.index'))
            ->assertOk()
            ->assertSee('Invite Colleague');

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('team.permissions.index', ['role' => 'staff']))
            ->assertOk()
            ->assertSee('Staff Module Permissions');
    }

    public function test_owner_invite_stores_only_token_hash_dispatches_mail_and_accepts_once(): void
    {
        Notification::fake();

        [$company, $owner] = $this->createWorkspaceWithOwner('INV');
        $inviteeEmail = 'newemp-' . uniqid() . '@stockoratest.com';

        $response = $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('team.invitations.store'), [
                'email' => strtoupper($inviteeEmail),
                'role' => 'staff',
                'personal_message' => 'Welcome aboard!',
            ]);

        $response->assertRedirect(route('team.invitations.index'));

        $invitation = CompanyInvitation::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('email', $inviteeEmail)
            ->first();

        $this->assertNotNull($invitation);
        $this->assertSame('pending', $invitation->status);
        $this->assertSame('staff', $invitation->role);
        $this->assertSame(64, strlen($invitation->token_hash));

        $capturedUrl = null;
        Notification::assertSentOnDemand(
            CompanyInvitationNotification::class,
            function (CompanyInvitationNotification $notification, array $channels, object $notifiable) use ($inviteeEmail, &$capturedUrl) {
                if (($notifiable->routes['mail'] ?? null) !== $inviteeEmail) {
                    return false;
                }
                $capturedUrl = $notification->toMail($notifiable)->actionUrl;
                return true;
            }
        );

        $this->assertNotNull($capturedUrl);
        $rawToken = basename(parse_url($capturedUrl, PHP_URL_PATH));
        $this->assertNotSame($rawToken, $invitation->token_hash);
        $this->assertSame(hash('sha256', $rawToken), $invitation->token_hash);

        $invitee = User::factory()->create(['email' => $inviteeEmail]);

        $this->actingAs($invitee)
            ->get(route('invitations.show', ['token' => $rawToken]))
            ->assertOk()
            ->assertSee("Join {$company->name}");

        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $rawToken]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_company_id', $company->id);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $invitee->id,
            'role' => 'staff',
            'is_active' => 1,
        ]);

        // Re-using an accepted token is rejected.
        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $rawToken]))
            ->assertRedirect(route('invitations.show', ['token' => $rawToken]))
            ->assertSessionHas('error');
    }

    public function test_guest_opening_invitation_can_register_and_accept_into_invited_company(): void
    {
        Notification::fake();

        [$company, $owner] = $this->createWorkspaceWithOwner('REGINV');
        $inviteeEmail = 'invitedguest-' . uniqid() . '@stockoratest.com';

        $created = app(InvitationService::class)->createInvitation(
            $company->id,
            $owner,
            $inviteeEmail,
            'viewer'
        );

        $rawToken = basename(parse_url($created['accept_url'], PHP_URL_PATH));

        $this->get(route('invitations.show', ['token' => $rawToken]))
            ->assertOk()
            ->assertSessionHas('pending_invitation_email', $inviteeEmail)
            ->assertSessionHas('pending_invitation_url', route('invitations.show', ['token' => $rawToken]));

        // Register redirects straight back to invitation acceptance card instead of blank company wizard.
        $regResponse = $this->post('/register', [
            'name' => 'Invited Analyst',
            'email' => $inviteeEmail,
            'password' => 'Stockora2026!',
            'password_confirmation' => 'Stockora2026!',
            'agree_terms' => '1',
        ]);

        $regResponse->assertRedirect(route('invitations.show', ['token' => $rawToken]));
        $user = User::where('email', $inviteeEmail)->firstOrFail();

        $this->actingAs($user)
            ->post(route('invitations.accept', ['token' => $rawToken]))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_company_id', $company->id);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'is_active' => 1,
        ]);
        $this->assertSame(1, DB::table('company_user')->where('user_id', $user->id)->count());
    }

    public function test_invitation_rejects_expired_revoked_duplicate_existing_member_and_mismatched_email(): void
    {
        Notification::fake();

        [$company, $owner] = $this->createWorkspaceWithOwner('INVSEC');
        [$existingMember] = $this->addMember($company, 'staff');

        // Existing member invite rejected
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('team.invitations.store'), [
                'email' => $existingMember->email,
                'role' => 'viewer',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // Create valid pending invite
        $email = 'pendingcheck-' . uniqid() . '@stockoratest.com';
        $created = app(InvitationService::class)->createInvitation($company->id, $owner, $email, 'staff');
        $rawToken = basename(parse_url($created['accept_url'], PHP_URL_PATH));

        // Duplicate pending rejected
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('team.invitations.store'), [
                'email' => $email,
                'role' => 'manager',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // Mismatched logged-in email rejected 403 on accept
        $otherUser = User::factory()->create(['email' => 'wrong-' . uniqid() . '@stockoratest.com']);
        $this->actingAs($otherUser)
            ->post(route('invitations.accept', ['token' => $rawToken]))
            ->assertForbidden();

        // Revoked invite rejected
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->delete(route('team.invitations.destroy', $created['invitation']->id))
            ->assertRedirect(route('team.invitations.index'));

        $intendedUser = User::factory()->create(['email' => $email]);
        $this->actingAs($intendedUser)
            ->post(route('invitations.accept', ['token' => $rawToken]))
            ->assertRedirect(route('invitations.show', ['token' => $rawToken]))
            ->assertSessionHas('error');

        // Expired invite rejected
        $email2 = 'expiring-' . uniqid() . '@stockoratest.com';
        $created2 = app(InvitationService::class)->createInvitation($company->id, $owner, $email2, 'staff');
        $rawToken2 = basename(parse_url($created2['accept_url'], PHP_URL_PATH));

        CompanyInvitation::withoutGlobalScopes()
            ->where('id', $created2['invitation']->id)
            ->update(['expires_at' => now()->subMinute()]);

        $user2 = User::factory()->create(['email' => $email2]);
        $this->actingAs($user2)
            ->post(route('invitations.accept', ['token' => $rawToken2]))
            ->assertRedirect(route('invitations.show', ['token' => $rawToken2]))
            ->assertSessionHas('error');
    }

    public function test_invitation_cannot_be_accepted_after_inviter_loses_invite_authority(): void
    {
        Notification::fake();

        [$company, $owner] = $this->createWorkspaceWithOwner('DEM');
        [$admin] = $this->addMember($company, 'admin');
        $inviteeEmail = 'laterdemoted-' . uniqid() . '@stockoratest.com';

        $created = app(InvitationService::class)->createInvitation($company->id, $admin, $inviteeEmail, 'staff');
        $rawToken = basename(parse_url($created['accept_url'], PHP_URL_PATH));

        // Demote admin -> viewer after issuing invite
        DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $admin->id)
            ->update(['role' => 'viewer']);

        $invitee = User::factory()->create(['email' => $inviteeEmail]);
        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $rawToken]))
            ->assertForbidden();

        $this->assertDatabaseMissing('company_user', [
            'company_id' => $company->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_owner_can_assign_roles_toggle_status_and_remove_members_but_last_owner_is_protected(): void
    {
        [$company, $owner] = $this->createWorkspaceWithOwner('MEM');
        [$staff, $staffMembershipId] = $this->addMember($company, 'staff');

        $ownerMembershipId = (int) DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $owner->id)
            ->value('id');

        // Last active owner cannot be demoted, deactivated, or removed
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->patch(route('team.members.role', $ownerMembershipId), ['role' => 'admin'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->patch(route('team.members.status', $ownerMembershipId), ['is_active' => 0])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->delete(route('team.members.destroy', $ownerMembershipId))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('company_user', [
            'id' => $ownerMembershipId,
            'role' => 'owner',
            'is_active' => 1,
        ]);

        // Owner promotes staff -> manager, deactivates, reactivates, then removes
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->patch(route('team.members.role', $staffMembershipId), ['role' => 'manager'])
            ->assertRedirect(route('team.show', $staffMembershipId));

        $this->assertDatabaseHas('company_user', ['id' => $staffMembershipId, 'role' => 'manager']);

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->patch(route('team.members.status', $staffMembershipId), ['is_active' => 0])
            ->assertRedirect();
        $this->assertDatabaseHas('company_user', ['id' => $staffMembershipId, 'is_active' => 0]);

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->patch(route('team.members.status', $staffMembershipId), ['is_active' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('company_user', ['id' => $staffMembershipId, 'is_active' => 1]);

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->delete(route('team.members.destroy', $staffMembershipId))
            ->assertRedirect(route('team.index'));
        $this->assertDatabaseMissing('company_user', ['id' => $staffMembershipId]);
    }

    public function test_ownership_transfer_requires_owner_and_confirmation_code_and_demotes_former_owner(): void
    {
        [$company, $owner] = $this->createWorkspaceWithOwner('XFER');
        [$admin, $adminMembershipId] = $this->addMember($company, 'admin');
        [$staff, $staffMembershipId] = $this->addMember($company, 'staff');

        // Admin cannot transfer ownership
        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('team.transfer-ownership'), [
                'target_member_id' => $adminMembershipId,
                'confirm_transfer' => '1',
                'confirm_company_code' => $company->code,
            ])
            ->assertForbidden();

        // Wrong company code rejected by validation
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('team.transfer-ownership'), [
                'target_member_id' => $adminMembershipId,
                'confirm_transfer' => '1',
                'confirm_company_code' => 'WRONG-CODE',
            ])
            ->assertSessionHasErrors('confirm_company_code');

        // Valid transfer by owner succeeds and demotes previous owner to admin
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('team.transfer-ownership'), [
                'target_member_id' => $adminMembershipId,
                'confirm_transfer' => '1',
                'confirm_company_code' => $company->code,
            ])
            ->assertRedirect(route('team.index'));

        $this->assertDatabaseHas('company_user', [
            'id' => $adminMembershipId,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'admin',
        ]);
    }

    public function test_admin_cannot_escalate_to_owner_modify_owner_or_edit_admin_permissions(): void
    {
        [$company, $owner] = $this->createWorkspaceWithOwner('ADM');
        [$admin, $adminMembershipId] = $this->addMember($company, 'admin');
        [$staff, $staffMembershipId] = $this->addMember($company, 'staff');

        $ownerMembershipId = (int) DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $owner->id)
            ->value('id');

        // Assigning 'owner' directly via updateRole fails validation
        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->patch(route('team.members.role', $staffMembershipId), ['role' => 'owner'])
            ->assertSessionHasErrors('role');

        // Admin cannot modify owner membership role, status, or remove owner
        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->patch(route('team.members.role', $ownerMembershipId), ['role' => 'viewer'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->patch(route('team.members.status', $ownerMembershipId), ['is_active' => 0])
            ->assertForbidden();

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->delete(route('team.members.destroy', $ownerMembershipId))
            ->assertForbidden();

        // Admin cannot modify admin permissions or owner permissions
        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('team.permissions.update', 'admin'), ['permissions' => []])
            ->assertForbidden();

        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('team.permissions.update', 'owner'), ['permissions' => []])
            ->assertForbidden();

        // Admin CAN update manager/staff/viewer permissions (as long as admin has team_management.manage)
        $this->actingAs($admin)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('team.permissions.update', 'viewer'), [
                'permissions' => [
                    'dashboard' => ['view' => '1'],
                    'inventory_reports' => ['view' => '1', 'export' => '1'],
                ],
            ])
            ->assertRedirect(route('team.permissions.index', ['role' => 'viewer']));

        $this->assertTrue(app(RbacService::class)->canAccessModule($staff->id ? $admin : $owner, $company->id, 'team_management', 'manage'));
    }

    public function test_manager_settings_restriction_and_immediate_permission_override_per_company(): void
    {
        [$companyA, $ownerA] = $this->createWorkspaceWithOwner('MGA');
        [$companyB, $ownerB] = $this->createWorkspaceWithOwner('MGB');
        [$managerA] = $this->addMember($companyA, 'manager');
        [$managerB] = $this->addMember($companyB, 'manager');

        // Default manager cannot view/create/edit Company Settings or Team Management
        $this->actingAs($managerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('companies.index'))
            ->assertForbidden();

        $this->actingAs($managerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('companies.create'))
            ->assertForbidden();

        $this->actingAs($managerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('companies.edit', $companyA))
            ->assertForbidden();

        $this->actingAs($managerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('team.index'))
            ->assertForbidden();

        // Owner A grants company_settings.view & edit to Manager role in Company A only
        $managerDefaults = [];
        foreach (PermissionRegistry::MODULES as $mod => $meta) {
            foreach ($meta['actions'] as $act) {
                if (PermissionRegistry::defaultAllows('manager', $mod, $act)) {
                    $managerDefaults[$mod][$act] = '1';
                }
            }
        }
        $managerDefaults['company_settings']['view'] = '1';
        $managerDefaults['company_settings']['edit'] = '1';

        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->put(route('team.permissions.update', 'manager'), ['permissions' => $managerDefaults])
            ->assertRedirect();

        // Immediate effect for Manager A without re-login
        $this->actingAs($managerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('companies.edit', $companyA))
            ->assertOk();

        // Company B manager remains unaffected (tenant-isolated permission overrides)
        $this->actingAs($managerB)
            ->withSession(['active_company_id' => $companyB->id])
            ->get(route('companies.edit', $companyB))
            ->assertForbidden();
    }

    public function test_staff_allowed_operations_vs_restricted_admin_approve_delete_and_export_actions(): void
    {
        [$company, $owner, $warehouse] = $this->createWorkspaceWithOwner('STF');
        [$staff] = $this->addMember($company, 'staff');

        [$unit, $category, $product, $supplier, $po] = app(TenantContext::class)->withoutScope(function () use ($company, $owner) {
            $unit = Unit::create(['company_id' => $company->id, 'name' => 'Piece', 'symbol' => 'pcs']);
            $category = Category::create(['company_id' => $company->id, 'code' => 'GEN', 'name' => 'General']);
            $product = Product::create([
                'company_id' => $company->id,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'code' => 'SKU-STF-1',
                'name' => 'Staff Item',
                'stock' => '0.0000',
                'min_stock' => '1.0000',
                'moving_average_cost' => '0.000000',
                'inventory_value' => '0.000000',
                'legacy_reconciliation_status' => 'HISTORICALLY_VERIFIED',
            ]);
            $supplier = Supplier::create(['company_id' => $company->id, 'code' => 'SUP1', 'name' => 'Vendor One']);
            $po = Purchase::create([
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
                'user_id' => $owner->id,
                'purchase_number' => 'PO-STF-1',
                'date' => now()->toDateString(),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'total_amount' => '1000.00',
            ]);

            return [$unit, $category, $product, $supplier, $po];
        });

        // Allowed for STAFF: view dashboard/products/reports + open operational create screens
        $this->actingAs($staff)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Team Management');

        $this->actingAs($staff)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('purchases.create'))
            ->assertOk();

        // STAFF can save a draft adjustment
        $this->actingAs($staff)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('adjustments.store'), [
                'adjustment_number' => 'ADJ-STF-001',
                'warehouse_id' => $warehouse->id,
                'idempotency_key' => 'stf-draft-' . uniqid(),
                'reason' => 'Draft opname count',
                'submit_action' => 'draft',
                'lines' => [
                    ['product_id' => $product->id, 'direction' => 'in', 'quantity' => '5', 'unit_cost' => '10000'],
                ],
            ])
            ->assertRedirect();

        $draft = StockAdjustment::withoutGlobalScopes()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('draft', $draft->status);

        // STAFF cannot direct-post an adjustment (requires approve)
        $this->actingAs($staff)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('adjustments.store'), [
                'adjustment_number' => 'ADJ-STF-002',
                'warehouse_id' => $warehouse->id,
                'idempotency_key' => 'stf-post-' . uniqid(),
                'reason' => 'Unauthorized direct post',
                'submit_action' => 'post',
                'lines' => [
                    ['product_id' => $product->id, 'direction' => 'in', 'quantity' => '5', 'unit_cost' => '10000'],
                ],
            ])
            ->assertForbidden();

        // Blocked for STAFF: team, company settings, product create/delete, PO confirm, CSV export, adjustment postDraft
        $this->actingAs($staff)->withSession(['active_company_id' => $company->id])->get(route('team.index'))->assertForbidden();
        $this->actingAs($staff)->withSession(['active_company_id' => $company->id])->get(route('products.create'))->assertForbidden();
        $this->actingAs($staff)->withSession(['active_company_id' => $company->id])->delete(route('products.destroy', $product))->assertForbidden();
        $this->actingAs($staff)->withSession(['active_company_id' => $company->id])->post(route('purchases.confirm', $po))->assertForbidden();
        $this->actingAs($staff)->withSession(['active_company_id' => $company->id])->post(route('adjustments.post', $draft))->assertForbidden();
        $this->actingAs($staff)->withSession(['active_company_id' => $company->id])->get(route('reports.export', ['type' => 'valuation']))->assertForbidden();
    }

    public function test_viewer_is_strictly_read_only_across_web_forms_json_requests_and_exports(): void
    {
        [$company, $owner, $warehouse] = $this->createWorkspaceWithOwner('VWR');
        [$viewer] = $this->addMember($company, 'viewer');

        [$unit, $category, $product] = app(TenantContext::class)->withoutScope(function () use ($company) {
            $unit = Unit::create(['company_id' => $company->id, 'name' => 'Box', 'symbol' => 'box']);
            $category = Category::create(['company_id' => $company->id, 'code' => 'CATV', 'name' => 'Cat View']);
            $product = Product::create([
                'company_id' => $company->id,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'code' => 'SKU-VWR-1',
                'name' => 'Viewer Product',
                'stock' => '10.0000',
                'min_stock' => '1.0000',
                'moving_average_cost' => '5000.000000',
                'inventory_value' => '50000.000000',
                'legacy_reconciliation_status' => 'HISTORICALLY_VERIFIED',
            ]);

            return [$unit, $category, $product];
        });

        // Permitted reads return 200 without Create/Delete/Export UI buttons
        $this->actingAs($viewer)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Add Product');

        $this->actingAs($viewer)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee('CSV Valuation');

        // All create, edit, delete, approve, export, settings & team routes blocked with 403
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->get(route('team.index'))->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->get(route('products.create'))->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->post(route('products.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->put(route('products.update', $product), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->delete(route('products.destroy', $product))->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->get(route('purchases.create'))->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->get(route('grns.create'))->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->get(route('transfers.create'))->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->get(route('issues.create'))->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->get(route('adjustments.create'))->assertForbidden();
        $this->actingAs($viewer)->withSession(['active_company_id' => $company->id])->get(route('reports.export', ['type' => 'valuation']))->assertForbidden();

        // JSON FormRequest inventory endpoints blocked 403
        $this->actingAs($viewer)
            ->withSession(['active_company_id' => $company->id])
            ->postJson(route('inventory.issues.store'), [
                'warehouse_id' => $warehouse->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_inactive_member_is_immediately_blocked_from_all_company_routes(): void
    {
        [$company, $owner] = $this->createWorkspaceWithOwner('INACT');
        [$staff] = $this->addMember($company, 'staff', active: false);

        // Even with stale active_company_id in session, EnsureActiveCompany blocks 403 without falling back to onboarding
        $this->actingAs($staff)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('dashboard'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['active_company_id' => $company->id])
            ->getJson(route('products.index'))
            ->assertForbidden();
    }

    public function test_cross_tenant_isolation_blocks_managing_other_company_members_and_invitations(): void
    {
        Notification::fake();

        [$companyA, $ownerA] = $this->createWorkspaceWithOwner('ISOA');
        [$companyB, $ownerB] = $this->createWorkspaceWithOwner('ISOB');
        [$memberB, $memberBId] = $this->addMember($companyB, 'staff');

        $inviteB = app(InvitationService::class)->createInvitation(
            $companyB->id,
            $ownerB,
            'other-' . uniqid() . '@stockoratest.com',
            'staff'
        )['invitation'];

        // Owner A active in Company A receives 404 when targeting Company B member or invitation
        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('team.show', $memberBId))
            ->assertNotFound();

        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->patch(route('team.members.role', $memberBId), ['role' => 'viewer'])
            ->assertNotFound();

        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->post(route('team.invitations.resend', $inviteB->id))
            ->assertNotFound();

        $this->actingAs($ownerA)
            ->withSession(['active_company_id' => $companyA->id])
            ->delete(route('team.invitations.destroy', $inviteB->id))
            ->assertNotFound();
    }

    public function test_permission_reset_restores_role_defaults_immediately_and_rotates_resend_token(): void
    {
        Notification::fake();

        [$company, $owner] = $this->createWorkspaceWithOwner('RST');
        [$viewer] = $this->addMember($company, 'viewer');

        // Grant viewer reports.export override -> immediate 200 -> reset -> immediate 403
        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->put(route('team.permissions.update', 'viewer'), [
                'permissions' => [
                    'dashboard' => ['view' => '1'],
                    'inventory_reports' => ['view' => '1', 'export' => '1'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($viewer)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('reports.export', ['type' => 'valuation']))
            ->assertOk();

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('team.permissions.reset', 'viewer'))
            ->assertRedirect();

        $this->actingAs($viewer)
            ->withSession(['active_company_id' => $company->id])
            ->get(route('reports.export', ['type' => 'valuation']))
            ->assertForbidden();

        // Resend invitation rotates token hash and invalidates prior raw token
        $email = 'resendcheck-' . uniqid() . '@stockoratest.com';
        $first = app(InvitationService::class)->createInvitation($company->id, $owner, $email, 'staff');
        $firstToken = basename(parse_url($first['accept_url'], PHP_URL_PATH));

        $this->actingAs($owner)
            ->withSession(['active_company_id' => $company->id])
            ->post(route('team.invitations.resend', $first['invitation']->id))
            ->assertRedirect(route('team.invitations.index'));

        // Old token now returns 404 on landing
        $this->get(route('invitations.show', ['token' => $firstToken]))
            ->assertNotFound();
    }

    public function test_multi_company_member_switches_roles_cleanly_across_workspaces(): void
    {
        [$companyA, $ownerA] = $this->createWorkspaceWithOwner('MCA');
        [$companyB, $ownerB] = $this->createWorkspaceWithOwner('MCB');
        $dualUser = User::factory()->create(['email' => 'dual-' . uniqid() . '@stockoratest.com']);

        DB::table('company_user')->insert([
            [
                'company_id' => $companyA->id,
                'user_id' => $dualUser->id,
                'role' => 'owner',
                'is_default' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $companyB->id,
                'user_id' => $dualUser->id,
                'role' => 'viewer',
                'is_default' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // As OWNER in Company A: can access Team Management
        $this->actingAs($dualUser)
            ->withSession(['active_company_id' => $companyA->id])
            ->get(route('team.index'))
            ->assertOk();

        // Switch to Company B -> now VIEWER -> Team Management forbidden
        $this->actingAs($dualUser)
            ->post(route('company.switch'), ['company_id' => $companyB->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_company_id', $companyB->id);

        $this->actingAs($dualUser)
            ->withSession(['active_company_id' => $companyB->id])
            ->get(route('team.index'))
            ->assertForbidden();
    }
}
