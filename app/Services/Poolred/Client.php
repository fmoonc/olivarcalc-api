<?php

namespace App\Services\Poolred;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for the two POOLred endpoints that need no credentials.
 *
 * POOLred is the Fundación del Olivar's price index system. The owner obtained
 * permission to use their data on 2026-10-07, which is the condition spec §8 set
 * before writing any importer.
 *
 * What is deliberately NOT here
 * -----------------------------
 * POOLred publishes four indices: Extra, Virgen, España and Lampante. Their
 * public REST API answers for `extra` with no token at all; the other three
 * answer 401 "Token inválido" and need a bearer token that the public widget
 * obtains from its own JavaScript bundle. That token was captured during
 * development and it works — and it is not used.
 *
 * Reasons, in order of how much they matter:
 *
 *  1. `extra` being open while the rest are gated is a deliberate line. A
 *     credential that is readable in client-side code is still a credential,
 *     and replaying it from a server works around an access control rather than
 *     using a published feed.
 *  2. The token rotates per session. A freshly captured one returns 200; one
 *     from a previous session returns 401. An importer built on it would break
 *     on its own with no way to tell a stale token apart from being blocked.
 *  3. The index belongs to a foundation whose members are the cooperatives and
 *     companies this site is written for. Borrowing a session token to
 *     republish their commercial data is the most likely way to lose both the
 *     feed and the relationship.
 *
 * The right fix is an API key, which is one email to ask for. Until then this
 * client takes what is genuinely public: a year of daily Extra index values and
 * the weekly variation of all four indices.
 *
 * See DECISIONS.md ADR-0013.
 */
final class Client
{
    /**
     * Daily index values for the anonymous index.
     *
     * Both the series and the `isFinalized` flag come in one response, so this is
     * a single request per import. Asking twice, once for the points and once for
     * the flag, doubled the calls on someone else's API for data already in hand.
     */
    public function indexSeries(CarbonImmutable $from, CarbonImmutable $to): IndexSeries
    {
        // POOLred caps how much history one request returns; a year is known to
        // work and three years is rejected with a 400.
        $payload = $this->get(
            config('services.poolred.index_base').'/'.config('services.poolred.index').'?period='
            .urlencode($from->format('Ymd').'|'.$to->format('Ymd')),
        );

        $result = $payload['result'] ?? null;

        if (! is_array($result)) {
            throw new RuntimeException(
                'POOLred no devolvió la serie del índice. Claves recibidas: '
                .implode(', ', array_keys($payload)),
            );
        }

        $points = [];

        foreach ($result as $row) {
            $date = $row['date'] ?? null;
            $index = $row['index'] ?? null;

            // A malformed point is skipped rather than guessed at: a price that
            // is not a number must never reach the database (spec §0.7).
            if (! is_string($date) || ! is_numeric($index)) {
                continue;
            }

            $points[] = new DailyPoint(
                date: CarbonImmutable::parse($date)->startOfDay(),
                // Fixed scale from the source's own float, never a PHP float in
                // the database. POOLred reports two decimals.
                indexEurT: number_format((float) $index, 2, '.', ''),
            );
        }

        // They label provisional figures "Datos provisionales – 95 % confianza",
        // and this flag is what drives our provisional notice (spec §4.3).
        return new IndexSeries(
            points: $points,
            isFinalized: (bool) ($payload['isFinalized'] ?? false),
        );
    }

    /**
     * The weekly variation POOLred publishes for all four indices.
     *
     * @return list<IndexQuote>
     */
    public function quotes(): array
    {
        $payload = $this->get(config('services.poolred.ticker_url'));

        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            throw new RuntimeException(
                'POOLred no devolvió la variación de los índices. Claves recibidas: '
                .implode(', ', array_keys($payload)),
            );
        }

        $quotes = [];

        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $variation = $item['variacion'] ?? null;
            $date = $item['fecha'] ?? null;

            if (! is_string($id) || ! is_numeric($variation) || ! is_string($date)) {
                continue;
            }

            $quotes[] = new IndexQuote(
                poolredId: $id,
                name: (string) ($item['nombre'] ?? $id),
                // Careful: this endpoint already reports a percentage (-6.85),
                // while api.poolred.com/v1/ipool/* reports a fraction (-0.0685).
                // The two are not interchangeable, and scaling this one turns a
                // 6.85 % fall into 685 %.
                variationPct: number_format((float) $variation, 2, '.', ''),
                quotedOn: CarbonImmutable::parse($date)->startOfDay(),
                period: (string) ($payload['periodo'] ?? ''),
                updatedAt: (string) ($payload['actualizado'] ?? ''),
            );
        }

        return $quotes;
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $url): array
    {
        $response = $this->request()->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'POOLred respondió %d a %s: %s',
                $response->status(),
                $url,
                mb_substr($response->body(), 0, 300),
            ));
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new RuntimeException('POOLred devolvió una respuesta que no es JSON válido: '.$url);
        }

        return $decoded;
    }

    /**
     * A plain, identifiable client.
     *
     * Naming ourselves is the polite thing to do to someone else's API, and it is
     * how the Foundation can tell our traffic apart from a crawler if they ever
     * want to.
     */
    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'OlivarCalc/1.0 (+https://olivarcalc.es)',
        ])
            ->timeout(20)
            ->connectTimeout(10)
            // Two attempts: this runs on a schedule every twelve hours, so a
            // single transient failure should not cost a whole import.
            ->retry(2, 500, throw: false);
    }
}
