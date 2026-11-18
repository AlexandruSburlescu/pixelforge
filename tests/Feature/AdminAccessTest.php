<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userOfType(string $slug): User
    {
        $user = User::factory()->create();
        $user->user_type_id = UserType::where('slug', $slug)->value('id');
        $user->save();

        return $user;
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_regular_users_are_forbidden(): void
    {
        $this->actingAs($this->userOfType(UserType::USER))->get('/admin')->assertForbidden();
    }

    public function test_admins_and_super_admins_get_in(): void
    {
        $this->actingAs($this->userOfType(UserType::ADMIN))->get('/admin')->assertOk();
        $this->actingAs($this->userOfType(UserType::SUPER_ADMIN))->get('/admin')->assertOk();
    }

    public function test_admin_resource_pages_render(): void
    {
        $admin = $this->userOfType(UserType::SUPER_ADMIN);

        $this->actingAs($admin)->get('/admin/resource/user-resource/user-index-page')->assertOk();
        $this->actingAs($admin)->get('/admin/resource/user-type-resource/user-type-index-page')->assertOk();
    }

    public function test_a_new_type_is_ranked_by_level(): void
    {
        UserType::create(['slug' => 'support', 'name' => 'Support', 'level' => 30, 'is_admin' => false]);
        $support = $this->userOfType('support');

        $this->assertTrue($support->hasTypeAtLeast(UserType::USER));
        $this->assertFalse($support->hasTypeAtLeast(UserType::ADMIN));
    }

    public function test_user_type_and_balance_cannot_be_mass_assigned(): void
    {
        // Strict mode (non-production) refuses loudly instead of silently dropping.
        $this->expectException(MassAssignmentException::class);

        User::create([
            'name' => 'x', 'email' => 'x@example.com', 'password' => 'secret-password',
            'user_type_id' => 3, 'balance_nano' => 999,
        ]);
    }

    public function test_new_users_start_as_user_type_with_zero_balance(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame(UserType::USER, $user->userType->slug);
        $this->assertSame(0, $user->balance_nano);
    }
}
