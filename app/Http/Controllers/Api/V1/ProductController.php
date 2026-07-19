<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
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
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'price_min', 'price_max', 'stock_min', 'stock_max']);

        $products = $this->service->list(
            $filters,
            $request->integer('per_page', 15),
            $request->integer('page', 1)
        );

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

    public function show(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->error(
                'Produto não encontrado.',
                status: 404
            );
        }

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

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->service->update($product, $request->validated());

        return $this->success(
            new ProductResource($product),
            'Produto atualizado com sucesso.'
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->service->delete($product);

        return $this->success(null, 'Produto excluído com sucesso.');
    }
}
