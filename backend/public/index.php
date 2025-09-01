<?php
// backend/public/api/analyze.php
// Головний endpoint для аналізу продуктів
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../vendor/autoload.php';

try {
    // Завантажуємо конфігурацію
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();

//    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//        throw new Exception('Тільки POST метод дозволений', 405);
//    }

    $startTime = microtime(true);

    // Ініціалізуємо сервіси
    $openaiService = new \PriceFinder\Services\OpenAIService($_ENV['OPENAI_API_KEY']);

    // Визначаємо локацію користувача
    $userLocation = null;

    if (isset($_POST['user_location'])) {
        $userLocation = $_POST['user_location'];

    } else {
        $userLocation = [
            'country' => 'Ukraine',
            'city' => 'Kyiv',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
    }

    $productData = null;
    $searchType = '';
    $searchQuery = '';

    if (isset($_POST['text_query']) && !empty(trim($_POST['text_query']))) {
        // Обробка текстового пошуку
        $searchQuery = trim($_POST['text_query']);

        if (strlen($searchQuery) < 2) {
            throw new Exception('Запит занадто короткий. Мінімум 2 символи.');
        }

        if (strlen($searchQuery) > 500) {
            throw new Exception('Запит занадто довгий. Максимум 500 символів.');
        }

        $productData = $openaiService->searchProductByText($searchQuery, $userLocation);
        $searchType = 'text';

    } else if (isset($_POST['image'])) {
        // Валідація зображення
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        // Обробка фото
        $imageData = $_POST['image'];
        $imageName = $_POST['image_name'] ?? 'uploaded_image';
        $imageType = $_POST['image_type'] ?? 'image/jpeg';


        if (!in_array($imageType, $allowedTypes)) {
            throw new Exception('Непідтримуваний тип файлу. Використовуйте JPEG, PNG або WebP.');
        }

        exit;
        // Аналізуємо зображення
        $productData = $openaiService->analyzeProductImage($imageData, $userLocation);
        $searchType = 'photo';
        $searchQuery = 'Зображення: ' . $imageName;
        // Видаляємо тимчасовий файл


    } else {
        throw new Exception('Не надано зображення або текстовий запит');
    }

    $endTime = microtime(true);
    $processingTime = round(($endTime - $startTime) * 1000);

    // Оновлюємо час обробки в метаданих
    if (isset($productData['meta'])) {
        $productData['meta']['response_time_ms'] = $processingTime;
    }

    // Покращуємо результати
    $productData = $openaiService->enhanceResults($productData);

    // Генеруємо звіт
    $report = $openaiService->generateSearchReport($productData);

    // Підготовка відповіді
    $response = [
        'success' => true,
        'search_type' => $searchType,
        'processing_time_ms' => $processingTime,
        'timestamp' => date('c'),
        'query' => $searchQuery,
        'user_location' => $userLocation,

        // Дані продукту
        'product' => $productData['product_identification'] ?? [],
        'results' => $productData['search_results'] ?? [],
        'market_analysis' => $productData['market_analysis'] ?? [],
        'report' => $report,

        // Метадані
        'meta' => $productData['meta'] ?? [],
        'limited_results' => $productData['limited_results'] ?? false
    ];

    // Логування для аналітики
    error_log(sprintf(
        'PriceFinder Search: Type=%s, Query=%s, Results=%d, Time=%dms, Tokens=%d',
        $searchType,
        $searchQuery,
        count($productData['search_results'] ?? []),
        $processingTime,
        $productData['meta']['tokens_used'] ?? 0
    ));

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    $errorCode = $e->getCode() ?: 500;
    http_response_code($errorCode);

    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $errorCode,
        'timestamp' => date('c')
    ];

    // Додаємо поради по типу помилки
    if (strpos($e->getMessage(), 'OpenAI') !== false) {
        $errorResponse['suggestion'] = 'Перевірте налаштування OpenAI API';
    } elseif (strpos($e->getMessage(), 'файл') !== false) {
        $errorResponse['suggestion'] = 'Перевірте формат та розмір файлу зображення';
    } elseif (strpos($e->getMessage(), 'запит') !== false) {
        $errorResponse['suggestion'] = 'Уточніть пошуковий запит';
    }

    echo json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Детальне логування помилок
    error_log(sprintf(
        'PriceFinder Error: %s | Code: %d | File: %s:%d | Request: %s',
        $e->getMessage(),
        $e->getCode(),
        $e->getFile(),
        $e->getLine(),
        json_encode($_POST)
    ));
}
?>