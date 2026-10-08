<?php

namespace Tests\Feature\Auth;

use App\Mail\WelcomeMail;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_google_redirect_explains_when_it_is_not_configured(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->from(route('account'))
            ->get(route('auth.google', ['from' => 'account']))
            ->assertRedirect(route('account'))
            ->assertSessionHas('error', 'La connexion Google n\'est pas encore configurée.');
    }

    public function test_google_callback_creates_an_account_without_a_phone(): void
    {
        Mail::fake();
        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'));

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'jean.google@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->phone);
        $this->assertSame('google-1', $user->google_id);
        $this->assertSame('Jean', $user->first_name);
        $this->assertSame('Dupont', $user->last_name);
        Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail) => $mail->hasTo('jean.google@example.com'));
    }

    public function test_google_callback_logs_in_an_existing_email(): void
    {
        Mail::fake();
        $existing = User::factory()->create([
            'email' => 'jean.google@example.com',
        ]);
        $this->fakeGoogleUser();

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Connexion réussie.');

        $this->assertAuthenticatedAs($existing);
        $this->assertSame('google-1', $existing->fresh()->google_id);
        Mail::assertNothingSent();
    }

    public function test_google_callback_refuses_an_unverified_email(): void
    {
        $this->fakeGoogleUser(verified: false);

        $this->withSession(['google_auth_from' => 'login'])
            ->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google n\'a pas confirmé cette adresse e-mail.');

        $this->assertGuest();
    }

    private function fakeGoogleUser(bool $verified = true): void
    {
        $googleUser = new SocialiteUser;
        $googleUser->id = 'google-1';
        $googleUser->name = 'Jean Dupont';
        $googleUser->email = 'jean.google@example.com';
        $googleUser->user = [
            'email_verified' => $verified,
            'given_name' => 'Jean',
            'family_name' => 'Dupont',
        ];

        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }
}
