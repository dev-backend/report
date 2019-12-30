<?php

namespace App\Console\Commands;

use App\Data;
use App\Customer;
use App\Exports\OrdersExport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class Export extends Command
{

    private $status = [
        '0' => "New",
        '1' => "In Progress",
        '2' => "Completed",
        '3' => "Waiting",
        '4' => "Processing Pending",
        '5' => "Cancelled",
        '6' => "ReShipment",
        '7' => "Refunded",
        '8' => "Chargeback",
        '9' => "Fraud",
        '10' => "Declined",
        '11' => "Test Order",
        '12' => "Unapproved",
        '13' => "Verify In Progress",
        '14' => "Lead",
        '15' => "Black List",
        '16' => "Double Order",
        '17' => "Over Amount",
        '18' => "Wire Payment Confirmation",
        '19' => "To Refund",
        '20' => "Declined 3D",
        '21' => "WireWait",
        '22' => "WireWait Processing",
        '23' => "Problematic Refund",
        '24' => "To Test",
        '25' => "CC Check",
        '26' => "CC Risk",
        '27' => "Check and Verify",
        '28' => "Waiting Upgrade",
        '29' => "Shipping Conflict",
        '35' => "Wait for Wu",
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export report orders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Создание отчета.
     * Условия формирования отчета:
     * Берем мыло из таблицы "table_with_data(модель Data)",
     * по нему находим customer из таблицы "customers(модель Customer)",
     * берем id найденого customer и по нему ищем orders в таблице "this_year_orders(модель Order)".
     * Найденые orders должны находиться в промежутке дат: Data.date_added(дата отправки письма) и Data.date_three(плюс 3 дня от отправки письма).
     * Есть 4 типа данных (type):
     * Order: все найденые orders что подходят условию;
     * Not Between: все найденые orders, которые не подошли к условию. Так же эти записи обьединяются в 1 если их несколько
     * и если у customer есть уже тип Order то вообще не выводим этот тип;
     * Not Order: если у customer отсутсвуют orders
     * Not Customer: если у записи из Data не находит по email customer
     *
     * @return void
     */
    public function handle()
    {
        try {

            $start = microtime(true);

            $start = microtime(true);

            // Получаем все записи
            $datas = Data::cursor();

            // Регистрируем переменные
            $results = [];
            $results_not_customers = [];
            $results_not_orders = [];
            $results_valid = [];
            $results_fail = [];

            // Начинаем переберать список
            foreach ($datas as $data) {

                // Находим пользователей по email
                $customers = Customer::with('orders')->where('email', $data->email)->get();

                // Делаем проверку на найденых customres, если нет таковых, то делаем запись с типом: Not Customer и пропускаем итерацию цикла
                if ($customers->count() == 0) {
                    $results_not_customers[] = [
                        'email' => $data->email,
                        'date' => $data->date_added->toDateTimeString(),
                        'value' => $data->value,
                        'site_id' => '',
                        'order_creared' => '',
                        'order_status' => '',
                        'type' => 'Not Custemer'
                    ];
                    continue;
                }

                // Начинаем переберать список найденых customers
                foreach ($customers as $customer) {

                    // Делаем проверку на найденые orders у customre, если нет таковых, то делаем запись с типом: Not Order и пропускаем итерацию цикла
                    if ($customer->orders->count() == 0) {
                        $results_not_orders[] = [
                            'email' => $data->email,
                            'date' => $data->date_added->toDateTimeString(),
                            'value' => $data->value,
                            'site_id' => '',
                            'order_creared' => '',
                            'order_status' => '',
                            'type' => 'Not Order'
                        ];
                        continue;
                    }

                    // Получаем все orders, которые подошли по условию
                    $valid_orders = collect($customer->orders)->whereBetween('created_date', [$data->date_added, $data->date_three])->all();
                    // Проверка на существование
                    if ($valid_orders) {
                        // Начинаем переберать записи
                        foreach ($valid_orders as $valid_order) {
                            // Записываем запись с типом: Order 
                            $results_valid[] = [
                                'email' => $data->email,
                                'date' => $data->date_added->toDateTimeString(),
                                'value' => $data->value,
                                'site_id' => $valid_order->site_id,
                                'order_creared' => $valid_order->created_date->toDateTimeString(),
                                'order_status' => $this->status[$valid_order->status],
                                'type' => 'Order',
                            ];
                        }
                    }

                    // Получаем все orders, которые не подошли по условию
                    $fail_orders = collect($customer->orders)->whereNotBetween('created_date', [$data->date_added, $data->date_three])->all();
                    // Проверка на существование
                    if ($fail_orders) {
                        // Фильтреум на уникальность по мылу
                        $fail_orders = collect($fail_orders)->unique('email')->values()->all();
                        // Начинаем переберать записи
                        foreach ($fail_orders as $fail_order) {
                            // Записываем запись с типом: Not Between 
                            $results_fail[] = [
                                'email' => $data->email,
                                'date' => $data->date_added->toDateTimeString(),
                                'value' => $data->value,
                                'site_id' => '',
                                'order_creared' => $fail_order->created_date->toDateTimeString(),
                                'order_status' => '',
                                'type' => 'Not Between'
                            ];
                        }
                    }
                }
            }

            $end = round(microtime(true) - $start, 4);

            // Перебераем массивы с типом: Order и Not Between, если email существует в обоих массивах, то оставляем только запись в массиве с типом: Order 
            foreach ($results_valid as $valid) {
                foreach ($results_fail as $key => $fail) {
                    if ($valid['email'] == $fail['email']) {
                        unset($results_fail[$key]);
                    }
                }
            }

            // Преобразуем массив в коллекцию
            $results = collect($results);

            // Получаем уникальные записи с типом: Not Order по email
            $results_not_orders = collect($results_not_orders)->unique('email');
            // Получаем уникальные записи с типом: Not Customer по email
            $results_not_customers = collect($results_not_customers)->unique('email');

            // Добавляем в коллекцию результаты с типом: Order
            $results = $results->merge($results_valid);
            // Добавляем в коллекцию результаты с типом: Not Between
            $results = $results->merge($results_fail);

            // Добавляем в коллекцию результаты с типом: Not Order
            $results = $results->merge($results_not_orders);
            // Добавляем в коллекцию результаты с типом: Not Customer
            $results = $results->merge($results_not_customers);

            // Формируем cvs отчет
            $export = new OrdersExport($results->all());

            // Записываем файл в папку public
            Excel::store(new OrdersExport($results->all()), 'orders-ralete.csv', 'export');

            $end = round(microtime(true) - $start, 4);

            echo 'Export done! Time: ' . $end . PHP_EOL;

            return true;
        } catch (\Throwable $th) {
            echo "Error: " . $th->getMessage() . 'Time: ' . $end . PHP_EOL;
            return false;
        }
    }
}
