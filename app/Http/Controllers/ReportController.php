<?php

namespace App\Http\Controllers;

use App\Data;
use App\Order;
use App\Customer;
use App\Exports\OrdersExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\Continue_;

class ReportController extends Controller
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
    public function exportRelate()
    {

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

        // Отдаем файл для его закгрузки пользователем.
        return Excel::download($export, 'orders-report.csv');
    }

    /**
     * Создание проверочного отчета, только с подходящими под условие записями.
     * В отчет попадают только записи с типом: Order
     *
     * @return void
     */
    public function exportOnlyValid()
    {

        $datas = Data::cursor();

        $results = [];

        foreach ($datas as $data) {

            $orders = DB::table('this_year_orders')
                ->join('customers', 'this_year_orders.customer_id', '=', 'customers.id')
                ->join('table_with_data', 'customers.email', '=', 'table_with_data.email')
                ->select('table_with_data.email', 'table_with_data.date_added', 'table_with_data.value', 'this_year_orders.site_id', 'this_year_orders.created_date', 'this_year_orders.status',)
                ->where('customers.email', $data->email)
                ->whereBetween('this_year_orders.created_date', [$data->date_added, $data->date_three])
                ->get();

            foreach ($orders as $order) {
                $order->status = $this->status[$order->status];
                $order->type = 'Order';
                $results[] = $order;
            }
        }

        $export = new OrdersExport($results);

        return Excel::download($export, 'orders-only-valid.csv');
    }

    /**
     * Создание проверочного отчета, для сравнения с отчетом созданым по url: /export
     *
     * @return void
     */
    public function exportForeach()
    {

        $datas = Data::cursor();

        $results = [];
        $results_not_customers = [];
        $results_not_orders = [];
        $results_valid = [];
        $results_fail = [];

        foreach ($datas as $data) {

            $customers = Customer::where('email', $data->email)->get();

            if ($customers->count() > 0) {

                foreach ($customers as $customer) {

                    $orders = Order::where('customer_id', $customer->id)->get();

                    if ($orders->count() > 0) {

                        $valid_orders = $orders->whereBetween('created_date', [$data->date_added, $data->date_three])->values()->all();
                        if (count($valid_orders) > 0) {
                            foreach ($valid_orders as $valid_order) {
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

                        $fail_orders = $orders->whereNotBetween('created_date', [$data->date_added, $data->date_three])->values()->all();
                        if (count($fail_orders) > 0) {
                            $fail_orders = collect($fail_orders)->unique('email')->values()->all();
                            foreach ($fail_orders as $fail_order) {
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
                    } else {
                        $results_not_orders[] = [
                            'email' => $data->email,
                            'date' => $data->date_added->toDateTimeString(),
                            'value' => $data->value,
                            'site_id' => '',
                            'order_creared' => '',
                            'order_status' => '',
                            'type' => 'Not Order'
                        ];
                    }
                }
            } else {
                $results_not_customers[] = [
                    'email' => $data->email,
                    'date' => $data->date_added->toDateTimeString(),
                    'value' => $data->value,
                    'site_id' => '',
                    'order_creared' => '',
                    'order_status' => '',
                    'type' => 'Not Custemer'
                ];
            }
        }

        foreach ($results_valid as $valid) {
            foreach ($results_fail as $key => $fail) {
                if ($valid['email'] == $fail['email']) {
                    unset($results_fail[$key]);
                }
            }
        }

        $results = collect($results);

        $results_not_orders = collect($results_not_orders)->unique('email');

        $results_not_customers = collect($results_not_customers)->unique('email');

        $results = $results->merge($results_valid);

        $results = $results->merge($results_fail);

        $results = $results->merge($results_not_orders);

        $results = $results->merge($results_not_customers);

        $export = new OrdersExport($results->all());

        return Excel::download($export, 'orders-foreach.csv');
    }
}
