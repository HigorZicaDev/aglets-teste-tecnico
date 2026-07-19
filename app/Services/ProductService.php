<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ProductService
{
    private const VERSION_KEY = 'products:list:version';

    private const LIST_TTL = 300;

    /**
     * Lista produtos com cache por combinação de filtros + paginação.
     *
     * A chave de cache embute a versão atual da listagem (ver bumpListVersion),
     * de modo que qualquer alteração em produtos invalida todas as variações
     * de uma só vez, sem depender de cache tags.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $cacheKey = $this->listCacheKey($filters, $perPage, $page);

        return Cache::remember($cacheKey, self::LIST_TTL, function () use ($filters, $perPage, $page) {
            return Product::query()
                ->when(! empty($filters['search']), function ($query) use ($filters) {
                    $query->where('name', 'ilike', '%'.$filters['search'].'%');
                })
                ->when(! empty($filters['price_min']), function ($query) use ($filters) {
                    $query->where('price', '>=', $filters['price_min']);
                })
                ->when(! empty($filters['price_max']), function ($query) use ($filters) {
                    $query->where('price', '<=', $filters['price_max']);
                })
                ->when(! empty($filters['stock_min']), function ($query) use ($filters) {
                    $query->where('quantity_in_stock', '>=', $filters['stock_min']);
                })
                ->when(! empty($filters['stock_max']), function ($query) use ($filters) {
                    $query->where('quantity_in_stock', '<=', $filters['stock_max']);
                })
                ->latest()
                ->paginate($perPage, ['*'], 'page', $page);
        });
    }

    public function create(array $data): Product
    {
        $product = Product::create($data);

        $this->bumpListVersion();

        return $product;
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        $this->bumpListVersion();

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();

        $this->bumpListVersion();
    }

    private function listCacheKey(array $filters, int $perPage, int $page): string
    {
        ksort($filters);

        $hash = md5(serialize([$filters, $perPage, $page]));

        return sprintf('products:list:v%d:%s', $this->currentListVersion(), $hash);
    }

    private function currentListVersion(): int
    {
        return Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Incrementa a versão da listagem, tornando todas as chaves de cache
     * geradas com a versão anterior inalcançáveis (invalidação em massa).
     * As entradas órfãs expiram sozinhas pelo TTL. Estratégia usada por não
     * haver suporte a cache tags no driver `database`.
     */
    private function bumpListVersion(): void
    {
        Cache::forever(self::VERSION_KEY, $this->currentListVersion() + 1);
    }
}
