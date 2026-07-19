# Respostas — Perguntas Teóricas

## 4.1 API Resources no Laravel

**Objetivo:** API Resources são uma camada de transformação entre os models (Eloquent) e o JSON retornado pela API. Em vez de retornar o model diretamente (expondo todos os atributos e a estrutura interna do banco), a Resource define explicitamente quais campos aparecem na resposta e como são formatados.

No projeto, `app/Http/Resources/ProductResource.php` faz exatamente isso: retorna apenas `id`, `name`, `description`, `price` e `quantity_in_stock`, desacoplando a resposta pública da estrutura da tabela `products` (por exemplo, se no futuro a tabela ganhar colunas como `created_by` ou `internal_notes`, elas não vazam na API automaticamente).

**Situações em que são úteis:**
- **Esconder dados sensíveis ou internos** (senhas, chaves estrangeiras internas, timestamps que não interessam ao consumidor da API).
- **Renomear ou computar campos** sem alterar o schema do banco (ex.: transformar `price` em string formatada, ou juntar `first_name`/`last_name` em `full_name`).
- **Consistência entre endpoints** — o mesmo model pode aparecer em `index`, `show`, e como relacionamento aninhado de outro recurso; a Resource garante que o formato é sempre igual.
- **Versionamento de API** — permite ter `ProductResource` (v1) e `ProductResourceV2` (v2) retornando formatos diferentes do mesmo model, sem duplicar lógica de negócio.
- **Coleções** — `ProductResource::collection($products)` já lida com paginação/coleções de forma padronizada, sem precisar de `map()` manual no controller.

## 4.2 Organização de Validação em Laravel

Usar Form Requests (`app/Http/Requests/StoreProductRequest.php`, `UpdateProductRequest.php`) em vez de validar direto no controller traz vantagens em três frentes:

- **Organização do código:** o controller (`ProductController`) fica enxuto e focado em orquestrar a requisição (chamar o service, montar a resposta), sem misturar regras de validação com lógica de fluxo. Quem lê o controller entende o "o quê" acontece; quem precisa saber "quais campos são obrigatórios" vai direto na Request.
- **Manutenção:** as regras ficam centralizadas em um único lugar por operação (`rules()`). Alterar uma regra de validação (ex.: mudar `max:255` para `max:100` no nome do produto) não exige tocar no controller nem procurar validação espalhada em `if`s. O método `authorize()` também separa a pergunta "o usuário pode fazer isso?" da pergunta "os dados estão no formato certo?".
- **Reutilização:** a mesma Form Request pode ser reaproveitada em mais de uma rota/controller se necessário, e o Laravel injeta e valida automaticamente antes do método do controller rodar — não é preciso repetir `Validator::make(...)` em cada action. No projeto, `UpdateProductRequest` reutiliza a mesma estrutura de `StoreProductRequest`, só ajustando a regra de `unique` para ignorar o próprio produto (`Rule::unique(...)->ignore($product)`).

## 4.3 Testes Automatizados no Laravel

**1. Para que servem:**
Testes automatizados garantem que o comportamento esperado da aplicação continua funcionando conforme o código evolui. Eles servem para:
- Prevenir regressões — uma mudança em um lugar não quebra silenciosamente outro fluxo.
- Documentar o comportamento esperado (um teste de "cria produto com sucesso" é, na prática, uma especificação executável).
- Dar segurança para refatorar código (mudar a implementação interna sem medo, desde que os testes continuem passando).
- Detectar problemas antes de chegar em produção, rodando em CI a cada commit/PR.

**2. Como testar um endpoint da API com PHPUnit/Pest no Laravel:**

- **Onde o teste é criado:** dentro de `tests/Feature/`, já que testar um endpoint HTTP envolve o framework inteiro (rotas, middlewares, banco de dados) — diferente de `tests/Unit/`, reservado para testar classes isoladas sem bootar a aplicação. No projeto, o arquivo é `tests/Feature/ProductApiTest.php`, gerado com `php artisan make:test --pest ProductApiTest`.

- **Como o endpoint é testado:** o teste simula uma requisição HTTP contra a aplicação sem precisar de um servidor real, usando os helpers do Laravel (`$this->postJson()`, `$this->getJson()`, `$this->putJson()`, `$this->deleteJson()`). Em seguida, faz asserções sobre a resposta (`assertStatus()`, `assertJsonPath()`, `assertJsonCount()`) e, quando relevante, sobre o estado do banco (`assertDatabaseHas()`). Usa-se a trait `RefreshDatabase` (habilitada em `tests/Pest.php`) para garantir que cada teste rode com o banco limpo (migrations aplicadas do zero), e factories (`Product::factory()`) para popular dados de teste. Exemplo real no projeto: o teste `'creates a product'` faz um `postJson('/api/v1/products', $payload)`, confere `assertStatus(201)`, o conteúdo do envelope de resposta (`data.name`, `message`) e `assertDatabaseHas('products', [...])`.

- **Como executar os testes:** com os comandos padrão do Laravel — `php artisan test` (ou `php artisan test --compact` para saída resumida), ou filtrando por nome: `php artisan test --filter=ProductApiTest`. Como o projeto usa Pest, os mesmos comandos funcionam normalmente (Pest roda sobre o PHPUnit por baixo). Os testes usam SQLite em memória e cache em array (configurado em `phpunit.xml`), então rodam isolados, sem depender do PostgreSQL do `docker-compose.yml` estar no ar.
