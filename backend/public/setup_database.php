<?php
// backend/setup_database.php
// Створення таблиць та початкових даних

require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "Підключення до бази даних успішне!\n\n";

    // Читаємо та виконуємо SQL файл
    $sql = file_get_contents(__DIR__ . '/database/migrations/001_create_tables.sql');

    // Розділяємо на окремі запити
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !str_starts_with($stmt, '--');
        }
    );

    echo "Виконання " . count($statements) . " SQL запитів...\n";

    foreach ($statements as $i => $statement) {
        if (trim($statement)) {
            echo ($i + 1) . ". ";
            try {
                $pdo->exec($statement);

                // Визначаємо тип операції
                if (str_contains(strtoupper($statement), 'CREATE TABLE')) {
                    preg_match('/CREATE TABLE.*?(\w+)/i', $statement, $matches);
                    $tableName = $matches[1] ?? 'unknown';
                    echo "Створено таблицю: {$tableName}\n";
                } elseif (str_contains(strtoupper($statement), 'INSERT INTO')) {
                    preg_match('/INSERT INTO\s+(\w+)/i', $statement, $matches);
                    $tableName = $matches[1] ?? 'unknown';
                    echo "Додано дані в таблицю: {$tableName}\n";
                } else {
                    echo "Виконано SQL команду\n";
                }

            } catch (PDOException $e) {
                echo "ПОМИЛКА: " . $e->getMessage() . "\n";

                // Продовжуємо виконання інших запитів
                continue;
            }
        }
    }

    // Перевіряємо створені таблиці
    echo "\nПеревірка створених таблиць:\n";
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $countResult = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
        $count = $countResult->fetch()['count'];
        echo "✅ {$table}: {$count} записів\n";
    }

    echo "\n🎉 База даних налаштована успішно!\n";

} catch (PDOException $e) {
    echo "❌ Помилка підключення до бази даних: " . $e->getMessage() . "\n";
    echo "\nПеревірте:\n";
    echo "1. Чи запущений MySQL контейнер: docker-compose ps\n";
    echo "2. Чи правильні дані в .env файлі\n";
    echo "3. Чи створена база даних: docker-compose exec mysql mysql -u root -prootpassword -e 'SHOW DATABASES;'\n";
}