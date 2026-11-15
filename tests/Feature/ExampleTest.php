<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_unassigned_authenticated_user_gets_403_on_dashboard(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/dashboard');
        $response->assertStatus(403);
    }

    public function test_assigned_tenant_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $company = Company::where('code', 'DEFAULT')->firstOrFail();
        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'is_default' => 1,
            'is_active' => 1,
        ]);

        $response = $this->actingAs($user)->getJson('/dashboard');
        $response->assertStatus(200)->assertJsonPath('company_id', (int) $company->id);
    }
}
