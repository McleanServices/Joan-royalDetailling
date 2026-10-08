<?php

namespace App\Services;

use App\Models\User;
use App\Support\Euro;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lunar\DataTypes\Price;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Collection;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class OrderService
{
    public function add(int $variantId): void
    {
        $this->cart()->add($this->purchasableVariant($variantId), 1, [
            'reservation' => (string) Str::uuid(),
        ], refresh: false);
    }

    public function remove(int $lineId): void
    {
        $cart = CartSession::current();

        $line = $cart?->lines->firstWhere('id', $lineId);

        if ($cart === null || ! $line instanceof CartLine) {
            throw ValidationException::withMessages([
                'line_id' => 'Ce service n\'est pas dans la réservation.',
            ]);
        }

        if ((int) $line->quantity > 1) {
            $cart->updateLine($lineId, (int) $line->quantity - 1);

            return;
        }

        $cart->remove($lineId);
    }

    /**
     * @return array{lines: list<array<string, mixed>>, total: string, count: int, duration: string, tax: string}
     */
    public function summary(CatalogService $catalog): array
    {
        $cart = CartSession::current(calculate: false);
        $lines = [];
        $minutes = 0;
        $count = 0;

        if ($cart !== null) {
            $cart->load(['lines.purchasable.product.collections']);

            if ($cart->lines->isNotEmpty()) {
                $cart->calculate();
            }

            foreach ($cart->lines as $line) {
                if (! $line instanceof CartLine) {
                    continue;
                }

                $presented = $this->presentLine($line, $catalog);

                if ($presented === null) {
                    continue;
                }

                $quantity = max(1, (int) $presented['quantity']);
                $minutes += $presented['minutes'] * $quantity;
                $count += $quantity;

                for ($copy = 0; $copy < $quantity; $copy++) {
                    $row = $presented;
                    $row['key'] = $presented['id'].'-'.$copy;
                    unset($row['minutes'], $row['quantity']);
                    $lines[] = $row;
                }
            }
        }

        return [
            'lines' => $lines,
            'total' => Euro::fromStored($this->minor($cart?->total)),
            'count' => $count,
            'duration' => $this->formatMinutes($minutes),
            'tax' => '0,00 €',
        ];
    }

    /**
     * @param  array{first_name: string, last_name: string, email: string, phone: string, line_one?: string|null, postcode?: string|null, city?: string|null}  $customer
     */
    public function checkout(?User $user, bool $forgetCart = true, array $customer = []): Order
    {
        $cart = CartSession::current();

        if ($cart === null || $cart->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'variant_id' => 'Ajoutez un service avant de payer.',
            ]);
        }

        $cart->setBillingAddress($this->billingAddress($user, $customer, $this->visitIsAtHome($cart)));

        $order = CartSession::createOrder(forget: $forgetCart);

        session(['checkout.order_id' => $order->id]);

        return $order;
    }

    private function purchasableVariant(int $variantId): ProductVariant
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($variantId);

        if (! $variant->isPurchasable() || $variant->shippable) {
            throw ValidationException::withMessages([
                'variant_id' => 'Ce service ne peut pas être réservé en ligne.',
            ]);
        }

        return $variant;
    }

    private function cart(): Cart
    {
        CartSession::setCurrency(Currency::getDefault());

        $cart = CartSession::manager();

        if ($cart === null) {
            throw ValidationException::withMessages([
                'variant_id' => 'Le panier n\'a pas pu être créé.',
            ]);
        }

        return $cart;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentLine(CartLine $line, CatalogService $catalog): ?array
    {
        $variant = $line->purchasable;

        if (! $variant instanceof ProductVariant) {
            return null;
        }

        $product = $variant->product;

        if (! $product instanceof Product) {
            return null;
        }

        $collection = $product->collections->first();
        $key = $collection instanceof Collection ? (string) $collection->translateAttribute('catalog_key') : '';
        $duration = (string) $product->translateAttribute('duration');
        $quantity = max(1, (int) $line->quantity);

        return [
            'id' => $line->id,
            'variant_id' => $variant->id,
            'name' => (string) $product->translateAttribute('name'),
            'duration' => $duration,
            'location' => (string) ($product->translateAttribute('service_group') ?: 'À l\'adresse de l\'établissement'),
            'image' => $catalog->imageFor($key),
            'price' => Euro::fromStored(intdiv($this->minor($line->total), $quantity)),
            'quantity' => $quantity,
            'minutes' => $this->minutes($duration),
        ];
    }

    private function minor(mixed $price): int
    {
        if ($price instanceof Price) {
            return (int) $price->value;
        }

        return (int) $price;
    }

    private function minutes(string $duration): int
    {
        $hours = 0;
        $mins = 0;

        if (preg_match('/(\d+)\s*h(?:\s*(\d+))?/u', $duration, $match) === 1) {
            $hours = (int) $match[1];
            $mins = isset($match[2]) ? (int) $match[2] : 0;
        } elseif (preg_match('/(\d+)\s*min/u', $duration, $match) === 1) {
            $mins = (int) $match[1];
        }

        return ($hours * 60) + $mins;
    }

    private function formatMinutes(int $total): string
    {
        $hours = intdiv($total, 60);
        $mins = $total % 60;

        if ($hours > 0 && $mins > 0) {
            return $hours.' h '.$mins.' min';
        }

        if ($hours > 0) {
            return $hours.' h';
        }

        return $mins.' min';
    }

    /**
     * @param  array{first_name?: string, last_name?: string, email?: string, phone?: string, line_one?: string|null, postcode?: string|null, city?: string|null}  $customer
     * @return array<string, mixed>
     */
    private function billingAddress(?User $user, array $customer, bool $atHome): array
    {
        $country = Country::query()
            ->where('iso2', config('store.billing.country_iso'))
            ->firstOrFail();

        return [
            'country_id' => $country->id,
            'first_name' => $customer['first_name'] ?? ($user?->first_name ?: 'Client'),
            'last_name' => $customer['last_name'] ?? ($user?->last_name ?: 'Invité'),
            'line_one' => $atHome && filled($customer['line_one'] ?? null) ? $customer['line_one'] : config('store.billing.line_one'),
            'city' => $atHome && filled($customer['city'] ?? null) ? $customer['city'] : config('store.billing.city'),
            'postcode' => $atHome && filled($customer['postcode'] ?? null) ? $customer['postcode'] : config('store.billing.postcode'),
            'contact_email' => $customer['email'] ?? $user?->email,
            'contact_phone' => $customer['phone'] ?? $user?->phone,
        ];
    }

    private function visitIsAtHome(Cart $cart): bool
    {
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
