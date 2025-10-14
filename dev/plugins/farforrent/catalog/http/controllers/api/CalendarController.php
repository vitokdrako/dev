<?php

namespace Farforrent\Catalog\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    /**
     * FullCalendar -> GET /api/manager/calendar?start=YYYY-MM-DD&end=YYYY-MM-DD
     */
   public function calendar(Request $r)
{
    $start = (string) $r->query('start');
    $end   = (string) $r->query('end');
    if (!$start || !$end) return response()->json([], 200);

    // ---- статусна логіка ----
    // календарні статуси: Оброблено (19) + Заказ видан (24)
    $calendarStatusIds = [19, 24];

    // за замовчуванням показуємо календарні (19,24)
    $statusParam   = (string) $r->query('status', 'calendar'); // calendar | processed | issued | all
    $statusIdParam = $r->query('status_id');                   // ?status_id=2

    if ($statusIdParam !== null && $statusIdParam !== '') {
        $statusFilterSql = 'o.order_status_id = :status_id';
        $statusBindings  = ['status_id' => (int)$statusIdParam];
    } else {
        if ($statusParam === 'all') {
            $statusFilterSql = '1=1';
            $statusBindings  = [];
        } elseif ($statusParam === 'processed') {
            $statusFilterSql = 'o.order_status_id = :status_id';
            $statusBindings  = ['status_id' => 19];
        } elseif ($statusParam === 'issued') {
            $statusFilterSql = 'o.order_status_id = :status_id';
            $statusBindings  = ['status_id' => 24];
        } else { // 'calendar' -> 19 і 24
            // Можна через IN з константами — простіше і зрозуміліше
            $statusFilterSql = 'o.order_status_id IN (19, 24)';
            $statusBindings  = [];
        }
    }

    // ---- нормалізація дат ----
    $issueExpr = "COALESCE(
        osf.rent_issue_date,
        STR_TO_DATE(osf.rent_issue,  '%Y-%m-%d'),
        STR_TO_DATE(osf.rent_issue,  '%d.%m.%Y'),
        STR_TO_DATE(osf.rent_issue,  '%m/%d/%Y')
    )";
    $returnExpr = "COALESCE(
        osf.rent_return_date,
        STR_TO_DATE(osf.rent_return, '%Y-%m-%d'),
        STR_TO_DATE(osf.rent_return, '%d.%m.%Y'),
        STR_TO_DATE(osf.rent_return, '%m/%d/%Y')
    )";

    // ---- SQL ----
    $sql = "
      SELECT
        o.order_id,
        MIN(CONCAT(o.firstname,' ',o.lastname)) AS customer,
        MIN(DATE($issueExpr))  AS issue_date,
        MIN(DATE($returnExpr)) AS return_date,
        GROUP_CONCAT(CONCAT(op.model, '×', op.quantity) SEPARATOR ', ') AS items,
        MIN(o.order_status_id) AS status_id
      FROM oc_order o
      LEFT JOIN oc_order_simple_fields osf ON osf.order_id = o.order_id
      LEFT JOIN oc_order_product       op  ON op.order_id  = o.order_id
      WHERE
        $statusFilterSql
        AND (
          ($issueExpr IS NOT NULL AND DATE($issueExpr) < :end)
        )
        AND (
          $returnExpr IS NULL OR DATE($returnExpr) >= :start
        )
      GROUP BY o.order_id
      ORDER BY MIN(DATE($issueExpr))
    ";

    $bindings = array_merge($statusBindings, ['start' => $start, 'end' => $end]);
    $rows = \DB::connection('opencart')->select($sql, $bindings);

    // ---- події ----
    $events = [];
    foreach ($rows as $row) {
        $orderId  = (int) $row->order_id;
        $title = '#' . $orderId . ' — ' . trim(($row->customer ?? '') . ' — ' . ($row->items ?? ''));

        // “календарні” статуси лишаються кольоровими
        $isCalendarStatus = in_array((int)$row->status_id, $calendarStatusIds, true);

        // видача (зелений / сірий)
        if (!empty($row->issue_date) && (int)$row->status_id !== 24) {
    $issueEnd = (new \DateTime($row->issue_date))->modify('+1 day')->format('Y-m-d');
    $events[] = [
        'id'              => $orderId . '-issue',
        'title'           => $title,
        'start'           => $row->issue_date,
        'end'             => $issueEnd,
        'allDay'          => true,
        'backgroundColor' => $isCalendarStatus ? '#16a34a' : '#9ca3af',
        'borderColor'     => $isCalendarStatus ? '#16a34a' : '#9ca3af',
        'url'             => "https://farforrent.com.ua/admin/index.php?route=sale/order/info&order_id={$orderId}",
        'extendedProps'   => [
            'kind'      => 'issue',
            'issue'     => $row->issue_date,
            'return'    => $row->return_date ?? null,
            'status_id' => (int)$row->status_id,
        ],
    ];
}

        // повернення (синій / сірий)
        if (!empty($row->return_date)) {
            $returnEnd = (new \DateTime($row->return_date))->modify('+1 day')->format('Y-m-d');
            $events[] = [
                'id'              => $orderId . '-return',
                'title'           => $title,
                'start'           => $row->return_date,
                'end'             => $returnEnd,
                'allDay'          => true,
                'backgroundColor' => $isCalendarStatus ? '#2563eb' : '#9ca3af',
                'borderColor'     => $isCalendarStatus ? '#2563eb' : '#9ca3af',
                'url'             => "https://farforrent.com.ua/admin/index.php?route=sale/order/info&order_id={$orderId}",
                'extendedProps'   => [
                    'kind'      => 'return',
                    'issue'     => $row->issue_date ?? null,
                    'return'    => $row->return_date,
                    'status_id' => (int)$row->status_id,
                ],
            ];
        }
    }

    return response()->json($events);
}
}