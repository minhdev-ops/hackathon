<?php

/*
|--------------------------------------------------------------------------
| PHPUnit bootstrap — Issue 11 (hạ tầng test)
|--------------------------------------------------------------------------
|
| PHP build trên máy dev/CI chỉ có pdo_mysql (không có pdo_sqlite), nên
| phpunit.xml trỏ sang MySQL test DB riêng `trustgate_test` thay cho
| sqlite :memory:. Các giá trị khác (DB_USERNAME/DB_PASSWORD/APP_KEY)
| lấy từ .env khi app khởi động; phpunit.xml không bị .env ghi đè.
|
| Bootstrap này nạp .env (nếu có) và tạo sẵn database test khi thiếu,
| fail-fast với thông báo rõ thay vì để hàng chục test chết vì driver.
|
*/

require __DIR__.'/../vendor/autoload.php';

if (extension_loaded('pdo_sqlite') || ! extension_loaded('pdo_mysql')) {
    // Máy có sqlite hoặc không có MySQL → để phpunit.xml/env quyết định.
    return;
}

$base = dirname(__DIR__);

if (is_file($base.'/.env')) {
    Dotenv\Dotenv::create(Illuminate\Support\Env::getRepository(), $base)->safeLoad();
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_DATABASE') ?: 'trustgate_test';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port}",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
    );

    $pdo->exec(
        'CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '', $database)
        .'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Không kết nối được MySQL để chạy test: {$exception->getMessage()}\n"
        ."Tạo DB thủ công: CREATE DATABASE trustgate_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
        ."hoặc sửa DB_HOST/DB_USERNAME/DB_PASSWORD trong .env\n",
    );

    exit(1);
}
