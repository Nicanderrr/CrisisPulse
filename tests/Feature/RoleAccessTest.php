<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_staff_cannot_open_user_management(): void
    {
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_system_admin_can_open_user_management(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'system_admin']))
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('User Accounts');
    }

    public function test_system_admin_can_upload_login_media(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create(['role' => 'system_admin']))
            ->post(route('admin.settings.login-media.update'), [
                'login_media' => UploadedFile::fake()->image('hospital-login.png'),
            ])
            ->assertRedirect();

        $path = SystemSetting::valueFor('login_media_path');

        $this->assertSame('image', SystemSetting::valueFor('login_media_type'));
        Storage::disk('public')->assertExists($path);
    }
}
