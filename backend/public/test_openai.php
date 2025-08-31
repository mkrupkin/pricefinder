<?php
// backend/test_openai.php
// Простий тест OpenAI API для PriceFinder

require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['OPENAI_API_KEY'] ?? '';

if (empty($apiKey) || $apiKey === 'sk-your-openai-api-key-here') {
    die("ПОМИЛКА: Встановіть правильний OPENAI_API_KEY в .env файлі\n");
}

$client = new \GuzzleHttp\Client([
    'base_uri' => 'https://api.openai.com/v1/',
    'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json'
    ],
    'timeout' => 60
]);

echo "Тестування OpenAI API для PriceFinder...\n\n";

// Тест 1: Простий пошук товару
echo "Тест 1: Пошук iPhone в українських магазинах\n";
echo str_repeat("-", 50) . "\n";

$startTime = microtime(true);

$prompt = "Знайдіть де можна купити iPhone 15 Pro в Києві. 
Поверніть список РЕАЛЬНИХ українських магазинів з приблизними цінами в гривнях.

Формат відповіді:
Магазин: назва
Ціна: сума в UAH  
Тип: онлайн/офлайн
Сайт: адреса

Приклад:
Магазин: Rozetka
Ціна: 55000 UAH
Тип: онлайн
Сайт: rozetka.com.ua";

$data = [
    'model' => 'gpt-4',
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => 800,
    'temperature' => 0.3
];

try {
    $response = $client->post('chat/completions', ['json' => $data]);
    $result = json_decode($response->getBody(), true);

    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000);

    $content = $result['choices'][0]['message']['content'];
    $tokensUsed = $result['usage']['total_tokens'];
    $cost = ($tokensUsed / 1000) * 0.03;

    echo "Час виконання: {$duration}ms\n";
    echo "Токени: {$tokensUsed}\n";
    echo "Вартість: $" . number_format($cost, 4) . "\n\n";

    echo "ВІДПОВІДЬ OpenAI:\n";
    echo str_repeat("-", 30) . "\n";
    echo $content . "\n\n";

    // Аналіз якості відповіді
    $ukrainianStores = ['rozetka', 'eldorado', 'comfy', 'foxtrot', 'moyo', 'citrus'];
    $foundStores = 0;

    foreach ($ukrainianStores as $store) {
        if (stripos($content, $store) !== false) {
            $foundStores++;
        }
    }

    echo "АНАЛІЗ:\n";
    echo "Знайдено відомих українських магазинів: {$foundStores}\n";

    if ($foundStores >= 2) {
        echo "РЕЗУЛЬТАТ: OpenAI знає українські магазини - ДОБРЕ\n";
    } else {
        echo "РЕЗУЛЬТАТ: OpenAI погано знає український ринок - ПРОБЛЕМА\n";
    }

    if ($duration < 10000) {
        echo "Швидкість: ПРИЙНЯТНА\n";
    } else {
        echo "Швидкість: ПОВІЛЬНО\n";
    }

    if ($cost < 0.1) {
        echo "Вартість: ПРИЙНЯТНА\n";
    } else {
        echo "Вартість: ДОРОГО\n";
    }

} catch (Exception $e) {
    echo "ПОМИЛКА: " . $e->getMessage() . "\n";

    if (strpos($e->getMessage(), 'Unauthorized') !== false) {
        echo "Перевірте API ключ\n";
    } elseif (strpos($e->getMessage(), 'insufficient_quota') !== false) {
        echo "Недостатньо коштів на OpenAI рахунку\n";
    }
}

// Тест 2: Швидкий тест вартості
echo "\nТест 2: Розрахунок вартості для різних планів\n";
echo str_repeat("-", 50) . "\n";

$plans = [
    'Безкоштовний' => 2,
    'Базовий' => 15,
    'Преміум' => 100
];

foreach ($plans as $planName => $searches) {
    $dailyCost = $searches * 0.05; // $0.05 за пошук
    $monthlyCost = $dailyCost * 30;

    echo "{$planName}: {$searches} пошуків/день\n";
    echo "  Вартість OpenAI: $" . number_format($monthlyCost, 2) . "/місяць\n";
    echo "  В гривнях: " . number_format($monthlyCost * 37, 0) . " грн/місяць\n\n";
}

echo "ВИСНОВОК: ";
if (isset($foundStores) && $foundStores >= 2 && isset($duration) && $duration < 10000) {
    echo "OpenAI інтеграція ГОТОВА для розробки\n";
} else {
    echo "Потрібно доопрацювати OpenAI промпти\n";
}

echo "\nНаступний крок: Розробка повного API endpoints\n";
?>