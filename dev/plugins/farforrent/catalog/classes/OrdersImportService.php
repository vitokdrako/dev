<?php namespace Farforrent\Catalog\Classes;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OrdersImportService
{
    /**
     * Імпорт status_id = 2 (нові/в обробці) з OpenCart у наші таблиці.
     * - farforrent_orders.id          = oc_order.order_id
     * - farforrent_orders.customer_id = oc_order.customer_id (або NULL якщо 0)
     * - farforrent_orders.rent_total  = oc_order.total
     * - farforrent_orders.note        = oc_order.comment
     * - farforrent_orders.created_at  = oc_order.date_added (лок. TZ)
     * - farforrent_orders.status      = 'draft'
     * - farforrent_order_items.order_id = той самий ID
     * + farforrent_orders.date_from   = DATE(oc_order_simple_fields.rent_issue[_date])
     * + farforrent_orders.date_to     = DATE(oc_order_simple_fields.rent_return[_date])
     */
    public function importNew(int $lookbackDays = 2, string $targetTz = 'Europe/Amsterdam'): array
    {
        // ---- MySQL advisory lock ----
        $lockName = 'farforrent_import_new_orders_lock';
        try {
            $gotLock = (int) DB::select("SELECT GET_LOCK(?, 0) AS l", [$lockName])[0]->l === 1;
        } catch (\Throwable $e) {
            Log::warning('[ImportNewOrders] GET_LOCK failed: '.$e->getMessage());
            $gotLock = true;
        }
        if (!$gotLock) return ['ok'=>false,'skipped'=>true,'reason'=>'import already running'];

        try {
            $to   = Carbon::now($targetTz)->startOfDay();
            $from = (clone $to)->subDays(max(0, $lookbackDays - 1));

            // OpenCart connection
            $oc = DB::connection('opencart');

            // Беремо замовлення за днем створення та статусом 2
            $ocOrders = $oc->table('order')
                ->where('order_status_id', 2)
                ->whereBetween(DB::raw('DATE(date_added)'), [$from->toDateString(), $to->toDateString()])
                ->select(
                    'order_id',
                    'customer_id',
                    'firstname','lastname',
                    'telephone','email',
                    'comment','date_added','total'
                )
                ->orderBy('order_id')
                ->get();

            // Колонки у наших таблицях
            $orderCols     = collect(Schema::getColumnListing('farforrent_orders'))->flip();
            $orderItemCols = collect(Schema::getColumnListing('farforrent_order_items'))->flip();
            $hasCustomers  = Schema::hasTable('farforrent_customers');
            $customerCols  = $hasCustomers ? collect(Schema::getColumnListing('farforrent_customers'))->flip() : collect();

            $created = 0; $updated = 0; $itemsTotal = 0;

            foreach ($ocOrders as $row) {
                DB::transaction(function () use ($row, $oc, $targetTz, $orderCols, $orderItemCols, $hasCustomers, $customerCols, &$created, &$updated, &$itemsTotal) {

                    $ocOrderId   = (int) $row->order_id;

                    // --- FIX: customer_id = 0 або NULL → NULL ---
                    $ocCustomerId = ($row->customer_id !== null && (int)$row->customer_id > 0)
                        ? (int)$row->customer_id
                        : null;

                    $fullName    = trim(($row->firstname ?? '').' '.($row->lastname ?? ''));
                    $title       = trim('OC#'.$ocOrderId.' '.($fullName ?: ''));
                    $note        = trim($row->comment ?? '') ?: null;

                    // created_at у нашій TZ
                    $createdAt = $row->date_added
                        ? Carbon::parse($row->date_added, 'UTC')->setTimezone($targetTz)->toDateTimeString()
                        : Carbon::now($targetTz)->toDateTimeString();
                    $nowTs = Carbon::now($targetTz)->toDateTimeString();

                    // ---- ДАТИ ОРЕНДИ з oc_order_simple_fields (без JOIN) ----
                    // читаємо один рядок для order_id; префікс oc_ підставиться автоматично
                    $osf = $oc->table('order_simple_fields')
                        ->where('order_id', $ocOrderId)
                        ->select('rent_issue_date','rent_issue','rent_return_date','rent_return')
                        ->first();

                    // Витягуємо тільки YYYY-MM-DD (якщо поле DATETIME/DATE)
                    $issueDay  = null;
                    $returnDay = null;
                    if ($osf) {
                        $issueDay  = $osf->rent_issue_date  ? substr((string)$osf->rent_issue_date, 0, 10)
                                   : ($osf->rent_issue      ? substr((string)$osf->rent_issue,      0, 10) : null);
                        $returnDay = $osf->rent_return_date ? substr((string)$osf->rent_return_date, 0, 10)
                                   : ($osf->rent_return     ? substr((string)$osf->rent_return,     0, 10) : null);
                    }

                    // --- синхронізація клієнта ---
                    if ($ocCustomerId && $hasCustomers) {
                        $this->syncCustomerByExternalId(
                            $ocCustomerId,
                            $fullName,
                            $row->telephone ?? null,
                            $row->email ?? null,
                            $customerCols,
                            $nowTs
                        );
                    }

                    // --- payload для orders (НЕ ставимо date_from/date_to тут, щоб не занулити при update) ---
                    $basePayload = [
                        'title'       => $title,
                        'note'        => $note,
                        'status'      => 'draft',
                        'created_at'  => $createdAt,
                        'updated_at'  => $nowTs,
                    ];
                    if ($orderCols->has('rent_total'))       $basePayload['rent_total'] = (float)($row->total ?? 0);
                    if ($orderCols->has('deposit_total'))    $basePayload['deposit_total'] = 0;
                    if ($orderCols->has('customer_id'))      $basePayload['customer_id'] = $ocCustomerId;
                    if ($orderCols->has('customer_name'))    $basePayload['customer_name'] = $fullName ?: null;
                    if ($orderCols->has('customer_phone'))   $basePayload['customer_phone'] = $row->telephone ?? null;
                    if ($orderCols->has('customer_email'))   $basePayload['customer_email'] = $row->email ?? null;

                    // чи існує order з таким id
                    $exists = DB::table('farforrent_orders')->where('id', $ocOrderId)->exists();

                    if ($exists) {
                        $updatePayload = $basePayload;
                        if (!$orderCols->has('created_at')) unset($updatePayload['created_at']);

                        // ОНОВЛЮЄМО ДАТИ ЛИШЕ ЯКЩО ВОНИ ПРИЙШЛИ (не затираємо існуючі на NULL)
                        if ($orderCols->has('date_from') && $issueDay  !== null) $updatePayload['date_from'] = $issueDay;
                        if ($orderCols->has('date_to')   && $returnDay !== null) $updatePayload['date_to']   = $returnDay;

                        DB::table('farforrent_orders')
                            ->where('id', $ocOrderId)
                            ->update(array_intersect_key($updatePayload, $orderCols->all()));

                        $updated++;
                    } else {
                        $insertPayload = array_intersect_key($basePayload, $orderCols->all());
                        $insertPayload['id'] = $ocOrderId;

                        // НА ВСТАВЦІ ПИШЕМО ДАТИ ЯК Є (можуть бути NULL — це ок)
                        if ($orderCols->has('date_from')) $insertPayload['date_from'] = $issueDay;
                        if ($orderCols->has('date_to'))   $insertPayload['date_to']   = $returnDay;

                        DB::table('farforrent_orders')->insert($insertPayload);
                        $created++;
                    }

                    // --- позиції ---
                    DB::table('farforrent_order_items')->where('order_id', $ocOrderId)->delete();

                    $products = $oc->table('order_product')
                        ->where('order_id', $ocOrderId)
                        ->select('product_id','model','name','quantity','price','total')
                        ->get();

                    foreach ($products as $p) {
                        $itemPayload = [
                            'order_id'   => $ocOrderId,
                            'created_at' => $nowTs,
                            'updated_at' => $nowTs,
                        ];
                        if ($orderItemCols->has('product_id'))       $itemPayload['product_id'] = $p->product_id ?? null;
                        if ($orderItemCols->has('sku'))              $itemPayload['sku'] = $p->model ?? null;
                        if ($orderItemCols->has('name_snapshot'))    $itemPayload['name_snapshot'] = $p->name ?? null;
                        if ($orderItemCols->has('name'))             $itemPayload['name'] = $p->name ?? null;
                        if ($orderItemCols->has('qty_ordered'))      $itemPayload['qty_ordered'] = (int)($p->quantity ?? 0);
                        if ($orderItemCols->has('quantity'))         $itemPayload['quantity'] = (int)($p->quantity ?? 0);
                        if ($orderItemCols->has('unit_price'))       $itemPayload['unit_price'] = (float)($p->price ?? 0);
                        if ($orderItemCols->has('line_total'))       $itemPayload['line_total'] = (float)($p->total ?? 0);
                        if ($orderItemCols->has('qty_issued'))       $itemPayload['qty_issued'] = 0;
                        if ($orderItemCols->has('qty_returned'))     $itemPayload['qty_returned'] = 0;
                        if ($orderItemCols->has('rent_per_day'))     $itemPayload['rent_per_day'] = 0;
                        if ($orderItemCols->has('deposit_per_item')) $itemPayload['deposit_per_item'] = 0;
                        if ($orderItemCols->has('return_status'))    $itemPayload['return_status'] = 'pending';

                        DB::table('farforrent_order_items')->insert(
                            array_intersect_key($itemPayload, $orderItemCols->all())
                        );
                        $itemsTotal++;
                    }
                });
            }

            return [
                'ok'           => true,
                'orders_found' => count($ocOrders),
                'created'      => $created,
                'updated'      => $updated,
                'items'        => $itemsTotal,
                'from'         => $from->toDateString(),
                'to'           => $to->toDateString(),
            ];

        } catch (\Throwable $e) {
            Log::error('[ImportNewOrders] failed: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return ['ok'=>false,'error'=>$e->getMessage()];
        } finally {
            try { DB::select("SELECT RELEASE_LOCK(?)", [$lockName]); } catch (\Throwable $e) {}
        }
    }

    protected function syncCustomerByExternalId(
        int $ocCustomerId,
        ?string $name,
        ?string $phone,
        ?string $email,
        \Illuminate\Support\Collection $customerCols,
        string $nowTs
    ): void {
        $exists = DB::table('farforrent_customers')->where('id', $ocCustomerId)->exists();

        $payload = ['updated_at' => $nowTs];
        if ($customerCols->has('name'))  $payload['name']  = $name ?: null;
        if ($customerCols->has('phone')) $payload['phone'] = $this->normalizePhone($phone);
        if ($customerCols->has('email')) $payload['email'] = $email ?: null;

        if ($exists) {
            DB::table('farforrent_customers')
                ->where('id', $ocCustomerId)
                ->update(array_intersect_key($payload, $customerCols->all()));
        } else {
            $insert = array_intersect_key($payload, $customerCols->all());
            $insert['id'] = $ocCustomerId;
            if ($customerCols->has('created_at')) $insert['created_at'] = $nowTs;

            DB::table('farforrent_customers')->insert($insert);
        }
    }

    protected function normalizePhone(?string $raw): ?string
    {
        if (!$raw) return null;
        $d = preg_replace('/\D+/', '', $raw);
        if (strlen($d) === 10 && $d[0] === '0') return '38'.$d;
        if (strlen($d) === 12 && substr($d,0,2)==='38') return $d;
        return $d ?: null;
    }
}
