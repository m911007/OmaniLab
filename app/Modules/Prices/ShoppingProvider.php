<?php

namespace App\Modules\Prices;

/** مزوّد أسعار المتاجر الحية. الكشط المباشر لمواقع المتاجر غير مستخدم عمدًا (تمنعه شروط أغلبها)، فالمزوّد خدمة مرخّصة بمفتاح المالك. */
interface ShoppingProvider
{
    public function key(): string;

    public function label(): string;

    public function configured(): bool;

    /**
     * @return list<array{title: string, store: string, price: float, currency: string, link: ?string, thumbnail: ?string, rating: ?float, reviews: ?int, delivery: ?string}>
     */
    public function search(string $query, string $countryIso2, int $limit = 20): array;
}
