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

---

## ADR-0009 — A Filament column name must not match a model method

**Contexto.** `ArticleResource` was working, then `/admin/categories` returned
500 with `App\Models\Category::publishedArticlesCount must return a relationship
instance`. The offending code was a column counting published guides, which had
been moved onto a model method to work around `counts()` not accepting a
constraint.

**Decisión.** Use a `withCount` subquery aliased to a name that is not a model
method, applied through `modifyQueryUsing()`.

**Consecuencias.** Filament treats any method whose name matches a column as a
relationship to eager-load, so a helper method with a column-like name throws at
render time. Keep computed values in subquery aliases.

**The more important lesson is about the tests.** The suite was green while the
page was broken, because every panel test rendered an *empty* table, and Filament
never evaluates a column when there are no rows. The panel tests now create a
record per resource before asserting. A 200 from an empty table proves only that
the route resolves, not that the screen works.

---

## ADR-0011 — What was actually verified among the 21 parameters, and what was not

**Contexto.** Spec §5.2 seeds 21 parameters with `verified = false` and the owner
checks each one. Rather than guess, the two that could be settled from primary
law were read off the consolidated text of **Ley 37/1992** (BOE-A-1992-28740,
last updated 28/02/2026).

**Settled, now seeded verified with their article:**

| key | value | source |
|---|---|---|
| `vat.reagp_compensation_pct` | 12 | art. 130.5.1.º, verbatim: «El 12 por 100, en las entregas de productos naturales obtenidos en explotaciones agrícolas o forestales» |
| `vat.olive_general_rate_pct` | 10 | art. 91.Uno.1.1.º (produce for human nutrition) and 1.2.º (the vegetables used to obtain it, i.e. the olives sent to the mill) |

The second one was a `TODO` in the spec. Until it had a value, a grower paying
under the general regime got *"El parámetro ... todavía no tiene valor
confirmado"* instead of a settlement, so this is a working tool again, not just
a tidier record.

**Left unverified, on purpose.** The other 19 fall into three groups, and none
of them is "a number I could not be bothered to look up":

- **Volatile by nature** — the 8 provincial wages and `labor.employer_cost_pct`
  change every January or every season. Verifying them would rot.
- **Not a single figure** — the 6 cost presets are per comarca and per campaign,
  so there is no one defensible number for Andalucía.
- **Not law** — `campaign.start_month_day` is a sector convention, and
  `oil.density_kg_per_l` is a physical constant the spec sourced to a
  "referencia técnica" rather than a norm.

Each of the 19 now carries a note in Filament saying which article or bulletin
to open, and where there is a trap: the wage paid on olives is usually the
*plus de recolección*, not the convenio's base salary, and it is revised
mid-season.

**`source_url` was deliberately left empty on all 19.** It means "this value came
from here", and the panel renders it as a link on verified rows. Filling it with
"go and look here" would attach a source to figures that have none. The
where-to-look belongs in `notes`, which is not exposed by the public API.

**Open question, flagged not fixed.** `irpf.withholding_base_includes_vat` is
seeded `false` by the spec and I believe it should be `true`: for deliveries of
goods the withholding base should include indirect taxes. I could not reach art.
95 of the Reglamento del IRPF (RD 439/2007) — the BOE consolidated view and
EUR-Lex both refused — so it stays as the spec has it, with the impact written
into the note. On the 10.000 kg reference case the retention goes from 87,20 €
to 97,66 €, a 12 % difference the grower feels.

**Consequence.** The §4.3 warning still shows, correctly: 19 figures are still
the owner's to confirm. The two that are no longer in doubt stop being treated
as such, and the seeder ships them verified so a fresh environment matches the
live one instead of warning about figures nobody doubts.

---

## ADR-0012 — The seeder only ever writes to a draft

**Contexto.** Two bugs, both found while trying to record a verified figure, and
both of the same shape: the seeder wrote to a version it did not own.

1. `ParameterSetSeeder` used `updateOrCreate`, which reset `status`. Re-seeding
   silently demoted the **published** set to a draft and left a stale
   `published_at` on it. Nothing in the app broke loudly; the site just stopped
   having a live parameter set.
2. "Duplicar el vigente" in the panel passed Eloquent models to `createMany()`,
   which only accepts arrays, so the action died with a `TypeError` the first
   time anyone pressed it. It had never been exercised.

A third, quieter one: re-seeding reset `verified` to false, which would have
discarded the owner's verification work.

**Decisión.**

- The seeder writes only to a **draft**. A published or archived set is left
  byte-for-byte alone and the seeder says so out loud.
