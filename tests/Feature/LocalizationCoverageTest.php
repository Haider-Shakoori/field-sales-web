<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationCoverageTest extends TestCase
{
    public function test_dari_and_pashto_keep_the_same_translation_keys_as_english(): void
    {
        $english = $this->translations('en');
        $dari = $this->translations('fa');
        $pashto = $this->translations('ps');

        $englishKeys = array_keys($english);
        $dariKeys = array_keys($dari);
        $pashtoKeys = array_keys($pashto);

        sort($englishKeys);
        sort($dariKeys);
        sort($pashtoKeys);

        $this->assertSame($englishKeys, $dariKeys);
        $this->assertSame($englishKeys, $pashtoKeys);

        foreach ([
            'Customer messaging',
            'Customer portal',
            'AI insights',
            'Order items',
            'Collection detail',
            'Recommended actions',
            'Create portal link',
            'Payment reminder',
        ] as $key) {
            $this->assertArrayHasKey($key, $dari);
            $this->assertArrayHasKey($key, $pashto);
            $this->assertNotSame($key, $dari[$key]);
            $this->assertNotSame($key, $pashto[$key]);
        }
    }

    public function test_high_traffic_views_route_labels_through_translation_helpers(): void
    {
        $orders = file_get_contents(
            resource_path('views/admin/orders/show.blade.php'),
        );
        $collections = file_get_contents(
            resource_path('views/admin/collections/show.blade.php'),
        );
        $ai = file_get_contents(
            resource_path('views/admin/ai-insights/index.blade.php'),
        );

        $this->assertStringContainsString("__('Order items')", $orders);
        $this->assertStringContainsString("__('Qty')", $orders);
        $this->assertStringContainsString("__('Collection detail')", $collections);
        $this->assertStringContainsString("__('Payment method')", $collections);
        $this->assertStringContainsString(
            "__($recommendation['message'], $recommendation['message_params'] ?? [])",
            $ai,
        );
    }

    private function translations(string $locale): array
    {
        $contents = file_get_contents(lang_path($locale.'.json'));
        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);

        return $decoded;
    }
}
