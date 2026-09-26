# DECISIONS — olivarcalc-api

Registro de decisiones no triviales (spec §0.5). Formato ADR corto: contexto,
decisión, consecuencia. Si algo queda abierto y no trivial, se añade aquí.

---

## ADR-0001 — Local stack is Herd + DBNGIN, not Docker Compose

**Contexto.** Spec §13.2 pide `docker-compose.yml` con `app`, `mysql:8`, `redis`
y `mailpit`, y que los comandos se documenten como `make up`, `make test`,
`make seed`. El entorno de desarrollo del propietario ya tiene Laravel Herd
(PHP 8.5) y DBNGIN (MySQL 8.0.27), y se prefirió no depender de Docker
para el día a día.

**Decisión.** El local usa Herd para PHP y DBNGIN para MySQL 8, ambos nativos
del host. Los targets `make up` / `make test` / `make seed` de la spec se
reformulan como `make setup` / `make test` / `make seed` sobre comandos
directos. `docker/Dockerfile` **se conserva** porque producción corre en
Coolify (§13.3) y sí necesita una imagen. `docker-compose.yml` se elimina: sin
Docker local no habría quien lo levante, y un compose sin usar es deuda.

**Consecuencias.**
- `make db-create` documenta el paso de crear base de datos y usuario.
- Los tests usan una base de datos aparte, `olivarcalc_test` (ver ADR-0003), así
  que `RefreshDatabase` nunca toca datos de desarrollo.
- Si otra persona entra al repo sin Herd ni DBNGIN, el README dice qué
  servicios hacen falta en `127.0.0.1:3306`. El coste de onboarding sube
  respecto a Docker; la velocidad diaria baja mucho.

---

## ADR-0002 — Versions pinned to the current stable, not what the spec assumed

**Contexto.** Spec §0.4 pide la última versión estable de cada herramienta y
anotar aquí cuando la indicada en la spec ya no es la actual. La spec no fija
versiones concretas, así que se instalaron las vigentes.

**Decisión.**

| Paquete | Versión | Nota |
|---|---|---|
| Laravel | 13.33.0 | |
| PHP | 8.5.0 | spec pide 8.3+ |
| Filament | 5.8.4 | |
| Laravel Sanctum | 4.3.3 | |
| Laravel Socialite | 5.31.0 | |
| Scramble | 0.13.45 | genera OpenAPI 3.1.0 |
| Pest | 5.2.1 | requiere PHPUnit 13 |
| PHPUnit | 13.3.4 | el skeleton de Laravel 13 fijaba 12; hubo que subirlo |
| Larastan | 3.12.2 | nivel 6 (spec §0.6) |
| Pint | 1.32.1 | |
| MySQL | 8.0.27 | vía DBNGIN |

**Consecuencias.** Dos desviaciones que costarían tiempo si no constaran:

- **Pest 5 exige PHPUnit 13**, pero el skeleton de `laravel/laravel` 13 fija
  `phpunit/phpunit: ^12.5.12`. `composer require pestphp/pest` falla solo con un
  error de resolución confuso. Hay que subir **primero** el requisito raíz de
  PHPUnit a `^13.3` con `-W`, y solo después instalar Pest.
- **Larastan queda fuera de `tests`.** No modela los closures de `it()` de Pest,
  así que cada test HTTP reporta un `undefined method get()` falso. El análisis
  estático se limita a `app`, `database` y `routes`.

---

## ADR-0003 — Tests run against MySQL 8, not SQLite

**Contexto.** El `phpunit.xml` por defecto de Laravel apunta a SQLite en
memoria. Spec §13.7 pide "Pest (con MySQL 8 como servicio)".

**Decisión.** `phpunit.xml` fija `DB_CONNECTION=mysql` contra la base de datos
`olivarcalc_test`, en `127.0.0.1:3306` con el usuario `olivarcalc`.

**Consecuencias.** Motivos por los que SQLite habría dado una falsa confianza:

- `utf8mb4` / `utf8mb4_unicode_ci` (spec §6) no se aplican en SQLite.
- `DECIMAL(12,2)`, `DECIMAL(6,3)` y `ENUM` no existen igual en SQLite, y el
  redondeo de la aritmética decimal es justo lo que la spec prohíbe
 aproximar con flotantes (§0.7).
