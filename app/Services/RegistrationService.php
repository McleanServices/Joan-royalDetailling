<?php

namespace App\Services;

use App\Exceptions\GoogleAccountConflict;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;

class RegistrationService
{
    /**
     * @param  array{google_id: string, email: string, first_name: string, last_name: string}  $data
     */
    public function registerFromGoogle(array $data): User
    {
        $byGoogle = User::query()->where('google_id', $data['google_id'])->first();
        $byEmail = User::query()->where('email', $data['email'])->first();

        if ($byGoogle && $byEmail && $byGoogle->isNot($byEmail)) {
            throw new GoogleAccountConflict;
        }

        $existing = $byGoogle ?? $byEmail;

        if ($existing) {
            if ($existing->google_id !== null && $existing->google_id !== $data['google_id']) {
                throw new GoogleAccountConflict;
            }

            $existing->forceFill([
                'google_id' => $data['google_id'],
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ])->save();

            return $existing;
        }

        return $this->register([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Str::password(48),
            'google_id' => $data['google_id'],
        ]);
    }

    /**
     * @param  array{first_name: string, last_name: string, email: string, phone?: string|null, password: string, google_id?: string|null}  $data
     */
    public function register(array $data): User
    {
        $lastName = trim($data['last_name']);

        $user = User::create([
            'first_name' => Str::limit(trim($data['first_name']), 150, ''),
            'last_name' => $lastName === '' ? null : Str::limit($lastName, 150, ''),
            'name' => Str::limit(trim($data['first_name'].' '.$data['last_name']), 255, ''),
            'email' => $data['email'],
            'google_id' => $data['google_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'email_verified_at' => now(),
        ]);

        $customer = Customer::create([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'meta' => ['phone' => $user->phone],
        ]);

        $user->customers()->attach($customer);

        $group = CustomerGroup::query()->where('default', true)->first();

        if ($group) {
            $customer->customerGroups()->attach($group);
        }

        Mail::to($user->email)->send(new WelcomeMail($user));

        return $user;
    }
}
