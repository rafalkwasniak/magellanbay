<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'surname' => $user->surname,
            'email' => $user->email,
        ], $overrides);
    }

    public function test_user_can_view_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Rafał']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Rafał');
    }

    public function test_user_can_update_name_and_phone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), $this->payload($user, [
                'name' => 'Nowe',
                'surname' => 'Nazwisko',
                'phone' => '500600700',
            ]))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nowe',
            'surname' => 'Nazwisko',
            'phone' => '48500600700',
        ]);
    }

    public function test_user_can_change_email(): void
    {
        $user = User::factory()->create(['email' => 'stary@example.com']);

        $this->actingAs($user)
            ->post(route('profile.update'), $this->payload($user, ['email' => 'nowy@example.com']))
            ->assertSessionHasNoErrors();

        $this->assertSame('nowy@example.com', $user->fresh()->email);
    }

    public function test_email_must_be_unique_among_platform_users(): void
    {
        User::factory()->create(['email' => 'zajety@example.com']);
        $user = User::factory()->create(['email' => 'moj@example.com']);

        $this->actingAs($user)
            ->post(route('profile.update'), $this->payload($user, ['email' => 'zajety@example.com']))
            ->assertSessionHasErrors('email');

        $this->assertSame('moj@example.com', $user->fresh()->email);
    }

    public function test_keeping_own_email_is_allowed(): void
    {
        $user = User::factory()->create(['email' => 'moj@example.com']);

        $this->actingAs($user)
            ->post(route('profile.update'), $this->payload($user, ['name' => 'Zmiana']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Zmiana', $user->fresh()->name);
    }

    public function test_password_changes_with_correct_current_password(): void
    {
        $user = User::factory()->create(); // fabryczne hasło: "password"

        $this->actingAs($user)
            ->post(route('profile.update'), $this->payload($user, [
                'current_password' => 'password',
                'password' => 'NoweHaslo1',
                'password_confirmation' => 'NoweHaslo1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NoweHaslo1', $user->fresh()->password));
    }

    public function test_password_change_rejected_with_wrong_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), $this->payload($user, [
                'current_password' => 'zle-haslo',
                'password' => 'NoweHaslo1',
                'password_confirmation' => 'NoweHaslo1',
            ]))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), $this->payload($user, [
                'password' => 'NoweHaslo1',
                'password_confirmation' => 'NoweHaslo1',
            ]))
            ->assertSessionHasErrors('current_password');
    }

    public function test_user_can_upload_and_remove_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.update'), $this->payload($user, [
                'avatar' => UploadedFile::fake()->image('avatar.png', 256, 256),
            ]))
            ->assertSessionHasNoErrors();

        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)->post(route('profile.update'), $this->payload($user, ['remove_avatar' => '1']));

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }
}
