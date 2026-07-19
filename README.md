# API de Gerenciamento de Produtos

API REST em Laravel para cadastro, consulta, atualização e remoção de produtos, com filtros combináveis, paginação, cache na listagem e testes automatizados.

Projeto desenvolvido como teste técnico (vaga Desenvolvedor Fullstack — Aglets). Ver enunciado completo em [`instructions.md`](./instructions.md) e as respostas das perguntas teóricas em [`respostas.md`](./respostas.md).

## Stack

- PHP 8.4
- Laravel 13
- PostgreSQL 17 (via Docker)
- Pest 4 (testes, compatível com `php artisan test` / PHPUnit)
- Laravel Pint (formatação de código)

## Pré-requisitos

- PHP 8.4 + Composer
- Docker e Docker Compose (para o banco PostgreSQL)

## Instalação

```bash
# 1. Instalar dependências PHP
composer install

# 2. Copiar o arquivo de ambiente e gerar a chave da aplicação
cp .env.example .env
php artisan key:generate
```

O `.env.example` já vem configurado para apontar para o banco do `docker-compose.yml` (`DB_DATABASE=aglets`, `DB_USERNAME=userx`, `DB_PASSWORD=password123`, porta `5432`). Ajuste se preferir outras credenciais — só lembre de espelhar os mesmos valores no `docker-compose.yml`.

## Subindo o banco de dados

```bash
docker compose up -d
```

Isso sobe um container PostgreSQL 17 na porta `5432` (configurável via `DB_PORT` no `.env`).

## Migrations e seed

```bash
php artisan migrate --seed
```

Cria a tabela `products` e popula com 15 produtos fake via `ProductSeeder` (útil para testar listagem/filtros manualmente).

## Executando a aplicação

```bash
php artisan serve
```

API disponível em `http://127.0.0.1:8000/api/v1/products`.

## Executando os testes

```bash
php artisan test
# ou, com saída compacta:
php artisan test --compact
```

Os testes rodam com `sqlite` em memória e cache `array` (configurado em `phpunit.xml`), então **não dependem do container do PostgreSQL estar de pé**. Cobrem criação de produto, listagem com paginação, filtro por preço, validação de payload inválido e invalidação do cache (`tests/Feature/ProductApiTest.php`).

## Documentação da API

Base URL: `/api/v1/products`

Todas as respostas seguem o envelope padrão (via `App\Traits\ApiResponse`):

```json
{
  "data": {},
  "message": "string ou null",
  "errors": null
}
```

### Endpoints

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/products` | Lista produtos (com filtros e paginação) |
| POST | `/api/v1/products` | Cria um produto |
| GET | `/api/v1/products/{id}` | Consulta um produto |
| PUT/PATCH | `/api/v1/products/{id}` | Atualiza um produto |
| DELETE | `/api/v1/products/{id}` | Remove um produto |

### Filtros da listagem (query params, combináveis)

| Param | Tipo | Descrição |
|---|---|---|
| `search` | string | Busca por nome (case-insensitive, `ilike`) |
| `price_min` | numeric | Preço mínimo |
| `price_max` | numeric | Preço máximo |
| `stock_min` | integer | Estoque mínimo |
| `stock_max` | integer | Estoque máximo |
| `per_page` | integer | Itens por página (padrão: 15) |
| `page` | integer | Página atual (padrão: 1) |

> **Nota:** o enunciado (`instructions.md`) traz como exemplo os parâmetros `name`, `min_price` e `max_price`. Optamos por `search`, `price_min` e `price_max` (e adicionamos `stock_min`/`stock_max` para o filtro de estoque, que o enunciado menciona sem definir um nome fixo de parâmetro). O comportamento — busca por nome e filtro por faixa de valores, combináveis na mesma requisição — é o mesmo pedido no enunciado; só o nome dos parâmetros difere do exemplo literal.

### Exemplos de requisição

#### Criar produto

```
POST /api/v1/products
Content-Type: application/json
```
```json
{
  "name": "Teclado Mecânico",
  "description": "Teclado mecânico RGB",
  "price": 299.90,
  "quantity_in_stock": 10
}
```

Resposta `201 Created`:
```json
{
  "data": {
    "id": 21,
    "name": "Teclado Mecânico",
    "description": "Teclado mecânico RGB",
    "price": "299.90",
    "quantity_in_stock": 10
  },
  "message": "Produto criado com sucesso.",
  "errors": null
}
```

Resposta `422 Unprocessable Entity` (payload inválido, ex.: sem `name` e com `price` negativo):
```json
{
  "message": "The name field is required. (and 1 more error)",
  "errors": {
    "name": ["The name field is required."],
    "price": ["The price field must be greater than 0."]
  }
}
```

#### Listar produtos com filtros combinados

```
GET /api/v1/products?search=teclado&price_min=100&price_max=500&per_page=10&page=1
```

Resposta `200 OK`:
```json
{
  "data": {
    "items": [
      {
        "id": 21,
        "name": "Teclado Mecânico",
        "description": "Teclado mecânico RGB",
        "price": "299.90",
        "quantity_in_stock": 10
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 10,
      "total": 1
    }
  },
  "message": "Produtos listados com sucesso.",
  "errors": null
}
```

#### Consultar um produto

```
GET /api/v1/products/21
```

Resposta `200 OK`: mesmo formato de `data` do exemplo de criação. Resposta `404 Not Found` se o produto não existir.

#### Atualizar um produto

```
PUT /api/v1/products/21
Content-Type: application/json
```
```json
{
  "name": "Teclado Mecânico RGB",
  "description": "Teclado mecânico RGB, switches azuis",
  "price": 279.90,
  "quantity_in_stock": 8
}
```

Resposta `200 OK`: `data` com os campos atualizados e `message: "Produto atualizado com sucesso."`.

#### Remover um produto

```
DELETE /api/v1/products/21
```

Resposta `200 OK`:
```json
{
  "data": null,
  "message": "Produto excluído com sucesso.",
  "errors": null
}
```

### Collection do Insomnia

Arquivo [`insomnia_collection.json`](./insomnia_collection.json) na raiz do projeto — importar direto no Insomnia (`Import` → `From File`). Contém os 5 endpoints acima com exemplos de query params e body prontos, usando uma variável de ambiente `base_url` (padrão `http://127.0.0.1:8000/api/v1`).

## Cache

A listagem (`GET /api/v1/products`) é cacheada por combinação de filtros + paginação (`Cache::remember`, TTL de 5 minutos). A cada criação, atualização ou remoção de produto, uma versão interna do cache é incrementada, invalidando automaticamente todas as entradas de listagem anteriores — incluindo diferentes páginas e filtros — sem depender de cache tags (que não são suportadas pelo driver `database` usado em produção). Detalhes em `app/Services/ProductService.php`.
