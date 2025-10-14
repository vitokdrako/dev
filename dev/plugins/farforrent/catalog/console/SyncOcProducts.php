<?php

namespace Farforrent\Catalog\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\InputOption;

class SyncOcProducts extends Command
{
    /**
     * Виклик:
     *   php artisan ff:sync:oc-products --since="2025-01-01 00:00:00" --limit=1000
     *   php artisan ff:sync:oc-products --since="2000-01-01 00:00:00" --limit=7000
     *   php artisan ff:sync:oc-products --dry   (переглянути без запису)
     */
    protected $name = 'ff:sync:oc-products';
    protected $description = 'Incremental sync: OpenCart -> farforrent_catalog_products';

    public function handle()
    {
        $since = $this->option('since') ?: Carbon::now()->subDay()->toDateTimeString();
        $limit = (int) ($this->option('limit') ?? 1000);
        $dry   = (bool) $this->option('dry');

        $this->info('Since: '.$since.' | Limit: '.$limit.' | Dry: '.($dry ? 'yes' : 'no'));

        // --- 1) підключення до OC та його префікс
        $oc = DB::connection('opencart');
        $p  = $oc->getTablePrefix(); // зазвичай 'oc_'

        // --- 2) дістаємо language_id для української (uk-ua/uk)
        $langs = $oc->select("SELECT language_id, code FROM {$p}language");
        $lidUa = null;
        foreach ($langs as $L) {
            if ($L->code === 'uk-ua' || $L->code === 'uk') { $lidUa = (int)$L->language_id; break; }
        }

        // --- 3) вибірка нових/змінених товарів + назви
        $sql = "
            SELECT
              p.product_id,
              p.model,
              p.ean,
              p.quantity,
              p.image,
              p.price,
              p.length,
              p.width,
              p.height,
              p.date_added,
              p.date_modified,

              ua.name AS name_ua,
              (SELECT pd_any.name
                 FROM {$p}product_description pd_any
                WHERE pd_any.product_id = p.product_id
                ORDER BY pd_any.language_id ASC
                LIMIT 1) AS name_any

            FROM {$p}product p
            ".($lidUa !== null
                ? "LEFT JOIN {$p}product_description ua
                       ON ua.product_id = p.product_id AND ua.language_id = :lidUa"
                : "LEFT JOIN {$p}product_description ua
                       ON 1=0")."
            WHERE GREATEST(p.date_modified, p.date_added) > :since
            ORDER BY p.date_modified ASC
            LIMIT :lim
        ";

        $bind = [
            'since' => $since,
            'lim'   => $limit,
        ];
        if ($lidUa !== null) $bind['lidUa'] = $lidUa;

        $rows = $oc->select($sql, $bind);
        $this->info('OC changed rows: '.count($rows));

        foreach ($rows as $r) {
            // ---- Назва українською (або будь-яка)
            $nameUa = $this->cleanName($r->name_ua ?? $r->name_any ?? null);

            // ---- SKU: спершу застосуємо префікс (ENV FF_SKU_PREFIX), потім унікалізуємо V-кою при колізії
            $rawSku = trim((string)$r->model);
            $sku    = $this->applySkuPrefix($rawSku);
            $sku    = $this->ensureUniqueSku($sku, (int)$r->product_id);

            $payload = [
                'id'           => (int) $r->product_id, // наш id = OC product_id
                'sku'          => $sku,                 // унікалізований sku
                'damage_price' => $r->ean,              // ean -> damage_price
                'quantity'     => (int) $r->quantity,
                'rent_price'   => (float) $r->price,    // price -> rent_price
                'length'       => (float) $r->length,
                'width'        => (float) $r->width,
                'height'       => (float) $r->height,
                'name_ua'      => $nameUa,
                'name_en'      => null,                 // зараз не заповнюємо
                'created_at'   => $r->date_added,
                'updated_at'   => $r->date_modified ?: $r->date_added,
            ];

            if ($dry) {
                $this->line('DRY -> ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
                continue;
            }

            // upsert по id (id = product_id зі старої БД)
            DB::table('farforrent_catalog_products')->updateOrInsert(
                ['id' => $payload['id']],
                array_merge($payload, ['updated_at' => Carbon::now()->toDateTimeString()])
            );
        }

        $this->info($dry ? 'DRY run finished.' : 'Sync finished.');
        return 0;
    }

    // ----------------- helpers -----------------

    protected function cleanName(?string $name): ?string
    {
        if (!$name) return null;
        // мінімальні чистки, за потреби доповнимо
        $name = trim($name);
        return $name !== '' ? $name : null;
    }

    /**
     * Додаємо префікс до SKU (за потреби).
     * Префікс можна задати у .env: FF_SKU_PREFIX="OC-"
     */
    protected function applySkuPrefix(string $sku): string
    {
        $prefix = env('FF_SKU_PREFIX', '');
        if ($prefix === '') return $sku;

        // якщо вже починається з цього префікса — не дублюємо
        if (stripos($sku, $prefix) === 0) return $sku;
        return $prefix . $sku;
    }

    /**
     * Гарантуємо унікальність SKU у farforrent_catalog_products.
     * Якщо знайдений інший запис з таким же sku — додаємо в кінець "V"
     * доти, доки не стане унікальним.
     *
     * @param string $sku первинний sku (вже з префіксом, якщо він заданий)
     * @param int    $currentId id товару, який апдейтимо (щоб не вважати дублем самого себе)
     */
    protected function ensureUniqueSku(string $sku, int $currentId): string
    {
        $candidate = $sku;
        while (true) {
            $exists = DB::table('farforrent_catalog_products')
                ->where('sku', $candidate)
                ->where('id', '!=', $currentId)
                ->exists();

            if (!$exists) return $candidate;

            $candidate .= 'V'; // додаємо “V” і перевіряємо знову
        }
    }

    protected function getOptions()
    {
        return [
            ['since', null, InputOption::VALUE_OPTIONAL, 'Починати з дати (YYYY-MM-DD HH:MM:SS)'],
            ['limit', null, InputOption::VALUE_OPTIONAL, 'Скільки рядків за раз', 1000],
            ['dry',   null, InputOption::VALUE_NONE,     'Лише показати, без запису'],
        ];
    }
}
