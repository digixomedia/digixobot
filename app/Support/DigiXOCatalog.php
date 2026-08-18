<?php

namespace App\Support;

use Illuminate\Support\Str;

class DigiXOCatalog
{
    public static function payload(): array
    {
        $catalog = require database_path('data/digixo_catalog.php');

        return [
            'categories' => array_map(function (array $category): array {
                return [
                    'slug' => $category['slug'],
                    'name' => $category['name'],
                    'description' => null,
                    'display_order' => $category['display_order'],
                    'is_active' => true,
                    'products' => array_map(function (array $product, int $index): array {
                        return [
                            'slug' => $product['slug'],
                            'name' => $product['name'],
                            'description' => $product['description'],
                            'display_order' => $index + 1,
                            'is_active' => true,
                            'is_featured' => false,
                            'is_deal' => false,
                            'plans' => [[
                                'slug' => Str::slug($product['plan']),
                                'name' => $product['plan'],
                                'validity' => $product['validity'],
                                'price_paise' => $product['price_rupees'] * 100,
                                'compare_at_price_paise' => null,
                                'stock' => null,
                                'delivery_method' => null,
                                'delivery_estimate' => null,
                                'activation_method' => null,
                                'warranty' => null,
                                'conditions' => null,
                                'is_active' => true,
                                'display_order' => 1,
                            ]],
                        ];
                    }, $category['products'], array_keys($category['products'])),
                ];
            }, $catalog),
        ];
    }
}
