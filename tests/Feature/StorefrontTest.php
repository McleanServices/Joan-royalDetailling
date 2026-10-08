<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\Facades\CartSession;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_home_lists_priced_and_quote_collections(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('store/home')
            ->has('collections', 5)
            ->where('collections.0.key', 'automobile')
            ->where('collections.2.quote_only', true)
        );
    }

    public function test_registration_creates_a_customer_and_sends_a_welcome_email(): void
    {
        Mail::fake();

        $response = $this->post(route('account.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'jean@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->phone);
        $this->assertInstanceOf(Customer::class, $user->latestCustomer());
        Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail) => $mail->hasTo('jean@example.com'));
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jean@example.com']);

        $response = $this->from(route('account'))->post(route('account.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('account'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_adding_a_service_keeps_it_on_the_reservation(): void
    {
        $variant = ProductVariant::query()->where('sku', 'formule-basic-wash-citadine-exterieur')->firstOrFail();

        $response = $this->from(route('home'))->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionMissing('success');

        $this->post(route('order.add'), [
            'variant_id' => $variant->id,
        ])->assertRedirect(route('home'));

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(2, CartSession::current()?->lines()->count());

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('reservation.count', 2)
            ->where('reservation.total', '59,98 €')
            ->has('reservation.lines', 2)
            ->where('reservation.lines.0.price', '29,99 €')
            ->where('reservation.lines.1.price', '29,99 €')
            ->where('reservation.duration', '2 h')
        );
    }

    public function test_paiement_redirects_to_the_sumup_hosted_page(): void
    {
        $this->fakeSumUp(status: 'PENDING');

        $variant = ProductVariant::query()->where('sku', 'formule-basic-wash-citadine-exterieur')->firstOrFail();

        $this->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $response = $this->post(route('order.create'), $this->payer());

        $order = Order::query()->first();

        $this->assertNotNull($order);
        $response->assertRedirect('https://checkout.sumup.com/pay/chk_test');

        $this->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $this->withHeader('X-Inertia', 'true')
            ->post(route('order.create'), $this->payer())
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://checkout.sumup.com/pay/chk_test');
        $this->assertSame(2999, $order->total->value);
        $this->assertSame('Camille', $order->billingAddress?->first_name);
        $this->assertSame('Martin', $order->billingAddress?->last_name);
        $this->assertSame('camille@exemple.fr', $order->billingAddress?->contact_email);
        $this->assertSame('0690123456', $order->billingAddress?->contact_phone);
        $this->assertSame('awaiting-payment', $order->status);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api.sumup.com/v0.1/checkouts'
            && $request['amount'] === 29.99
            && $request['currency'] === 'EUR'
            && $request['merchant_code'] === 'MCODE'
            && $request['checkout_reference'] === 'ORDER-'.$order->id
            && $request['hosted_checkout']['enabled'] === true
            && $request['redirect_url'] === route('checkout', $order)
            && $request['return_url'] === route('checkout.notify')
            && str_contains((string) $request['description'], 'Basic Wash CITADINE')
            && str_contains((string) $request['description'], 'Dans nos locaux')
            && str_contains((string) $request['description'], 'Camille Martin')
            && str_contains((string) $request['description'], '0690123456')
            && str_contains((string) $request['description'], 'camille@exemple.fr'));
    }

    public function test_return_from_sumup_marks_the_order_paid(): void
    {
        $this->fakeSumUp(status: 'PAID');

        $order = $this->reserveOneService();

        $this->get(route('checkout', $order))
            ->assertRedirect(route('thank-you'));

        $order->refresh();

        $this->assertSame('payment-received', $order->status);
        $this->assertNotNull($order->placed_at);
        $this->assertTrue($order->transactions()->where('reference', 'chk_test')->where('success', true)->exists());
    }

    public function test_return_does_not_mark_an_unpaid_checkout(): void
    {
        $this->fakeSumUp(status: 'PENDING');

        $order = $this->reserveOneService();

        $this->get(route('checkout', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('store/checkout')
                ->where('state', 'pending')
                ->where('total', '29,99 €')
                ->where('hostedUrl', 'https://checkout.sumup.com/pay/chk_test')
            );

        $this->assertSame('awaiting-payment', $order->refresh()->status);
    }

    public function test_return_rejects_a_paid_checkout_with_the_wrong_amount(): void
    {
        $this->fakeSumUp(status: 'PAID', amount: 1.00);

        $order = $this->reserveOneService();

        $this->get(route('checkout', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'mismatch')
            );

        $this->assertSame('awaiting-payment', $order->refresh()->status);
        $this->assertSame(0, $order->transactions()->count());
    }

    public function test_sumup_callback_marks_the_order_paid(): void
    {
        $this->fakeSumUp(status: 'PAID');

        $order = $this->reserveOneService();

        $this->flushSession();

        $this->post(route('checkout.notify'), ['id' => 'chk_test'])
            ->assertNoContent();

        $this->assertSame('payment-received', $order->refresh()->status);
    }

    public function test_a_public_sumup_key_is_rejected_before_checkout(): void
    {
        config([
            'services.sumup.key' => 'sup_pk_public',
            'services.sumup.merchant_code' => 'MCODE',
        ]);

        Http::fake();

        $variant = ProductVariant::query()->where('sku', 'formule-basic-wash-citadine-exterieur')->firstOrFail();

        $this->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $this->from(route('reservation'))->post(route('order.create'), $this->payer())
            ->assertRedirect(route('reservation'))
            ->assertSessionHasErrors([
                'payment' => 'La clé SumUp est une clé publique. Collez la clé secrète du sandbox, celle qui commence par sup_sk_.',
            ]);

        $this->assertSame(0, Order::query()->count());
        Http::assertNothingSent();
    }

    public function test_an_unusable_merchant_code_keeps_the_reservation(): void
    {
        config([
            'services.sumup.key' => 'test-key',
            'services.sumup.merchant_code' => 'MCODE',
        ]);

        Http::fake([
            'https://api.sumup.com/v0.1/checkouts' => Http::response([
                'message' => 'Validation error',
                'error_code' => 'INVALID',
                'param' => 'merchant_code',
            ], 400),
        ]);

        $variant = ProductVariant::query()->where('sku', 'formule-basic-wash-citadine-exterieur')->firstOrFail();

        $this->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $this->from(route('reservation'))->post(route('order.create'), $this->payer())
            ->assertRedirect(route('reservation'))
            ->assertSessionHasErrors([
                'payment' => 'SumUp refuse ce code marchand pour un paiement en ligne. Activez les paiements en ligne sur ce compte SumUp, puis réessayez.',
            ]);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, CartSession::current()?->lines()->count());
    }

    public function test_a_foreign_payment_url_is_not_followed(): void
    {
        config([
            'services.sumup.key' => 'test-key',
            'services.sumup.merchant_code' => 'MCODE',
        ]);

        Http::fake([
            'https://api.sumup.com/v0.1/checkouts' => Http::response([
                'id' => 'chk_test',
                'hosted_checkout_url' => 'https://evil.example/pay',
            ], 201),
        ]);

        $variant = ProductVariant::query()->where('sku', 'formule-basic-wash-citadine-exterieur')->firstOrFail();

        $this->from(route('home'))->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $this->from(route('reservation'))->post(route('order.create'), $this->payer())
            ->assertRedirect(route('reservation'))
            ->assertSessionHasErrors('payment');
    }

    public function test_checkout_is_hidden_from_another_visitor(): void
    {
        $this->fakeSumUp(status: 'PENDING');

        $order = $this->reserveOneService();

        $this->flushSession();

        $this->get(route('checkout', $order))->assertForbidden();
    }

    private function reserveOneService(): Order
    {
        $variant = ProductVariant::query()->where('sku', 'formule-basic-wash-citadine-exterieur')->firstOrFail();

        $this->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $this->post(route('order.create'), $this->payer())->assertRedirect('https://checkout.sumup.com/pay/chk_test');

        return Order::query()->firstOrFail();
    }

    private function fakeSumUp(string $status, float $amount = 29.99): void
    {
        config([
            'services.sumup.key' => 'test-key',
            'services.sumup.merchant_code' => 'MCODE',
        ]);

        Http::fake(function ($request) use ($status, $amount) {
            if ($request->method() === 'POST') {
                return Http::response([
                    'id' => 'chk_test',
                    'status' => 'PENDING',
                    'hosted_checkout_url' => 'https://checkout.sumup.com/pay/chk_test',
                    'amount' => 29.99,
                    'currency' => 'EUR',
                ], 201);
            }

            $order = Order::query()->first();

            return Http::response([
                'id' => 'chk_test',
                'status' => $status,
                'checkout_reference' => 'ORDER-'.$order?->id,
                'amount' => $amount,
                'currency' => 'EUR',
            ], 200);
        });
    }

    public function test_reservation_page_lists_the_selected_service(): void
    {
        $variant = ProductVariant::query()->where('sku', 'formule-basic-wash-citadine-exterieur')->firstOrFail();

        $this->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $this->get(route('reservation'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('store/reservation')
                ->where('reservation.count', 1)
                ->where('reservation.lines.0.name', 'Formule Basic Wash CITADINE — Extérieur')
                ->where('reservation.lines.0.price', '29,99 €')
            );
    }

    public function test_paiement_requires_customer_details(): void
    {
        $variant = ProductVariant::query()->where('sku', 'formule-basic-wash-citadine-exterieur')->firstOrFail();

        $this->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $this->from(route('reservation'))->post(route('order.create'), [])
            ->assertRedirect(route('reservation'))
            ->assertSessionHasErrors([
                'first_name' => 'Le prénom est obligatoire.',
                'last_name' => 'Le nom est obligatoire.',
                'email' => 'L\'e-mail est obligatoire.',
                'phone' => 'Le téléphone est obligatoire.',
            ]);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, CartSession::current()?->lines()->count());
    }

    public function test_a_home_visit_requires_the_address_and_sends_it_to_sumup(): void
    {
        $this->fakeSumUp(status: 'PENDING', amount: 69.99);

        $variant = ProductVariant::query()->where('sku', 'formule-super-wash-citadine')->firstOrFail();

        $this->post(route('order.add'), [
            'variant_id' => $variant->id,
        ]);

        $this->from(route('reservation'))->post(route('order.create'), $this->payer())
            ->assertRedirect(route('reservation'))
            ->assertSessionHasErrors([
                'line_one' => 'L\'adresse est obligatoire.',
                'postcode' => 'Le code postal est obligatoire.',
                'city' => 'La ville est obligatoire.',
            ]);

        $this->assertSame(0, Order::query()->count());

        $this->post(route('order.create'), $this->payer([
            'line_one' => '12 rue des Flamboyants',
            'postcode' => '97139',
            'city' => 'Les Abymes',
        ]))->assertRedirect('https://checkout.sumup.com/pay/chk_test');

        $order = Order::query()->firstOrFail();

        $this->assertSame('12 rue des Flamboyants', $order->billingAddress?->line_one);
        $this->assertSame('97139', $order->billingAddress?->postcode);
        $this->assertSame('Les Abymes', $order->billingAddress?->city);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains((string) $request['description'], 'Super Wash CITADINE')
            && str_contains((string) $request['description'], '12 rue des Flamboyants')
            && str_contains((string) $request['description'], '97139 Les Abymes'));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payer(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Camille',
            'last_name' => 'Martin',
            'email' => 'camille@exemple.fr',
            'phone' => '0690123456',
        ], $overrides);
    }

    public function test_thank_you_page_is_available(): void
    {
        $this->get(route('thank-you'))->assertOk();
    }
}
