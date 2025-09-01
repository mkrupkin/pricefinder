<?php
// backend/src/Services/OpenAIService.php
// Повний сервіс для роботи з OpenAI API

namespace PriceFinder\Services;

class OpenAIService {
    private $client;
    private $apiKey;
    private $modelText = 'gpt-4';
    private $modelVision = 'gpt-4-vision-preview';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 60
        ]);
    }

    /**
     * Аналіз продукту по зображенню
     */
    public function analyzeProductImage($imageBase64, $userLocation = null) {
        $prompt = $this->buildImageAnalysisPrompt($userLocation);

        $data = [
            'model' => $this->modelVision,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:image/jpeg;base64,{$imageBase64}",
                                'detail' => 'high'
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 1500,
            'temperature' => 0.2
        ];

        $response = $this->makeRequest('chat/completions', $data);
        return $this->parseResponse($response, 'image');
    }

    /**
     * Пошук продукту по тексту
     */
    public function searchProductByText($query, $userLocation = null) {
        $prompt = $this->buildTextSearchPrompt($query, $userLocation);

        $data = [
            'model' => $this->modelText,
            'messages' => [
                ['role' => 'system', 'content' => 'Ви експерт з пошуку товарів в Україні та світі. Надаєте точну інформацію про магазини та ціни.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.2
        ];

        $response = $this->makeRequest('chat/completions', $data);



        return $this->parseResponse($response, 'text');
    }

    /**
     * Створення промпту для аналізу зображення
     */
    private function buildImageAnalysisPrompt($userLocation) {
        $location = $userLocation ?
            "{$userLocation['city']}, {$userLocation['country']}" :
            'Київ, Україна';

        return "
АНАЛІЗ ПРОДУКТУ ЗА ЗОБРАЖЕННЯМ

Проаналізуйте це зображення продукту та знайдіть де його можна купити.

ЗАВДАННЯ:
1. Точно ідентифікуйте продукт (бренд, модель, характеристики)
2. Знайдіть РЕАЛЬНІ магазини де можна купити цей продукт
3. Пріоритет: {$location} та околиці

ПОКРИТТЯ ПОШУКУ:
🌍 ГЕОГРАФІЯ:
- Локальні магазини в {$location}
- Національні мережі України
- Міжнародні магазини з доставкою
- Спеціалізовані імпортери

🏪 ТИПИ МАГАЗИНІВ:
1. Офіційні дилери та брендові магазини
2. Великі e-commerce платформи (Rozetka, Amazon тощо)
3. Електронні ритейлери та техномагазини
4. Універмаги та супермаркети
5. Спеціалізовані нішеві ритейлери
6. Marketplace продавці
7. Оптові постачальники
8. Аукціони та біржі
9. Секонд-хенд та відновлені товари

📊 ДЛЯ КОЖНОГО РЕЗУЛЬТАТУ:
- Назва магазину та категорія
- Реалістична ціна в UAH (актуальна для 2024-2025)
- Оригінальна валюта якщо інша
- Статус наявності товару
- Час доставки та вартість
- Контактна інформація (сайт, телефон)
- Фізична адреса якщо є
- Рейтинг магазину
- Спеціальні пропозиції
- Способи оплати
- Умови повернення

ВИМОГИ:
- Мінімум 10-20 результатів
- Суміш: локальні (50%), національні (30%), міжнародні (20%)
- Включити бюджетні та преміум варіанти
- Враховувати вартість доставки
- Враховувати податки та мита

ВІДПОВІДЬ У JSON ФОРМАТІ:
{
  \"product_identification\": {
    \"name\": \"точна назва продукту\",
    \"brand\": \"виробник\",
    \"model\": \"номер моделі\",
    \"category\": \"категорія продукту\",
    \"key_features\": [\"особливість1\", \"особливість2\"],
    \"confidence\": 0.95
  },
  \"search_results\": [
    {
      \"store_name\": \"Назва магазину\",
      \"store_type\": \"official_retailer|marketplace|specialty|local\",
      \"price_uah\": 45000,
      \"original_price\": \"$1200 USD\",
      \"availability\": \"В наявності|Під замовлення|Немає\",
      \"delivery_time\": \"1-3 дні\",
      \"shipping_cost_uah\": 150,
      \"total_cost_uah\": 45150,
      \"contact\": {
        \"website\": \"store.com.ua\",
        \"phone\": \"+380...\",
        \"email\": \"contact@store.com\",
        \"address\": \"фізична адреса\"
      },
      \"location\": {
        \"country\": \"Ukraine\",
        \"city\": \"Київ\",
        \"region\": \"Local|National|International\"
      },
      \"rating\": 4.5,
      \"review_count\": 1250,
      \"special_offers\": \"Безкоштовна доставка від 1000 UAH\",
      \"payment_methods\": [\"Картка\", \"Готівка при отриманні\"],
      \"return_policy\": \"14 днів повернення\",
      \"notes\": \"додаткова інформація\"
    }
  ],
  \"market_analysis\": {
    \"price_range\": \"40000-55000 UAH\",
    \"average_price\": 47500,
    \"best_local_deal\": \"найкращий локальний варіант\",
    \"best_international_deal\": \"найкращий міжнародний варіант\",
    \"recommendations\": [\"рекомендації щодо покупки\"]
  }
}";
    }

    /**
     * Створення промпту для текстового пошуку
     */
    private function buildTextSearchPrompt($query, $userLocation) {
        $location = $userLocation ?
            "{$userLocation['city']}, {$userLocation['country']}" :
            'Київ, Україна';
        return "
УНІВЕРСАЛЬНИЙ ПОШУК ПРОДУКТУ: ".$query."

ЗАВДАННЯ:
1. Точно ідентифікуйте продукт (бренд, модель, характеристики)
2. Знайдіть РЕАЛЬНІ магазини де можна купити цей продукт
3. Пріоритет: {$location} та околиці

ПОКРИТТЯ ПОШУКУ:
🌍 ГЕОГРАФІЯ:
- Локальні магазини в {$location}
- Національні мережі України
- Міжнародні магазини з доставкою
- Спеціалізовані імпортери

🏪 ТИПИ МАГАЗИНІВ:
1. Офіційні дилери та брендові магазини
2. Великі e-commerce платформи (Rozetka, Amazon тощо)
3. Електронні ритейлери та техномагазини
4. Універмаги та супермаркети
5. Спеціалізовані нішеві ритейлери
6. Marketplace продавці
7. Оптові постачальники
8. Аукціони та біржі
9. Секонд-хенд та відновлені товари

📊 ДЛЯ КОЖНОГО РЕЗУЛЬТАТУ:
- Назва магазину та категорія
- Реалістична ціна в UAH (актуальна для 2024-2025)
- Оригінальна валюта якщо інша
- Статус наявності товару
- Час доставки та вартість
- Контактна інформація (сайт, телефон)
- Фізична адреса якщо є
- Рейтинг магазину
- Спеціальні пропозиції
- Способи оплати
- Умови повернення

ВИМОГИ:
- Мінімум 10-20 результатів
- Суміш: локальні (50%), національні (30%), міжнародні (20%)
- Включити бюджетні та преміум варіанти
- Враховувати вартість доставки
- Враховувати податки та мита

ВІДПОВІДЬ У JSON ФОРМАТІ:
{
  \"product_identification\": {
    \"name\": \"точна назва продукту\",
    \"brand\": \"виробник\",
    \"model\": \"номер моделі\",
    \"category\": \"категорія продукту\",
    \"key_features\": [\"особливість1\", \"особливість2\"],
    \"confidence\": 0.95
  },
  \"search_results\": [
    {
      \"store_name\": \"Назва магазину\",
      \"store_type\": \"official_retailer|marketplace|specialty|local\",
      \"price_uah\": 45000,
      \"original_price\": \"$1200 USD\",
      \"availability\": \"В наявності|Під замовлення|Немає\",
      \"delivery_time\": \"1-3 дні\",
      \"shipping_cost_uah\": 150,
      \"total_cost_uah\": 45150,
      \"contact\": {
        \"website\": \"store.com.ua\",
        \"phone\": \"+380...\",
        \"email\": \"contact@store.com\",
        \"address\": \"фізична адреса\"
      },
      \"location\": {
        \"country\": \"Ukraine\",
        \"city\": \"Київ\",
        \"region\": \"Local|National|International\"
      },
      \"rating\": 4.5,
      \"review_count\": 1250,
      \"special_offers\": \"Безкоштовна доставка від 1000 UAH\",
      \"payment_methods\": [\"Картка\", \"Готівка при отриманні\"],
      \"return_policy\": \"14 днів повернення\",
      \"notes\": \"додаткова інформація\"
    }
  ],
  \"market_analysis\": {
    \"price_range\": \"40000-55000 UAH\",
    \"average_price\": 47500,
    \"best_local_deal\": \"найкращий локальний варіант\",
    \"best_international_deal\": \"найкращий міжнародний варіант\",
    \"recommendations\": [\"рекомендації щодо покупки\"]
  }
}";
    }

    /**
     * Виконання запиту до OpenAI API
     */
    private function makeRequest($endpoint, $data) {
        try {
            print_r( $data);
            exit;
            $response = $this->client->post($endpoint, [
                'json' => $data
            ]);

            $body = $response->getBody()->getContents();

            $result = json_decode($body, true);

            if (!$result || !isset($result['choices'][0]['message']['content'])) {
                throw new \Exception('Некоректна відповідь від OpenAI API');
            }

            return $result;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody() : '';

            switch ($statusCode) {
                case 401:
                    throw new \Exception('Недійсний API ключ OpenAI');
                case 429:
                    throw new \Exception('Перевищено ліміт запитів OpenAI');
                case 402:
                    throw new \Exception('Недостатньо коштів на рахунку OpenAI');
                default:
                    throw new \Exception('Помилка OpenAI API: ' . $e->getMessage());
            }
        }
    }

    /**
     * Парсинг та валідація відповіді
     */
    private function parseResponse($response, $searchType) {
        $content = $response['choices'][0]['message']['content'];

        // Знаходимо JSON у відповіді
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}') + 1;

        if ($jsonStart === false || $jsonEnd === false) {
            throw new \Exception('JSON не знайдено у відповіді OpenAI');
        }

        $jsonContent = substr($content, $jsonStart, $jsonEnd - $jsonStart);
        $result = json_decode($jsonContent, true);

        if (!$result) {
            throw new \Exception('Некоректний JSON у відповіді: ' . json_last_error_msg());
        }

        // Валідація структури
        $this->validateResponse($result);

        // Додаємо метадані
        $result['meta'] = [
            'search_type' => $searchType,
            'search_timestamp' => time(),
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
            'model_used' => $response['model'] ?? $this->modelText,
            'api_cost_usd' => $this->calculateCost($response['usage'] ?? []),
            'response_time_ms' => 0 // Буде встановлено в основному endpoint
        ];

        return $result;
    }

    /**
     * Валідація структури відповіді
     */
    private function validateResponse($result) {
        if (!isset($result['product_identification']) || !isset($result['search_results'])) {
            throw new \Exception('Відсутні обовязкові поля у відповіді');
        }

        if (!is_array($result['search_results']) || count($result['search_results']) < 3) {
            throw new \Exception('Недостатньо результатів пошуку (мінімум 3)');
        }

        // Перевіряємо кожен результат
        foreach ($result['search_results'] as $index => $store) {
            $required = ['store_name', 'price_uah', 'availability'];
            foreach ($required as $field) {
                if (!isset($store[$field])) {
                    throw new \Exception("Відсутнє поле {$field} у результаті {$index}");
                }
            }

            // Валідація ціни
            if (!is_numeric($store['price_uah']) || $store['price_uah'] <= 0) {
                throw new \Exception("Некоректна ціна у результаті {$index}");
            }
        }
    }

    /**
     * Розрахунок вартості API запиту
     */
    private function calculateCost($usage) {
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;

        // GPT-4 pricing: $0.03/1K prompt tokens, $0.06/1K completion tokens
        $promptCost = ($promptTokens / 1000) * 0.03;
        $completionCost = ($completionTokens / 1000) * 0.06;

        return round($promptCost + $completionCost, 4);
    }

    /**
     * Покращення результатів пошуку
     */
    public function enhanceResults($results, $userPreferences = []) {
        if (!isset($results['search_results'])) {
            return $results;
        }

        // Сортуємо за релевантністю
        usort($results['search_results'], function($a, $b) use ($userPreferences) {
            $scoreA = $this->calculateRelevanceScore($a, $userPreferences);
            $scoreB = $this->calculateRelevanceScore($b, $userPreferences);
            return $scoreB <=> $scoreA; // Сортування за спаданням
        });

        // Додаємо додаткову інформацію
        foreach ($results['search_results'] as &$store) {
            $store['relevance_score'] = $this->calculateRelevanceScore($store, $userPreferences);
            $store['is_recommended'] = $store['relevance_score'] > 0.7;

            // Додаємо категорії для фільтрації
            $store['filters'] = [
                'local' => $store['location']['region'] === 'Local',
                'official' => $store['store_type'] === 'official_retailer',
                'fast_delivery' => isset($store['delivery_time']) &&
                    strpos($store['delivery_time'], '1') !== false,
                'low_price' => $store['price_uah'] < ($results['market_analysis']['average_price'] ?? 999999)
            ];
        }

        return $results;
    }

    /**
     * Розрахунок релевантності магазину
     */
    private function calculateRelevanceScore($store, $userPreferences) {
        $score = 0.5; // Базовий рейтинг

        // Бонуси за локальність
        if (isset($store['location']['region'])) {
            switch ($store['location']['region']) {
                case 'Local': $score += 0.3; break;
                case 'National': $score += 0.2; break;
                case 'International': $score += 0.1; break;
            }
        }

        // Бонуси за тип магазину
        if (isset($store['store_type'])) {
            switch ($store['store_type']) {
                case 'official_retailer': $score += 0.2; break;
                case 'specialty': $score += 0.15; break;
                case 'marketplace': $score += 0.1; break;
            }
        }

        // Бонуси за рейтинг
        if (isset($store['rating']) && $store['rating'] > 4.0) {
            $score += ($store['rating'] - 4.0) * 0.1;
        }

        // Штрафи за високу ціну
        if (isset($userPreferences['budget_conscious']) && $userPreferences['budget_conscious']) {
            // Знижуємо рейтинг дорогих варіантів
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * Фільтрація результатів для безкоштовних користувачів
     */
    public function filterForFreePlan($results, $maxResults = 5) {
        if (!isset($results['search_results'])) {
            return $results;
        }

        // Залишаємо тільки найкращі результати
        $filtered = array_slice($results['search_results'], 0, $maxResults);

        $results['search_results'] = $filtered;
        $results['limited_results'] = true;
        $results['upgrade_message'] = 'Оновіть план щоб побачити всі результати';
        $results['original_count'] = count($results['search_results']);

        return $results;
    }

    /**
     * Генерація пошукового звіту
     */
    public function generateSearchReport($results) {
        if (!isset($results['search_results']) || empty($results['search_results'])) {
            return null;
        }

        $stores = $results['search_results'];
        $prices = array_column($stores, 'price_uah');

        $report = [
            'total_stores' => count($stores),
            'price_statistics' => [
                'min' => min($prices),
                'max' => max($prices),
                'average' => round(array_sum($prices) / count($prices)),
                'median' => $this->calculateMedian($prices)
            ],
            'store_distribution' => [
                'local' => 0,
                'national' => 0,
                'international' => 0
            ],
            'availability_summary' => [
                'in_stock' => 0,
                'limited' => 0,
                'out_of_stock' => 0
            ]
        ];

        // Рахуємо розподіл
        foreach ($stores as $store) {
            $region = $store['location']['region'] ?? 'unknown';
            if (isset($report['store_distribution'][strtolower($region)])) {
                $report['store_distribution'][strtolower($region)]++;
            }

            $availability = strtolower($store['availability'] ?? '');
            if (strpos($availability, 'наявності') !== false) {
                $report['availability_summary']['in_stock']++;
            } elseif (strpos($availability, 'замовлення') !== false) {
                $report['availability_summary']['limited']++;
            } else {
                $report['availability_summary']['out_of_stock']++;
            }
        }

        return $report;
    }

    private function calculateMedian($numbers) {
        sort($numbers);
        $count = count($numbers);
        $middle = floor(($count - 1) / 2);

        if ($count % 2) {
            return $numbers[$middle];
        } else {
            return ($numbers[$middle] + $numbers[$middle + 1]) / 2;
        }
    }
}
?>