<?php namespace Farforrent\Catalog\Http\Controllers\Api;

use Illuminate\Routing\Controller; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
use Farforrent\Catalog\Models\{Order, OrderItem, Payment, Fee, Deposit, CalendarEvent, ReturnSheet};

class DamageController extends Controller
{
  public function index(Request $r){
    $from=$r->query('from'); $to=$r->query('to'); $filter=$r->query('filter');
    $q = Damage::query();
    if($from) $q->whereDate('happened_at','>=',$from); if($to) $q->whereDate('happened_at','<=',$to);
    if($filter) $q->where('type',$filter);
    $rows = $q->orderByDesc('happened_at')->get()->map(function($d){
      return [
        'id'=>$d->id,'happened_at'=>$d->happened_at,'order_id'=>$d->order_id,
        'customer_name'=>optional($d->order)->customer_name,'type'=>$d->type,
        'qty'=>$d->qty,'amount'=>$d->amount,'status'=>$d->status,'manager_name'=>null
      ];
    });
    // підсумки
    $sum = ['clean'=>Damage::where('type','dirty')->sum('amount'),'repair'=>Damage::where('type','repairable')->sum('amount'),'loss'=>Damage::whereIn('type',['lost','broken_irrecoverable'])->sum('amount'),'late'=>Damage::where('type','late')->sum('amount')];
    return response()->json(['rows'=>$rows,'sum'=>$sum]);
  }
  public function store(Request $r){ /* create damage row */ }
}