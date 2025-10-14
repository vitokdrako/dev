<?php namespace Farforrent\Catalog\Console;

use Illuminate\Console\Command;
use Farforrent\Catalog\Classes\OrdersImportService;

class ImportNewOrders extends Command
{
    protected $name = 'farforrent:import-new-orders';
    protected $description = 'Імпорт status_id=2 (нові/в обробці) з OpenCart у наші таблиці. Ідемпотентно.';

    public function handle()
    {
        $lookback = (int) ($this->option('lookback') ?? 2);
        $tz       = (string) ($this->option('tz') ?? 'Europe/Amsterdam');

        $svc = app(OrdersImportService::class);
        $res = $svc->importNew($lookback, $tz);

        if (!empty($res['ok'])) {
            $this->info(sprintf(
                'OK: found=%d, created=%d, updated=%d, items=%d',
                $res['orders_found'] ?? 0, $res['created'] ?? 0, $res['updated'] ?? 0, $res['items'] ?? 0
            ));
            return 0;
        }
        $this->error('Fail: '.($res['error'] ?? 'unknown'));
        return 1;
    }

    protected function getOptions()
    {
        return [
            ['lookback', null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'К-сть днів назад (за замовчуванням 2)'],
            ['tz',       null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Timezone, напр. Europe/Amsterdam'],
        ];
    }
}
