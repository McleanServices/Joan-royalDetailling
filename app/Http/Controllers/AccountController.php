<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('store/account');
    }

    public function store(RegisterRequest $request, RegistrationService $registration): RedirectResponse
    {
        $data = $request->safe()->only([
            'first_name',
            'last_name',
            'email',
            'password',
        ]);

        $user = $registration->register([
            'first_name' => (string) $data['first_name'],
            'last_name' => (string) $data['last_name'],
            'email' => (string) $data['email'],
            'password' => (string) $data['password'],
        ]);

        Auth::login($user);

        return redirect()
            ->route('home')
            ->with('success', 'Compte créé avec succès.');
    }
}
