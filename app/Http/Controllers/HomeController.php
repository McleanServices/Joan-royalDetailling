<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use App\Services\OrderService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(CatalogService $catalog, OrderService $orders): Response
    {
        return Inertia::render('store/home', [
            'collections' => $catalog->forStorefront(),
            'reservation' => $orders->summary($catalog),
        ]);
    }

    public function reservation(CatalogService $catalog, OrderService $orders): Response
    {
        return Inertia::render('store/reservation', [
            'reservation' => $orders->summary($catalog),
        ]);
    }
}