- A **verified parameter is never modified** by the seeder — value, provenance
  and note. If the owner has verified a row, that row is theirs.
- Duplicating is `App\Actions\DuplicateParameterSet`, which the Filament action
  and the console both call, rather than logic living in a page class.
- Corrections arrive as a new version (`2026-27.1` → `2026-27.2`), which is what
  §5.1 already required and what the two verified figures did.

**Consecuences.** `tests/Feature/ParameterSetVersioningTest.php` pins all of it:
the copy reassigns its foreign key, the version increments, the published set
survives untouched, a re-seed cannot take a set offline or rewrite history, and
the undefendable values stay empty rather than plausible.

---

## ADR-0013 — POOLred: only the two endpoints that need no credentials

**Contexto.** Spec §8 said not to write the importer until the owner confirmed the
terms of use. The owner confirmed on **2026-10-07**, which satisfies the gate, and
then asked for a scraper against poolred.com. Investigating that turned up more
than the spec anticipated.

**What POOLred actually exposes.**

| Endpoint | Credential | Yields |
|---|---|---|
| `api.poolred.com/v1/ipool/extra?period=…` | none | 366 daily index points, a full year |
| `poolred.com/wp-json/poolred-ticker/v1/datos` | none | weekly variation, all four indices |
| `api.poolred.com/v1/ipool/{virgen,vl1,espania}` | **session token** | daily series |

The first two are public REST APIs; the second is WordPress's own ticker. Neither
is scraping. The prices are not in poolred.com's HTML at all: they load from
`ui.poolred.com`, a Vue app whose HTML is an empty shell.

**The token, and why it is not used.** The public widget sends a JWT with
`role: "public"` in its bundle. It was captured during development and it works.
It is not used, for three reasons in order of weight:

1. `extra` answering without a token while the other three do not is a deliberate
   line. Readable in client code is not the same as licensed for server-side reuse.
2. **It rotates per session.** Measured: a token captured and replayed immediately
   returns 200; the same token from a previous run returns 401. An importer built
   on it would break on its own, with no way to tell a stale token from being blocked.
3. The index belongs to a foundation whose members are the cooperatives and growers
   this site is written for. Replaying a session token to republish their commercial
   data is the most likely way to lose both the feed and the relationship.

The fix is an API key, which is one email. Two tests pin the decision: one asserts
no `Authorization` header is ever sent, the other that only `/ipool/extra` is
requested.

**What ships.** `poolred:import`, cron `0 0,12 * * *`, inert unless
`POOLRED_IMPORT_ENABLED=true`. The daily series is averaged per calendar week into
`oil_prices` with `source = 'poolred'`; the four variations go to a new
`oil_index_quotes` table because a percentage is not a €/tonne and does not belong
in a DECIMAL money column. `volume_t` stays null because their public data has no
volume. `provisional` mirrors their own `isFinalized` rather than deciding.

**Three findings worth recording.**

- POOLred's two endpoints disagree on units: the REST series returns a fraction
  (`-0.0685`) and the ticker a percentage (`-6.85`). Scaling the second turned a
  6.85 % fall into 685 %, caught only by printing the stored value. A test pins it.
- POOLred labels provisional data *"95 % de confianza"*, so `provisional` on our
  side is their judgement, not ours.
- Weekly averaging is deliberate: an index is a level, not a trade count, so a
  grower reading "that week's price" wants the level it held at, not Friday's close.

**The fabricated data is gone.** The 24 invented rows are deleted, and the page is
retitled "Índice iPOOL del aceite de oliva" with the Fundación del Olivar named as
the Dataset creator. It says what it is — a market index, not an official quotation
or a Lonjas price — because calling someone else's index "el precio del aceite"
would misrepresent it and them.

---

## ADR-0014 — The chart picks its default from the data it is given

**Contexto.** `PriceChart` defaulted to `virgin`. The old price page happened to
carry all three qualities, so nobody noticed. The POOLred page carries only
`extra`, and the chart rendered "No hay datos para esta calidad" with no way back —
no chart, and the quality tabs still offered two selections that showed nothing.

**Decisión.** Derive both the default quality and the tab list from the series the
component receives. A page that ships one index plots that index, and a page that
ships three still offers three tabs. The caption also follows the data's own
`source`, so a POOLred index describes itself as an index.

Cheaper than the alternatives: defaulting to "whichever exists" was the same
decision with the failure mode left in. Removing the tabs outright would have
thrown away real functionality for a future page that does have three qualities.

**Consequence.** Two E2E tests cover it, including the absence of dead tabs.
