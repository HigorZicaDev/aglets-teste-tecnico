<?php

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('creates a product', function () {
    $payload = [
        'name' => 'Teclado Mecânico',
        'description' => 'Teclado mecânico RGB',
        'price' => 299.90,
        'quantity_in_stock' => 10,
    ];

    $response = $this->postJson('/api/v1/products', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', $payload['name'])
        ->assertJsonPath('data.quantity_in_stock', $payload['quantity_in_stock'])
        ->assertJsonPath('message', 'Produto criado com sucesso.')
        ->assertJsonPath('errors', null);

    $this->assertDatabaseHas('products', [
        'name' => $payload['name'],
        'quantity_in_stock' => $payload['quantity_in_stock'],
    ]);
});

test('rejects invalid product payload', function () {
    $response = $this->postJson('/api/v1/products', [
        'description' => 'Sem nome e com preço inválido',
        'price' => -10,
        'quantity_in_stock' => 5,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'price']);
});

test('lists products with pagination envelope', function () {
    Product::factory()->count(20)->create();

    $response = $this->getJson('/api/v1/products');

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Produtos listados com sucesso.')
        ->assertJsonPath('errors', null)
        ->assertJsonPath('data.meta.per_page', 15)
        ->assertJsonPath('data.meta.total', 20)
        ->assertJsonCount(15, 'data.items');
});

test('filters products by price range', function () {
    Product::factory()->create(['name' => 'Produto Barato', 'price' => 10]);
    Product::factory()->create(['name' => 'Produto Caro', 'price' => 900]);

    $response = $this->getJson('/api/v1/products?price_min=500');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.name', 'Produto Caro');
});

test('returns 404 when showing a non-existent product', function () {
    $response = $this->getJson('/api/v1/products/999999');

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Produto não encontrado.')
        ->assertJsonPath('data', null);
});

test('invalidates the list cache when a product is created', function () {
    Product::factory()->count(3)->create();

    $this->getJson('/api/v1/products')->assertJsonPath('data.meta.total', 3);

    $this->postJson('/api/v1/products', [
        'name' => 'Produto Novo Pós Cache',
        'description' => null,
        'price' => 49.90,
        'quantity_in_stock' => 7,
    ])->assertStatus(201);

    $response = $this->getJson('/api/v1/products');

    $response->assertJsonPath('data.meta.total', 4);

    $names = collect($response->json('data.items'))->pluck('name');
    expect($names)->toContain('Produto Novo Pós Cache');
});
