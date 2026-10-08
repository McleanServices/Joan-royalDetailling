<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lunar\Admin\Models\Staff;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxZone;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $currency = $this->foundation();
        $this->catalog($currency);
        $this->admin();

        Cache::forget('storefront.catalog');
    }

    private function foundation(): Currency
    {
        Channel::query()->updateOrCreate(
            ['handle' => 'webstore'],
            [
                'name' => 'Webstore',
                'default' => true,
                'url' => config('app.url'),
            ],
        );

        Language::query()->where('default', true)->where('code', '!=', 'fr')->update(['default' => false]);
        Language::query()->updateOrCreate(
            ['code' => 'fr'],
            ['name' => 'Français', 'default' => true],
        );

        Currency::query()->where('default', true)->where('code', '!=', 'EUR')->update(['default' => false]);
        $currency = Currency::query()->updateOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'exchange_rate' => 1,
                'decimal_places' => 2,
                'default' => true,
                'enabled' => true,
            ],
        );

        CustomerGroup::query()->updateOrCreate(
            ['handle' => 'retail'],
            ['name' => 'Retail', 'default' => true],
        );

        CollectionGroup::query()->firstOrCreate(
            ['handle' => 'main'],
            ['name' => 'Main'],
        );

        $taxClass = TaxClass::query()->firstOrCreate(
            ['name' => 'Default Tax Class'],
            ['default' => true],
        );

        $country = Country::query()->updateOrCreate(
            ['iso2' => 'GP'],
            [
                'name' => 'Guadeloupe',
                'iso3' => 'GLP',
                'phonecode' => '590',
                'capital' => 'Basse-Terre',
                'currency' => 'EUR',
                'native' => 'Guadeloupe',
                'emoji' => '🇬🇵',
                'emoji_u' => 'U+1F1EC U+1F1F5',
            ],
        );

        $taxZone = TaxZone::query()->firstOrCreate(
            ['name' => 'Guadeloupe'],
            [
                'zone_type' => 'country',
                'price_display' => 'tax_inclusive',
                'default' => true,
                'active' => true,
            ],
        );

        $taxZone->update(['price_display' => 'tax_inclusive', 'default' => true, 'active' => true]);

        if (! $taxZone->countries()->where('country_id', $country->id)->exists()) {
            $taxZone->countries()->create(['country_id' => $country->id]);
        }

        $taxRate = TaxRate::query()->firstOrCreate(
            ['tax_zone_id' => $taxZone->id, 'name' => 'Taux zéro'],
            ['priority' => 1],
        );

        $taxRate->taxRateAmounts()->updateOrCreate(
            ['tax_class_id' => $taxClass->id],
            ['percentage' => 0],
        );

        $this->attributes();

        $type = ProductType::query()->firstOrCreate(['name' => 'Service']);
        $type->mappedAttributes()->sync(
            Attribute::query()->where('attribute_type', 'product')->pluck('id'),
        );

        return $currency;
    }

    private function attributes(): void
    {
        $productGroup = AttributeGroup::query()->firstOrCreate(
            ['handle' => 'details'],
            [
                'attributable_type' => Product::morphName(),
                'name' => collect(['fr' => 'Détails', 'en' => 'Details']),
                'position' => 1,
            ],
        );

        $collectionGroup = AttributeGroup::query()->firstOrCreate(
            ['handle' => 'collection_details'],
            [
                'attributable_type' => Collection::morphName(),
                'name' => collect(['fr' => 'Détails', 'en' => 'Details']),
                'position' => 1,
            ],
        );

        $this->translatedAttribute($productGroup->id, 'product', 'name', 'Nom', 1, true, false);
        $this->translatedAttribute($productGroup->id, 'product', 'description', 'Description', 2, false, true);
        $this->translatedAttribute($productGroup->id, 'product', 'duration', 'Durée', 3, false, false);
        $this->translatedAttribute($productGroup->id, 'product', 'service_group', 'Groupe', 4, false, false);
        $this->translatedAttribute($collectionGroup->id, 'collection', 'name', 'Nom', 1, true, false);
        $this->translatedAttribute($collectionGroup->id, 'collection', 'description', 'Description', 2, false, true);
        $this->plainAttribute($collectionGroup->id, 'collection', 'catalog_key', 'Clé', 3);
    }

    private function translatedAttribute(int $groupId, string $type, string $handle, string $name, int $position, bool $required, bool $richtext): void
    {
        Attribute::query()->updateOrCreate(
            ['attribute_type' => $type, 'handle' => $handle],
            [
                'attribute_group_id' => $groupId,
                'position' => $position,
                'name' => ['fr' => $name, 'en' => $name],
                'section' => 'main',
                'type' => TranslatedText::class,
                'required' => $required,
                'default_value' => null,
                'configuration' => ['richtext' => $richtext],
                'system' => $handle === 'name',
                'description' => ['fr' => '', 'en' => ''],
            ],
        );
    }

    private function plainAttribute(int $groupId, string $type, string $handle, string $name, int $position): void
    {
        Attribute::query()->updateOrCreate(
            ['attribute_type' => $type, 'handle' => $handle],
            [
                'attribute_group_id' => $groupId,
                'position' => $position,
                'name' => ['fr' => $name, 'en' => $name],
                'section' => 'main',
                'type' => Text::class,
                'required' => false,
                'default_value' => null,
                'configuration' => ['richtext' => false],
                'system' => false,
                'description' => ['fr' => '', 'en' => ''],
            ],
        );
    }

    private function catalog(Currency $currency): void
    {
        if (ProductVariant::query()->where('sku', Str::slug('Formule Basic Wash CITADINE — Extérieur'))->exists()) {
            return;
        }

        $groupId = CollectionGroup::query()->where('handle', 'main')->value('id');
        $taxClassId = TaxClass::query()->where('default', true)->value('id');

        $collections = [
            [
                'key' => 'automobile',
                'name' => 'Automobile',
                'description' => 'Lavage extérieur, nettoyage intérieur complet et détailing pour citadines, berlines, SUV et 4×4.',
                'services' => [
                    $this->service('Formule Basic Wash CITADINE — Extérieur', 'Prélavage au canon à mousse · Nettoyage des jantes · Lavage avec shampooing hydrophobe · Brillant pneus', '1 h', 'Dans nos locaux', 2999),
                    $this->service('Formule Basic Wash CITADINE — Intérieur', "Aspiration complète de l'habitacle · Nettoyage des plastiques et/ou cuirs · Rénovation des plastiques intérieur · Nettoyage des vitres", '1 h', 'Dans nos locaux', 3499),
                    $this->service('Formule Basic Wash SUV / 4×4 / BERLINE — Extérieur', 'Prélavage au canon à mousse · Nettoyage des jantes · Lavage avec shampooing hydrophobe · Brillant pneus', '1 h', 'Dans nos locaux', 3999),
                    $this->service('Formule Super Wash CITADINE', 'Aspiration complète · Nettoyage des plastiques/cuirs · Rénovation plastiques intérieur · Nettoyage des vitres · Prélavage au canon à mousse · Nettoyage des jantes · Lavage shampooing hydrophobe · Brillant pneus', '2 h 30', 'À domicile / lieu de travail', 6999),
                    $this->service('Formule Super Wash SUV / 4×4 / BERLINE', 'Aspiration complète · Nettoyage des plastiques/cuirs · Rénovation plastiques intérieur · Nettoyage des vitres · Prélavage au canon à mousse · Nettoyage des jantes · Lavage shampooing hydrophobe · Brillant pneus', '2 h 30', 'À domicile / lieu de travail', 7999),
                    $this->service('Formule Maxi Wash CITADINE', 'Aspiration complète · Shampooing sièges, moquettes & tapis · Nettoyage plastiques/cuirs · Nettoyage plafond · Rénovation plastiques/cuirs · Nettoyage vitres · Prélavage canon à mousse · Nettoyage jantes · Lavage shampooing hydrophobe · Brillant pneus', '3 h', 'À domicile / lieu de travail', 13999),
                    $this->service('Formule Maxi Wash SUV / 4×4 / BERLINE', 'Aspiration complète · Shampooing sièges, moquettes & tapis · Nettoyage plastiques/cuirs · Nettoyage plafond · Rénovation plastiques/cuirs · Nettoyage vitres · Prélavage canon à mousse · Nettoyage jantes · Lavage shampooing hydrophobe · Brillant pneus', '3 h', 'À domicile / lieu de travail', 14999),
                ],
            ],
            [
                'key' => 'nettoyage',
                'name' => 'Nettoyage / Entretien',
                'description' => 'Shampooing, traitement anti-acariens et désodorisation pour canapés, fauteuils et matelas — à domicile.',
                'services' => [
                    $this->service('Canapé ANGLE / MODULAIRE', 'Shampooing · Traitement anti-acariens · Traitement anti-bactériens · Désodorisation. Procédé en profondeur par injection/extraction ou nettoyeur vapeur.', '45 min', 'À domicile et lieu de travail', 9999),
                    $this->service('Canapé DROIT', 'Shampooing · Traitement anti-acariens · Traitement anti-bactériens · Désodorisation. Procédé en profondeur par injection/extraction ou nettoyeur vapeur.', '45 min', 'À domicile et lieu de travail', 7999),
                    $this->service('Fauteuil 2 PLACES', 'Shampooing · Traitement anti-acariens · Traitement anti-bactériens · Désodorisation. Traitement respectant les fibres.', '45 min', 'À domicile et lieu de travail', 6999),
                    $this->service('Matelas 1 PLACE', 'Shampooing · Décontamination · Traitement anti-acarien. Procédé en profondeur pour éliminer acariens, taches et mauvaises odeurs.', '45 min', 'À domicile et lieu de travail', 2999),
                    $this->service('Matelas 2 PLACES', 'Shampooing · Décontamination · Traitement anti-acarien. Procédé en profondeur pour éliminer acariens, taches et mauvaises odeurs.', '45 min', 'À domicile et lieu de travail', 3999),
                ],
            ],
            [
                'key' => 'professionnels',
                'name' => 'Services professionnels',
                'description' => "Contrats d'entretien sur mesure pour flottes de véhicules, sociétés et professionnels. Tarification dédiée selon volume et fréquence d'intervention.",
                'services' => [],
            ],
            [
                'key' => 'camion',
                'name' => 'Camion / Véhicules lourds',
                'description' => 'Nettoyage complet de cabine, carrosserie et plateau pour camions, semi-remorques et véhicules utilitaires. Chaque prestation est établie sur devis.',
                'services' => [],
            ],
            [
                'key' => 'autres',
                'name' => 'Autres services',
                'description' => 'Protection céramique, lustrage, polissage paint correction, traitement cuir et toutes autres prestations spécialisées. Contactez-nous pour un devis gratuit.',
                'services' => [],
            ],
        ];

        foreach ($collections as $definition) {
            $collection = Collection::create([
                'collection_group_id' => $groupId,
                'attribute_data' => [
                    'name' => new TranslatedText(collect(['fr' => $definition['name']])),
                    'description' => new TranslatedText(collect(['fr' => $definition['description']])),
                    'catalog_key' => new Text($definition['key']),
                ],
            ]);

            foreach ($definition['services'] as $position => $service) {
                $product = Product::create([
                    'status' => 'published',
                    'product_type_id' => ProductType::query()->where('name', 'Service')->value('id'),
                    'attribute_data' => [
                        'name' => new TranslatedText(collect(['fr' => $service['name']])),
                        'description' => new TranslatedText(collect(['fr' => $service['description']])),
                        'duration' => new TranslatedText(collect(['fr' => $service['duration']])),
                        'service_group' => new TranslatedText(collect(['fr' => $service['group']])),
                    ],
                ]);

                $variant = $product->variants()->create([
                    'tax_class_id' => $taxClassId,
                    'sku' => Str::slug($service['name']),
                    'shippable' => false,
                    'purchasable' => 'always',
                    'stock' => 0,
                    'unit_quantity' => 1,
                ]);

                if ($variant instanceof ProductVariant) {
                    $variant->prices()->create([
                        'currency_id' => $currency->id,
                        'price' => $service['price'],
                        'min_quantity' => 1,
                    ]);
                }

                $collection->products()->attach($product->id, ['position' => $position + 1]);
            }
        }
    }

    /**
     * @return array{name: string, description: string, duration: string, group: string, price: int}
     */
    private function service(string $name, string $description, string $duration, string $group, int $price): array
    {
        return compact('name', 'description', 'duration', 'group', 'price');
    }

    private function admin(): void
    {
        Staff::query()->firstOrCreate(
            ['email' => config('store.admin.email')],
            [
                'first_name' => 'Royal',
                'last_name' => 'Admin',
                'password' => config('store.admin.password'),
                'admin' => true,
            ],
        );
    }
}
