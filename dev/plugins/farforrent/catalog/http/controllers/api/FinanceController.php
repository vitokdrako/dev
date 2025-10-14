<?php namespace Farforrent\Catalog\Http\Controllers\Api;

use Illuminate\Routing\Controller; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
use Farforrent\Catalog\Models\{Order, OrderItem, Payment, Fee, Deposit, CalendarEvent, ReturnSheet};

class FinanceController extends Controller
{
  public function ledger(Request $r){
    $from = $r->query('from'); $to = $r->query('to'); $type=$r->query('type');
    $q = Payment::query();
    if($from) $q->whereDate('paid_at','>=',$from); if($to) $q->whereDate('paid_at','<=',$to);
    if($type) $q->where('type',$type);
    $rows = $q->orderByDesc('paid_at')->get()->map(function($p){
      return [
        'id'=>$p->id,'happened_at'=>$p->paid_at,'type'=>$p->type,
        'customer_name'=>optional($p->order)->customer_name,
        'order_id'=>$p->order_id,'amount'=>$p->amount,'manager_name'=>null,
      ];
    });
    return response()->json($rows);
  }
  public function summary(Request $r){
    $from=$r->query('from'); $to=$r->query('to');
    $q = Payment::query(); if($from) $q->whereDate('paid_at','>=',$from); if($to) $q->whereDate('paid_at','<=',$to);
    $sum = [
      'hold'    => (clone $q)->where('type','deposit_hold')->sum('amount'),
      'refund'  => (clone $q)->where('type','deposit_refund')->sum('amount'),
      'applied' => Fee::whereBetween(DB::raw('DATE(created_at)'), [$from,$to])->whereIn('fee_type',['loss_fee','repair_fee','cleaning_fee','late_fee'])->sum('amount'),
      'revenue' => (clone $q)->whereIn('type',['rent_payment','invoice_payment','prepayment'])->sum('amount'),
    ];
    return response()->json($sum);
  }
}