<?php namespace Farforrent\Catalog\Http\Controllers\Api;

use Illuminate\Routing\Controller; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
use Farforrent\Catalog\Models\{Order, OrderItem, Payment, Fee, Deposit, CalendarEvent, ReturnSheet};

class OrdersController extends Controller
{
  // Картки дня: 19 (issue/return по rent_*), 2 (new по created_local_day з OC history — вже реалізовано у тебе)
  public function today(\Illuminate\Http\Request $r)
{
    $tz   = $r->query('tz', 'Europe/Amsterdam');
    $date = $r->query('date') ?: now($tz)->toDateString();

    // межі доби у UTC (created_at зберігається як правило в UTC)
    $start = \Carbon\Carbon::parse($date, $tz)->startOfDay()->timezone('UTC');
    $end   = \Carbon\Carbon::parse($date, $tz)->endOfDay()->timezone('UTC');

    /** @var \Illuminate\Database\Eloquent\Builder $q */
    $q = \Farforrent\Catalog\Models\Order::query()
        ->select([
            'id','title','customer_name','customer_phone','note',
            'rent_total','deposit_total','date_from','date_to','created_at'
        ]);

    // Нові (за created_at у межах доби)
    $new = (clone $q)
        ->whereBetween('created_at', [$start, $end])
        ->orderByDesc('id')
        ->get();

    // Видача — якщо у цей день date_from
    $issue = (clone $q)
        ->whereDate('date_from', $date)
        ->orderByDesc('id')
        ->get();

    // Повернення — якщо у цей день date_to
    $return = (clone $q)
        ->whereDate('date_to', $date)
        ->orderByDesc('id')
        ->get();

    $map = function($o, string $kind) {
        // Спробуємо показувати старий OC-номер, якщо є у title "OC#XXXX ..."
        $displayId = $o->id;
        if (is_string($o->title) && preg_match('/OC#(\d+)/u', $o->title, $m)) {
            $displayId = (int)$m[1];
        }

        return [
            'id'      => $displayId,
            'kind'    => $kind,                              // 'new' | 'issue' | 'return'
            'name'    => (string)$o->customer_name,
            'phone'   => (string)$o->customer_phone,
            'note'    => (string)($o->note ?? ''),
            'sum'     => round((float)($o->rent_total ?? 0), 2),
            'deposit' => round((float)($o->deposit_total ?? 0), 2),
        ];
    };

    $out = array_merge(
        $new->map(fn($o) => $map($o, 'new'))->all(),
        $issue->map(fn($o) => $map($o, 'issue'))->all(),
        $return->map(fn($o) => $map($o, 'return'))->all(),
    );

    // Чистий JSON без зайвих символів
    return response()->json($out, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}



  public function show($id)
{
    $o = \Farforrent\Catalog\Models\Order::query()
        ->select([
            'id','title','customer_name','customer_phone','customer_email',
            'date_from','date_to','note','status','rent_total','deposit_total','created_at'
        ])->findOrFail($id);

    // Деталі позицій (під різні назви колонок)
    $items = [];
    if (\Illuminate\Support\Facades\Schema::hasTable('farforrent_order_items')) {
        $rows = \Illuminate\Support\Facades\DB::table('farforrent_order_items')
            ->where('order_id', $o->id)
            ->get();

        foreach ($rows as $r) {
            $qty = (int)($r->qty ?? $r->quantity ?? 1);
            $rentUnit = (float)($r->rent_price ?? $r->rent_per_day ?? $r->rent ?? 0);
            $depUnit  = (float)($r->deposit_per_item ?? $r->deposit ?? 0);
            $items[] = [
                'sku'               => (string)($r->sku ?? $r->model ?? ''),
                'name'              => (string)($r->name ?? ''),
                'qty'               => $qty,
                'rent_price'        => $rentUnit,
                'deposit_per_item'  => $depUnit,
                'subtotal'          => round($rentUnit * $qty, 2),
            ];
        }
    }

    // Можлива бізнес-логіка: сума до оплати
    $amountDue = null;
    if (isset($o->rent_total) || isset($o->deposit_total)) {
        $amountDue = (float)($o->rent_total ?? 0) + (float)($o->deposit_total ?? 0);
    }

    return response()->json([
        'id'             => $o->id,
        'title'          => $o->title,
        'customer_name'  => $o->customer_name,
        'customer_phone' => $o->customer_phone,
        'customer_email' => $o->customer_email,
        'date_from'      => optional($o->date_from)->toDateString() ?? (string)$o->date_from,
        'date_to'        => optional($o->date_to)->toDateString() ?? (string)$o->date_to,
        'note'           => $o->note,
        'status'         => $o->status,
        'rent_total'     => (float)($o->rent_total ?? 0),
        'deposit_total'  => (float)($o->deposit_total ?? 0),
        'amount_due'     => $amountDue,
        'items'          => $items,
    ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

  public function store(Request $r){
    // Приймаємо упакований запит (з сайту або з OC-синха) і створюємо «staging» ордер
    $data = $r->validate([
      'oc_order_id'=>'nullable|integer', 'source'=>'nullable|string',
      'created_local_day'=>'required|date',
      'customer_name'=>'nullable|string','customer_phone'=>'nullable|string','customer_email'=>'nullable|email',
      'comment_client'=>'nullable|string', 'items'=>'array|min:1',
      'items.*.product_id'=>'nullable|integer', 'items.*.sku'=>'nullable|string',
      'items.*.name_snapshot'=>'nullable|string','items.*.photo_url'=>'nullable|string',
      'items.*.qty_ordered'=>'required|integer|min:1','items.*.rent_per_day'=>'nullable|numeric','items.*.deposit_per_item'=>'nullable|numeric',
    ]);

    $o = Order::updateOrCreate(
      ['oc_order_id'=>$data['oc_order_id'] ?? null],
      array_merge($data, ['status'=>'new'])
    );

    // upsert items (простий варіант: видалити і вставити)
    $o->items()->delete();
    foreach($data['items'] as $it){ $o->items()->create($it); }

    // календарні події (за потреби)
    if(!empty($data['planned_issue_day'])){
      CalendarEvent::updateOrCreate(['order_id'=>$o->id,'kind'=>'issue'],[
        'start_day'=>$data['planned_issue_day'], 'title'=>$o->customer_name, 'phone'=>$o->customer_phone
      ]);
    }
    if(!empty($data['planned_return_day'])){
      CalendarEvent::updateOrCreate(['order_id'=>$o->id,'kind'=>'return'],[
        'start_day'=>$data['planned_return_day'], 'title'=>$o->customer_name, 'phone'=>$o->customer_phone
      ]);
    }

    return response()->json(['id'=>$o->id]);
  }

  public function update(Request $r, $id){
    $o = Order::findOrFail($id);
    if($o->is_locked_final) return response()->json(['error'=>'locked'], 409);

    $payload = $r->all();
    $o->fill(collect($payload)->only([
      'comment_manager','is_late','status'
    ])->toArray());
    $o->save();

    // опціонально оновлення item’ів/штрафів тут
    return response()->json($o->fresh(['items']));
  }

  public function issue($id){
    $o = Order::findOrFail($id);
    // логіка видачі: інвентарні рухи, qty_issued, подія календаря etc.
    return response()->json(['ok'=>true]);
  }

  public function settle($id){
    $o = Order::with('items','fees','payments')->findOrFail($id);
    // підрахунок totals / депозиту; запис у ReturnSheet
    return response()->json($o);
  }

  public function close($id){
    $o = Order::findOrFail($id);
    $o->status = 'closed'; $o->is_locked_final = true; $o->save();
    return response()->json(['ok'=>true]);
  }

  public function quickStatus(Request $r, $id){
    // перехід у старій адмінці: приймаємо status_id=24/13 — лог чи проксі
    return response()->json(['ok'=>true]);
  }
}