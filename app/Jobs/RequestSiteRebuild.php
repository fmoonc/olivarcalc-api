<?php

namespace App\Jobs;

use App\Models\SiteRebuildRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ask Cloudflare Pages to rebuild the site (spec §8).
 *
 * Debounced: publishing three qualities in a row should produce one rebuild,
 * not three. A pending request absorbs the next call and extends its own
 * window, and only the last job in the window actually calls the deploy hook.
 *
 * Failing here is logged, not thrown: the content is already saved, and a
 * deploy hook outage should not roll back a published price. The admin panel
 * shows the failure (spec §9.8).
 */
class RequestSiteRebuild implements ShouldQueue
{
    use Queueable;

    /** Debounce window from spec §8. */
    public const DEBOUNCE_MINUTES = 5;

    public function __construct(
        public readonly string $reason = 'manual',
    ) {}

    public function handle(): void
    {
        $pending = SiteRebuildRequest::query()
            ->where('status', 'pending')
            ->where('requested_at', '>=', now()->subMinutes(self::DEBOUNCE_MINUTES))
            ->latest('requested_at')
            ->first();

        if ($pending !== null) {
            // Absorbed: just keep the original request's window rolling.
            $pending->forceFill(['requested_at' => now()])->save();

            Log::info('Site rebuild debounced', ['reason' => $this->reason]);

            return;
        }

        $request = SiteRebuildRequest::create([
            'reason' => $this->reason,
            'requested_at' => now(),
            'status' => 'pending',
        ]);

        $hook = config('services.cloudflare.deploy_hook_url');

        if ($hook === null || $hook === '') {
            $request->forceFill([
                'status' => 'failed',
                'dispatched_at' => now(),
                'response' => 'CLOUDFLARE_PAGES_DEPLOY_HOOK_URL no está configurado.',
            ])->save();

            Log::warning('Site rebuild skipped: no deploy hook configured');

            return;
        }

        try {
            $response = Http::timeout(10)->post($hook);

            $request->forceFill([
                'status' => $response->successful() ? 'dispatched' : 'failed',
                'dispatched_at' => now(),
                'response' => 'HTTP '.$response->status().' · '.mb_substr($response->body(), 0, 500),
            ])->save();
        } catch (\Throwable $e) {
            $request->forceFill([
                'status' => 'failed',
                'dispatched_at' => now(),
                'response' => $e->getMessage(),
            ])->save();

            Log::warning('Site rebuild failed', ['error' => $e->getMessage()]);
        }
    }
}
