### Настройка и использование:
1. composer install
2. В файле .env настроить подключение к БД
3. Получение cvs отчета:
    * Через браузер:
        * Url: **`/export`** (Создание cvs отчета через отношения моделей)
        * Url: **`/export-only-valid`** (Создание cvs отчета для проверки записей с типом: Order)
        * Url: **`/export-foreach`** (Создание cvs отчета для визуального сравнения с отчетом из /export)
    * Через консоль:
        * **`php artisan export:run`** Файл сохряняется в папке **public**.

### Структура:
##### Models:
1. app/Data.php - таблица "table_with_data"
2. app/Customer.php - таблица "customers"
3. app/Order.php - таблица "this_year_orders"

##### Relationships:
1. Customer -> Order - Один ко Многим

##### Controllers:
1. app/Http/Controllers/ReportController.php - Отвечает за создание отчета через url браузера

##### Commands:
1. app/Console/Commands/Export.php - Отвечает за создание отчета через консоль

##### Exports:
1. app/Exports/OrdersExport.php - Класс отвечающий за формирование cvs отчета

##### Используемые материалы:
1. https://laravel.com - Фреймворк Laravel
2. https://laravel.com/docs/6.x/eloquent-relationships - Модели и их отношения.
3. https://laravel.com/docs/6.x/collections - Коллекции и их методы.
4. https://docs.laravel-excel.com/3.1/getting-started - Расширение для Export/Import файлов.
5. https://carbon.nesbot.com/docs - Работа с датой. 