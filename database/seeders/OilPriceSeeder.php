<?php

namespace Database\Seeders;

use App\Models\OilPrice;
use Illuminate\Database\Seeder;

/**
 * Example weekly prices, spec §6.
 *
 * Marked source = 'manual' and provisional = true, because they are invented
 * sample data. The Poolred importer stays disabled (spec §8), so nothing here
 * should ever claim to be an official figure.
 */
class OilPriceSeeder extends Seeder
{
    public function run(): void
    {
        // Eight weeks leading up to the 2026-27 campaign, drifting upward.
        $weeks = [
            ['2026-08-03', '2026-08-09', 3180.00, 41200.00],
            ['2026-08-10', '2026-08-16', 3240.50, 43800.00],
            ['2026-08-17', '2026-08-23', 3110.00, 39500.00],
            ['2026-08-24', '2026-08-30', 3290.75, 45100.00],
            ['2026-08-31', '2026-09-06', 3420.00, 47300.00],
            ['2026-09-07', '2026-09-13', 3355.25, 46900.00],
            ['2026-09-14', '2026-09-20', 3480.50, 48100.00],
            ['2026-09-21', '2026-09-27', 3510.00, 49600.00],
        ];

        // Virgin sits above extra early in the season, lampante below; the
        // spread narrows as the campaign progresses.
        $byQuality = [
            'extra' => 1.06,
            'virgin' => 1.00,
            'lampante' => 0.92,
        ];

        foreach ($weeks as [$start, $end, $base, $volume]) {
            foreach ($byQuality as $quality => $factor) {
                OilPrice::updateOrCreate(
                    ['week_start' => $start, 'quality' => $quality],
                    [
                        'week_end' => $end,
                        'avg_price_eur_t' => number_format($base * $factor, 2, '.', ''),
                        'volume_t' => number_format($volume * $factor, 2, '.', ''),
                        'provisional' => true,
                        'source' => 'manual',
                        'source_url' => null,
                        'published_at' => now(),
                    ],
                );
            }
        }
    }
}
