<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Lunar\Facades\CartSession;
use Lunar\Models\CartLine;
use Lunar\Models\ProductVariant;

class PayReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $atHome = $this->visitIsAtHome();

        return [
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = preg_replace('/\D/', '', (string) $value) ?? '';

                if (strlen($digits) < 8 || strlen($digits) > 15) {
                    $fail('Le téléphone n\'est pas valide.');
                }
            }],
            'line_one' => [$atHome ? 'required' : 'nullable', 'string', 'max:255'],
            'postcode' => [$atHome ? 'required' : 'nullable', 'string', 'regex:/^\d{5}$/'],
            'city' => [$atHome ? 'required' : 'nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
            'email.required' => 'L\'e-mail est obligatoire.',
            'email.email' => 'L\'e-mail n\'est pas valide.',
            'phone.required' => 'Le téléphone est obligatoire.',
            'line_one.required' => 'L\'adresse est obligatoire.',
            'postcode.required' => 'Le code postal est obligatoire.',
            'postcode.regex' => 'Le code postal n\'est pas valide.',
            'city.required' => 'La ville est obligatoire.',
        ];
    }

    private function visitIsAtHome(): bool
    {
        $cart = CartSession::current(calculate: false);

        if ($cart === null) {
            return false;
        }

        $cart->loadMissing(['lines.purchasable.product']);

        foreach ($cart->lines as $line) {
            if (! $line instanceof CartLine || ! $line->purchasable instanceof ProductVariant) {
                continue;
            }

            $place = mb_strtolower((string) $line->purchasable->product?->translateAttribute('service_group'));

            if (str_contains($place, 'domicile')) {
                return true;
            }
        }

        return false;
    }
}
