<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_an_active_user_can_authenticate_with_a_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'admin',
            'email' => null,
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password')
            ->set('data.remember', true)
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_an_unknown_username_cannot_authenticate(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'email' => null,
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->set('data.username', 'unknown-user')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest('web');
    }

    public function test_an_incorrect_password_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'username' => 'admin',
            'email' => null,
            'password' => 'password',
        ]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'incorrect-password')
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest('web');
    }

    public function test_an_inactive_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create([
            'username' => 'inactive-admin',
            'email' => null,
            'password' => 'password',
            'is_active' => false,
        ]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest('web');
    }
}