- MySQL impone límites de longitud de índice que con `utf8mb4` (4 bytes por
  carácter pueden romper un `UNIQUE` compuesto; SQLite no los comprueba.

Base de datos de test separada porque `RefreshDatabase` hace `DROP TABLE`:
compartirla con desarrollo borraría los datos locales.

---

## ADR-0004 — Enum casts documented with `@property`, not ignored

**Contexto.** Larastan infera el tipo de una propiedad desde la migración, así
que `User::$role` le queda como `'admin'|'user'` y no como el enum. Eso hace
que `isAdmin()` parezca siempre `false` y que asignar `UserRole::Admin` parezca
inválido, aunque el cast de Laravel funcione.

**Decisión.** Declarar `@property UserRole $role` en el modelo y poner
`treatPhpDocTypesAsCertain: false` en `phpstan.neon`. Sin `ignoreErrors`.

**Consecuencias.** Larastan queda limpio a nivel 6 sin silenciar nada. El
ignore sería lo más rápido, pero un aviso que "siempre es falso" en un
chequeo de admin es exactamente el tipo de aviso que uno no quiere
aprender a ignorar. Revisar en la próxima subida de Larastan.

---

## ADR-0005 — CORS is configured through `config/cors.php`, not `withCORS()`

**Contexto.** Spec §7.5 pide CORS con `supports_credentials` y orígenes por
variable de entorno. `withCORS()` no existe en `ApplicationBuilder` de Laravel
13: el middleware global `HandleCors` ya está activo y la config va en
`config/cors.php`.

**Decisión.** Crear `config/cors.php` leyendo `CORS_ALLOWED_ORIGINS`, con
`supports_credentials => true` y `exposed_headers => ['ETag']` (los endpoints
públicos llevan ETag, spec §7.1).

**Consecuencias.** Los orígenes de local, staging y producción difieren solo por
entorno. `SANCTUM_STATEFUL_DOMAINS` sigue siendo la pieza que habilita la
autenticación por cookie entre `olivarcalc.es` y `api.olivarcalc.es`.

---

## ADR-0006 — Filament 5 moved every action to `Filament\Actions\`

**Contexto.** Building the admin resources (spec §9) against Filament 5.8
produced a batch of classes that do not exist: `Filament\Tables\Actions\Action`,
`Filament\Tables\Actions\CreateAction`, `Filament\Schemas\Components\Placeholder`,
and `Panel::navigationSort()`.

**Decisión.** Use `Filament\Actions\*` for row, bulk, header and create actions,
`Filament\Forms\Components\Placeholder` for read-only form content, and
`NavigationGroup` + `NavigationItem` for navigation. No shims.

**Consecuencias.** The Filament 5 upgrade moved the whole action surface out of
the `Tables` and `Schemas` namespaces. A `use` statement copied from a
Filament 4 tutorial will not resolve.

Two related traps that cost real time:

- **`NavigationGroup::items()` wants `NavigationItem[]`, not resource class
  strings.** Pass `NavigationItem::make(ArticleResource::class)`.
- **`money()` takes `(currency, divideBy, locale, decimalPlaces)`.** The third
  argument is a locale string, not a separator pair, and `divideBy` is `1` for a
  value already in euros per tonne.

---

## ADR-0007 — A parameter set has no delete action anywhere

**Contexto.** The `make:filament-resource` scaffolding adds a `DeleteAction` to
the edit page and a `DeleteBulkAction` to the list toolbar.

**Decisión.** Both removed, and a test asserts the absence.

**Consecuencias.** Spec §5.1: parameter sets are never deleted, because saved
calculations store `parameter_set_id` and `calc_version`. A deleted set would
make an old settlement unexplainable. Publishing archives the previous set
instead. The same reasoning does not apply to articles, which are soft-deleted
and restorable.

---

## ADR-0008 — `CheckPriceAlerts` is not wired yet, on purpose

**Contexto.** Spec §8 says publishing prices fires an event that evaluates price
alerts and requests a site rebuild. `OilPricesPublished` exists and is dispatched
from the panel.

**Decisión.** Only `RequestSiteRebuild` is wired. `CheckPriceAlerts` lands in
Fase 5.

**Consecuencias.** A listener that exists but does nothing is worse than no
listener: it looks like notifications are being sent. There are no alerts in the
MVP yet (the table is empty and no user can create one), so nothing is silently
dropped. The event carries a comment saying exactly this.
