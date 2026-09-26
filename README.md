# olivarcalc-api

API REST y panel de administración de OlivarCalc. Laravel 13 + Filament 5.

La especificación completa del proyecto está en `../ESPECIFICACION_OLIVARCALC.md`
— este README solo cubre cómo trabajar con **esta** repo.

> **Las fórmulas no viven aquí.** `@olivar/calculations` (repo web) es la única
> fuente de verdad de los cálculos. Esta API no recalcula: valida la estructura
> de `inputs` y guarda lo que el usuario vio. Spec §7.4.

## Requisitos

- **PHP 8.3+** (probado con 8.5.0) con `pdo_mysql`, `intl`, `bcmath`, `gd`,
  `zip`, `redis`
- **Composer 2**
- **MySQL 8** en `127.0.0.1:3306` — en local, el de DBNGIN
- **Redis** en `127.0.0.1:6379` (opcional en local; sin él, cache y cola caen a
  MySQL)
- **Mailpit** en `127.0.0.1:1025`, UI en <http://localhost:8025> (opcional)

En macOS, [Laravel Herd](https://herd.laravel.com) cubre PHP y Composer.
Ver `DECISIONS.md` ADR-0001 para por qué no se usa Docker en local.

## Puesta en marcha

```bash
make setup          # composer install, .env, APP_KEY
make db-create      # solo la primera vez: crea olivarcalc y olivarcalc_test
make migrate
make seed
make serve          # http://localhost:8000
```

El panel de Filament queda en <http://localhost:8000/admin> (spec §9, solo
usuarios con `role = 'admin'`).

## Comandos

| Comando | Qué hace |
|---|---|
| `make test` | Suite Pest completa contra MySQL 8 |
| `make test-feature` / `make test-unit` | Solo un directorio |
| `make lint` | Pint en modo check |
| `make lint-fix` | Pint corrige |
| `make analyse` | Larastan nivel 6 |
| `make ci` | `lint` → `analyse` → `test`, el orden de GitHub Actions |
| `make openapi` | Exporta el contrato a `storage/app/openapi.json` |
| `make routes` | Lista de rutas |
| `make migrate-fresh` | Borra, migra y siembra |

Un solo test:

```bash
php artisan test --filter="stores the users table as utf8mb4"
```

## Convenciones

- **Código, nombres y comentarios en inglés. Texto para el usuario en español
  de España** (spec §0.1). `APP_LOCALE=es`.
- **Dinero y porcentajes nunca en `number`/`float`.** Importes en
  `DECIMAL(12,2)`, precios por tonelada en `DECIMAL(10,2)`, porcentajes en
  `DECIMAL(6,3)`. En la API viajan como **string decimal** (`"1234.56"`), nunca
  como número JSON (spec §7.1).
- Directorios por capa, según spec §3.2: `app/Http/{Controllers,Requests,Resources}/Api/V1`,
  `app/Actions`, `app/Services/{ExpoPush,SiteRebuild,Poolred}`, `app/Filament`.
- `parameter_sets` se archivan, **no se borran**: los cálculos guardados los
  referencian (spec §5.1).

## Pruebas

Corren contra **MySQL 8**, no SQLite, y sobre la base de datos
`olivarcalc_test` para que `RefreshDatabase` no destruya tus datos de
desarrollo (ADR-0003). Si MySQL no está levantado, los tests fallan: es
intencionado, porque SQLite ocultaría diferencias de `utf8mb4`, `DECIMAL` y
`ENUM`.

## Contrato OpenAPI

Scramble lee los FormRequests, los API Resources y los PHPDoc; no hay un YAML
paralelo que mantener. Para regenerar el contrato que consume el repo web:

```bash
make openapi
```

El flujo completo está en `DECISIONS.md` y en el `AGENTS.md` de la raíz.

## Despliegue

`develop` → staging, tags `v*` → producción, vía Coolify sobre un VPS de
Hetzner (spec §13.3). `docker/Dockerfile` es la imagen de producción; la
imagen de desarrollo con `artisan serve` ya no se mantiene.
