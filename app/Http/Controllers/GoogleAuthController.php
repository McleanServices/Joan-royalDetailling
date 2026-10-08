<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleAccountConflict;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $from = $this->origin($request);
        $request->session()->put('google_auth_from', $from);

        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()
                ->route($from)
                ->with('error', 'La connexion Google n\'est pas encore configurée.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request, RegistrationService $registration): RedirectResponse
    {
        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->fail($request, 'La connexion Google a échoué. Réessayez.');
        }

        $email = $googleUser->getEmail();
        $verified = filter_var(data_get($googleUser->user, 'email_verified'), FILTER_VALIDATE_BOOLEAN);

        if (! is_string($email) || $email === '' || ! $verified) {
            return $this->fail($request, 'Google n\'a pas confirmé cette adresse e-mail.');
        }

        try {
            $user = $registration->registerFromGoogle([
                'google_id' => (string) $googleUser->getId(),
                'email' => $email,
                'first_name' => $this->firstName($googleUser, $email),
                'last_name' => $this->lastName($googleUser),
            ]);
        } catch (GoogleAccountConflict) {
            return $this->fail($request, 'Un compte existe déjà avec cet e-mail.');
        }

        Auth::login($user, remember: true);

        return redirect()
            ->route('home')
            ->with('success', $user->wasRecentlyCreated ? 'Compte créé avec succès.' : 'Connexion réussie.');
    }

    private function firstName(SocialiteUser $googleUser, string $email): string
    {
        $given = trim((string) data_get($googleUser->user, 'given_name', ''));

        if ($given !== '') {
            return $given;
        }

        $full = trim((string) $googleUser->getName());

        if ($full !== '') {
            $parts = preg_split('/\s+/', $full, 2) ?: [];

            if (($parts[0] ?? '') !== '') {
                return (string) $parts[0];
            }
        }

        $local = strstr($email, '@', true);

        return is_string($local) && $local !== '' ? $local : 'Client';
    }

    private function lastName(SocialiteUser $googleUser): string
    {
        $family = trim((string) data_get($googleUser->user, 'family_name', ''));

        if ($family !== '') {
            return $family;
        }

        $given = trim((string) data_get($googleUser->user, 'given_name', ''));
        $full = trim((string) $googleUser->getName());

        if ($given !== '' || $full === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $full, 2) ?: [];

        return (string) ($parts[1] ?? '');
    }

    private function fail(Request $request, string $message): RedirectResponse
    {
        return redirect()
            ->route($this->storedOrigin($request))
            ->with('error', $message);
    }

    private function storedOrigin(Request $request): string
    {
        $from = $request->session()->pull('google_auth_from');

        return $from === 'login' ? 'login' : 'account';
    }

    private function origin(Request $request): string
    {
        return $request->query('from') === 'login' ? 'login' : 'account';
    }
}
