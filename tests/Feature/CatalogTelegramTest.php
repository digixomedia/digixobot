<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogTelegramTest extends TestCase
{
    use RefreshDatabase;

    private int $updateId = 100;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.webhook_secret' => 'test-secret',
        ]);
        Artisan::call('catalog:import');
        DB::table('customers')->insert([
            'telegram_id' => 123456,
            'customer_number' => 'DXO-CATALOG-TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_all_catalog_navigation_and_product_details_are_available_in_telegram(): void
    {
        $categoryPayload = $this->sendCallback('categories');
        $categoryButtons = collect($categoryPayload['reply_markup']['inline_keyboard']);
        $categoryLabels = $categoryButtons->take(6)->map(fn (array $row) => $row[0]['text'])->all();

        $this->assertSame([
            '📂 🤖 AI & Productivity',
            '📂 💻 Developer Tools & Automation',
            '📂 🎨 Design, Video & Creative',
            '📂 📈 Business, Marketing & Support',
            '📂 🤝 Collaboration & Knowledge',
            '📂 🧘 Wellness & Focus',
        ], $categoryLabels);
        $this->assertSame('home', $categoryButtons->last()[0]['callback_data']);

        $visibleProducts = 0;
        foreach (DB::table('categories')->where('is_active', true)->orderBy('display_order')->get() as $category) {
            $products = DB::table('products')->where('category_id', $category->id)->where('is_active', true)->orderBy('display_order')->get();
            $productCallbacks = collect();
            $page = 0;

            do {
                $productPayload = $this->sendCallback($page === 0 ? 'category:'.$category->id : 'products_page:'.$category->id.':'.$page);
                $rows = collect($productPayload['reply_markup']['inline_keyboard']);
                $buttons = $rows->flatten(1);
                $productCallbacks = $productCallbacks->concat(
                    $buttons->pluck('callback_data')->filter(fn ($callback) => str_starts_with($callback, 'product:')),
                );
                $this->assertTrue($buttons->contains('callback_data', 'categories'));
                $this->assertTrue($buttons->contains('callback_data', 'home'));
                $page++;
                $hasNext = $buttons->contains('callback_data', 'products_page:'.$category->id.':'.$page);
            } while ($hasNext);

            $this->assertCount($products->count(), $productCallbacks);
            $this->assertSame($products->map(fn ($product) => 'product:'.$product->id)->values()->all(), $productCallbacks->all());
            $visibleProducts += $products->count();

            foreach ($products as $product) {
                $detailPayload = $this->sendCallback('product:'.$product->id);
                $plan = DB::table('plans')->where('product_id', $product->id)->first();
                $planButtons = collect($detailPayload['reply_markup']['inline_keyboard'])->flatten(1);

                $this->assertStringContainsString($product->name, $detailPayload['text']);
                $this->assertStringContainsString($product->description, $detailPayload['text']);
                $this->assertTrue($planButtons->contains('callback_data', 'plan:'.$plan->id));
                $this->assertTrue($planButtons->contains('callback_data', 'category:'.$category->id));
                $this->assertTrue($planButtons->contains('callback_data', 'home'));
                $this->assertStringContainsString($plan->validity, $planButtons->firstWhere('callback_data', 'plan:'.$plan->id)['text']);
            }
        }

        $this->assertSame(34, $visibleProducts);

        $mercury = DB::table('products')->where('slug', 'mercury-two-years')->first();
        $mercuryPlan = DB::table('plans')->where('product_id', $mercury->id)->first();
        $planPayload = $this->sendCallback('plan:'.$mercuryPlan->id);
        $planButtons = collect($planPayload['reply_markup']['inline_keyboard'])->flatten(1);

        $this->assertStringContainsString("<b>Price:</b> ₹1,999.00\n<b>Validity:</b> 2 Years", $planPayload['text']);
        $this->assertStringContainsString('<b>Availability:</b> Available', $planPayload['text']);
        $this->assertStringNotContainsString('<b>Stock:</b>', $planPayload['text']);
        $this->assertTrue($planButtons->contains('callback_data', 'buy:'.$mercuryPlan->id));
        $this->assertTrue($planButtons->contains('callback_data', 'product:'.$mercury->id));
        $this->assertTrue($planButtons->contains('callback_data', 'home'));
    }

    private function sendCallback(string $data): array
    {
        Http::fake();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => $this->updateId++,
                'callback_query' => [
                    'id' => 'callback-'.$this->updateId,
                    'from' => ['id' => 123456],
                    'message' => ['chat' => ['id' => 123456]],
                    'data' => $data,
                ],
            ])->assertNoContent();

        $sendMessage = Http::recorded(fn ($request) => str_ends_with($request->url(), '/sendMessage'))->last();
        $this->assertNotNull($sendMessage, "No Telegram message was sent for callback {$data}.");

        return $sendMessage[0]->data();
    }
}
