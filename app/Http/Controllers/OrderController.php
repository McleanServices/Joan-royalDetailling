<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayReservationRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SumUpCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lunar\Facades\CartSession;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function add(StoreOrderRequest $request, OrderService $orders): RedirectResponse
    {
        $orders->add($request->integer('variant_id'));

        return back();
    }

    public function remove(Request $request, OrderService $orders, int $line): RedirectResponse
    {
        $orders->remove($line);

        return back();
    }

    public function store(PayReservationRequest $request, OrderService $orders, SumUpCheckoutService $sumup): Response
    {
        $sumup->ensureConfigured();

        $user = $request->user();
        $customer = $request->safe()->only([
            'first_name',
            'last_name',
            'email',
            'phone',
            'line_one',
            'postcode',
            'city',
        ]);

        try {
            $url = DB::transaction(function () use ($orders, $sumup, $user, $customer): string {
                $order = $orders->checkout($user instanceof User ? $user : null, forgetCart: false, customer: $customer);

                return $sumup->start($order);
            });
        } catch (ValidationException $exception) {
            session()->forget('checkout.order_id');

            throw $exception;
        }

        CartSession::forget();

        return Inertia::location($url);
    }
}
