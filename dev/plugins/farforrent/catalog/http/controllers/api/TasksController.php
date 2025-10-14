<?php namespace Farforrent\Catalog\Http\Controllers\Api;

use Illuminate\Routing\Controller; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
use Farforrent\Catalog\Models\{Order, OrderItem, Payment, Fee, Deposit, CalendarEvent, ReturnSheet};

class TasksController extends Controller
{
  public function index(Request $r){
    $assignee=$r->query('assignee'); $status=$r->query('status');
    $q=Task::query(); if($assignee) $q->where('assignee',$assignee); if($status) $q->where('status',$status);
    return response()->json($q->orderByDesc('id')->get());
  }
  public function store(Request $r){ /* create/update task */ }
  public function update(Request $r, $id){ /* update */ }
}