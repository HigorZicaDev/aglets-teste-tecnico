<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProductService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()

            // Busca por nome
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(
                    'name',
                    'ilike',
                    '%' . $request->input('search') . '%'
                );
            })

            // Preço mínimo
            ->when($request->filled('price_min'), function ($query) use ($request) {
                $query->where(
                    'price',
                    '>=',
                    $request->input('price_min')
                );
            })

            // Preço máximo
            ->when($request->filled('price_max'), function ($query) use ($request) {
                $query->where(
                    'price',
                    '<=',
                    $request->input('price_max')
                );
            })

            // Estoque mínimo
            ->when($request->filled('stock_min'), function ($query) use ($request) {
                $query->where(
                    'quantity_in_stock',
                    '>=',
                    $request->input('stock_min')
                );
            })

            // Estoque máximo
            ->when($request->filled('stock_max'), function ($query) use ($request) {
                $query->where(
                    'quantity_in_stock',
                    '<=',
                    $request->input('stock_max')
                );
            })

            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->success([
            'items' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ], 'Produtos listados com sucesso.');
    }

    public function show(Product $product): JsonResponse
    {
        return $this->success(
            new ProductResource($product),
            'Produto encontrado com sucesso.'
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->service->create($request->validated());

        return $this->success(
            new ProductResource($product),
            'Produto criado com sucesso.',
            201
        );
    }

}
