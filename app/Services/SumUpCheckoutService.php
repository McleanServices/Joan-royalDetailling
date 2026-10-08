<?php

namespace App\Services;

use App\Services\SumUp\LaravelHttpClient;
use App\Support\Euro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use SumUp\Exception\SDKException;
use SumUp\HttpClient\RequestOptions;
use SumUp\SumUp;
use SumUp\Types\CheckoutSuccess;

class SumUpCheckoutService
{
    public function ensureConfigured(): void
    {
        $key = (string) config('services.sumup.key');

        if (blank($key) || blank(config('services.sumup.merchant_code'))) {
            throw ValidationException::withMessages([
                'payment' => 'Le paiement est indisponible pour le moment.',
            ]);
        }

        if (str_starts_with($key, 'sup_pk_')) {
            throw ValidationException::withMessages([
                'payment' => 'La clé SumUp est une clé publique. Collez la clé secrète du sandbox, celle qui commence par sup_sk_.',
            ]);
        }
    }

    public function start(Order $order): string
    {
        $this->ensureConfigured();

        $existing = $this->hostedUrl($order);

        if ($existing !== null && $this->checkoutId($order) !== null) {
            return $existing;
        }

        try {
            $checkout = $this->client()->checkouts()->create(
                [
                    'checkout_reference' => $this->reference($order),
                    'amount' => Euro::toDecimal($order->getAttributes()['total'] ?? 0),
                    'currency' => 'EUR',
                    'merchant_code' => (string) config('services.sumup.merchant_code'),
                    'description' => $this->description($order),
                    'redirect_url' => route('checkout', $order),
                    'return_url' => route('checkout.notify'),
                    'hosted_checkout' => ['enabled' => true],
                ],
                $this->options(),
            );
        } catch (SDKException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'payment' => $this->paymentError($exception),
            ]);
        }

        $url = $this->sumUpUrl((string) $checkout->hostedCheckoutUrl);
        $id = (string) $checkout->id;

        if ($url === null || $id === '') {
            throw ValidationException::withMessages([
                'payment' => 'Le paiement est indisponible pour le moment.',
            ]);
        }

        $this->remember($order, $id, $url);

        return $url;
    }

    /**
     * Ask SumUp for the checkout status. Mark the order paid only when SumUp says PAID and the amount matches.
     */
    public function settle(Order $order): string
    {
        $checkoutId = $this->checkoutId($order);

        if ($checkoutId === null) {
            return 'missing';
        }

        if ($order->status === 'payment-received') {
            return 'paid';
        }

        $remote = $this->client()->checkouts()->get($checkoutId, $this->options());
        $status = strtoupper((string) $remote->status);

        if ($status !== 'PAID') {
            return match ($status) {
                'FAILED' => 'failed',
                'EXPIRED' => 'expired',
                default => 'pending',
            };
        }

        if (! $this->matches($order, $remote)) {
            Log::warning('SumUp checkout did not match the order.', [
                'order_id' => $order->id,
                'checkout_reference' => $remote->checkoutReference,
            ]);

            return 'mismatch';
        }

        $this->markPaid($order, $checkoutId);

        return 'paid';
    }

    public function hostedUrl(Order $order): ?string
    {
        $url = $order->meta['sumup_hosted_url'] ?? null;

        return is_string($url) ? $this->sumUpUrl($url) : null;
    }

    public function findByCheckoutId(string $checkoutId): ?Order
    {
        if ($checkoutId === '') {
            return null;
        }

        $order = Order::query()->where('meta->sumup_checkout_id', $checkoutId)->first();

        return $order instanceof Order ? $order : null;
    }

    private function markPaid(Order $order, string $checkoutId): void
    {
        DB::transaction(function () use ($order, $checkoutId): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked instanceof Order || $locked->status === 'payment-received') {
                return;
            }

            if ($locked->transactions()->where('reference', $checkoutId)->where('success', true)->exists()) {
                return;
            }

            $locked->transactions()->create([
                'success' => true,
                'type' => 'capture',
                'driver' => 'sumup',
                'amount' => (int) ($locked->getAttributes()['total'] ?? 0),
                'reference' => $checkoutId,
                'status' => 'paid',
                'card_type' => 'sumup',
                'captured_at' => now(),
            ]);

            $locked->forceFill([
                'status' => 'payment-received',
                'placed_at' => $locked->placed_at ?? now(),
            ])->save();
        });
    }

    private function matches(Order $order, CheckoutSuccess $remote): bool
    {
        $currency = strtoupper((string) $remote->currency);
        $amount = (int) round(((float) $remote->amount) * 100);
        $expected = (int) ($order->getAttributes()['total'] ?? 0);

        return $remote->checkoutReference === $this->reference($order)
            && $currency === 'EUR'
            && $amount === $expected;
    }

    private function remember(Order $order, string $checkoutId, string $url): void
    {
        $current = $order->meta?->getArrayCopy() ?? [];

        $order->forceFill([
            'meta' => array_merge($current, [
                'sumup_checkout_id' => $checkoutId,
                'sumup_hosted_url' => $url,
            ]),
        ])->save();
    }

    private function checkoutId(Order $order): ?string
    {
        $id = $order->meta['sumup_checkout_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function reference(Order $order): string
    {
        return 'ORDER-'.$order->id;
    }

    private function description(Order $order): string
    {
        $order->loadMissing(['productLines.purchasable.product', 'billingAddress']);

        $services = [];
        $atHome = false;

        foreach ($order->productLines as $line) {
            if (! $line instanceof OrderLine) {
                continue;
            }

            $variant = $line->purchasable;
            $product = $variant instanceof ProductVariant ? $variant->product : null;
            $duration = $product !== null ? (string) $product->translateAttribute('duration') : '';
            $place = $product !== null ? (string) $product->translateAttribute('service_group') : '';
            $atHome = $atHome || str_contains(mb_strtolower($place), 'domicile');

            $detail = (string) $line->description;
            $extra = array_values(array_filter([$duration, $place]));

            if ($extra !== []) {
                $detail .= ', '.implode(', ', $extra);
            }

            if ((int) $line->quantity > 1) {
                $detail .= ' × '.$line->quantity;
            }

            if ($detail !== '') {
                $services[] = $detail;
            }
        }

        $address = $order->billingAddress;
        $contact = array_values(array_filter([
            trim((string) ($address?->first_name ?? '').' '.(string) ($address?->last_name ?? '')),
            $atHome ? trim((string) ($address?->line_one ?? '').', '.(string) ($address?->postcode ?? '').' '.(string) ($address?->city ?? ''), ' ,') : null,
            $address?->contact_phone,
            $address?->contact_email,
        ]));

        $text = implode(' ; ', $services);

        if ($contact !== []) {
            $text = ($text !== '' ? $text.' — ' : '').implode(', ', $contact);
        }

        $reference = 'Réservation '.$order->reference;
        $text = $text !== '' ? $text.' — '.$reference : $reference;

        if (mb_strlen($text) > 500) {
            return mb_substr($text, 0, 499).'…';
        }

        return $text;
    }

    private function sumUpUrl(string $url): ?string
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';

        if ($scheme !== 'https' || ! preg_match('/(^|\.)sumup\.com$/i', (string) $host)) {
            return null;
        }

        return $url;
    }

    private function paymentError(SDKException $exception): string
    {
        $body = $exception->getResponseBody();
        $param = null;

        if (is_array($body)) {
            $param = $body['param'] ?? null;
        } elseif (is_object($body)) {
            $param = $body->param ?? null;
        }

        if ($exception->getStatusCode() === 401) {
            return 'SumUp a refusé la clé. Utilisez la clé secrète du compte sandbox, celle qui commence par sup_sk_.';
        }

        if (is_string($param) && str_contains($param, 'merchant_code')) {
            return 'SumUp refuse ce code marchand pour un paiement en ligne. Activez les paiements en ligne sur ce compte SumUp, puis réessayez.';
        }

        return 'Le paiement est indisponible pour le moment.';
    }

    private function client(): SumUp
    {
        return new SumUp([
            'api_key' => config('services.sumup.key'),
            'base_uri' => config('services.sumup.base_url'),
            'client' => new LaravelHttpClient((string) config('services.sumup.base_url')),
        ]);
    }

    private function options(): RequestOptions
    {
        return new RequestOptions(
            timeout: (int) config('services.sumup.timeout'),
            connectTimeout: 10,
        );
    }
}
