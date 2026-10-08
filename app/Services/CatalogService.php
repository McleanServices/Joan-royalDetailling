<?php

namespace App\Services;

use App\Support\Euro;
use Illuminate\Support\Facades\Cache;
use Lunar\Models\Collection;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class CatalogService
{
    /**
     * @var array<string, array{icon: string, image: string}>
     */
    private const PRESENTATION = [
        'automobile' => ['icon' => 'bi-car-front-fill', 'image' => '/images/automobile.jpg'],
        'nettoyage' => ['icon' => 'bi-house-heart-fill', 'image' => '/images/nettoyage.jpg'],
        'professionnels' => ['icon' => 'bi-briefcase-fill', 'image' => '/images/professionnels.jpg'],
        'camion' => ['icon' => 'bi-truck', 'image' => '/images/camion.jpg'],
        'autres' => ['icon' => 'bi-stars', 'image' => '/images/autres.jpg'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function forStorefront(): array
    {
        /** @var list<array<string, mixed>> $catalog */
        $catalog = Cache::remember('storefront.catalog', 3600, function (): array {
            $presented = [];

            foreach (Collection::query()->with(['products.variants.prices.currency'])->orderBy('_lft')->get() as $collection) {
                if ($collection instanceof Collection) {
                    $presented[] = $this->present($collection);
                }
            }

            return $presented;
        });

        return $catalog;
    }

    public function imageFor(string $key): string
    {
        return self::PRESENTATION[$key]['image'] ?? '/images/automobile.jpg';
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Collection $collection): array
    {
        $key = (string) $collection->translateAttribute('catalog_key');
        $presentation = self::PRESENTATION[$key] ?? [
            'icon' => 'bi-grid-fill',
            'image' => '/images/automobile.jpg',
        ];

        $groups = [];

        foreach ($collection->products as $product) {
            if (! $product instanceof Product) {
                continue;
            }

            $service = $this->presentService($product);

            if ($service === null) {
                continue;
            }

            $label = $service['group'];
            unset($service['group']);
            $groups[$label][] = $service;
        }

        $grouped = [];

        foreach ($groups as $label => $services) {
            $grouped[] = [
                'label' => $label,
                'services' => $services,
            ];
        }

        return [
            'key' => $key,
            'name' => (string) $collection->translateAttribute('name'),
            'description' => (string) $collection->translateAttribute('description'),
            'icon' => $presentation['icon'],
            'image' => $presentation['image'],
            'quote_only' => $grouped === [],
            'groups' => $grouped,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function presentService(Product $product): ?array
    {
        $variant = $product->variants->first();

        if (! $variant instanceof ProductVariant || ! $variant->isPurchasable()) {
            return null;
        }

        $price = $variant->prices->first();

        if (! $price instanceof Price) {
            return null;
        }

        return [
            'name' => (string) $product->translateAttribute('name'),
            'description' => (string) $product->translateAttribute('description'),
            'duration' => (string) $product->translateAttribute('duration'),
            'group' => (string) ($product->translateAttribute('service_group') ?: 'Services'),
            'variant_id' => $variant->id,
            'price' => Euro::fromStored($price->getAttributes()['price'] ?? 0),
        ];
    }
}
