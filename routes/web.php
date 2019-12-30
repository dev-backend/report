<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/export', 'ReportController@exportRelate')->name('export');
Route::get('/export-only-valid', 'ReportController@exportOnlyValid')->name('export-only-valid');
Route::get('/export-foreach', 'ReportController@exportForeach')->name('export-foreach');

