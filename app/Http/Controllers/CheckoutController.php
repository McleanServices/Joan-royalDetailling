<?php

namespace App\Http\Controllers;

use App\Services\SumUpCheckoutService;
use App\Support\Euro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Models\Order;
use SumUp\Exception\SDKException;

class CheckoutController extends Controller
{
    public function show(Order $order, SumUpCheckoutService $sumup): Response|RedirectResponse
    {
        $this->authorizeOrder($order);

        try {
            $state = $sumup->settle($order);
        } catch (SDKException $exception) {
            report($exception);
            $state = 'unavailable';
        }

        if ($state === 'paid') {
            return redirect()->route('thank-you');
        }

        return Inertia::render('store/checkout', [
            'orderId' => $order->id,
            'total' => Euro::fromStored($order->getAttributes()['total'] ?? 0),
            'state' => $state,
            'hostedUrl' => $sumup->hostedUrl($order),
        ]);
    }

    public function notify(Request $request, SumUpCheckoutService $sumup): HttpResponse
    {
        $checkoutId = (string) $request->input('id', '');
        $order = $sumup->findByCheckoutId($checkoutId);

        abort_if($order === null, 404);

        try {
            $sumup->settle($order);
        } catch (SDKException $exception) {
            report($exception);

            abort(503);
        }

        return response()->noContent();
    }

    public function thankYou(): Response
    {
        return Inertia::render('store/thank-you');
    }

    private function authorizeOrder(Order $order): void
    {
        $ownsOrder = auth()->id() !== null && (int) auth()->id() === (int) $order->user_id;
        $openedInSession = (int) session('checkout.order_id') === $order->id;

        abort_unless($ownsOrder || $openedInSession, 403);
    }
}
