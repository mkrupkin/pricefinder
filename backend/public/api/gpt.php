
<?php


// backend/public/api/analyze.php
// Головний endpoint для аналізу продуктів

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


echo  '{
    "success": true,
    "search_type": "text",
    "processing_time_ms": 17743,
    "timestamp": "2025-09-01T09:44:55+00:00",
    "query": "iphone 16",
    "user_location": {
        "country": "Ukraine",
        "city": "Kyiv",
        "ip": "172.22.0.1"
    },
    "product": {
        "name": "iPhone 16",
        "brand": "Apple",
        "model": "A2456",
        "category": "Smartphones",
        "key_features": [
            "5G",
            "ProMotion display",
            "A16 chip",
            "Triple camera system"
        ],
        "confidence": 0.95
    },
    "results": [
        {
            "store_name": "Rozetka",
            "store_type": "marketplace",
            "price_uah": 53000,
            "original_price": "$1750 USD",
            "availability": "In Stock",
            "delivery_time": "1-2 days",
            "shipping_cost_uah": 0,
            "total_cost_uah": 53000,
            "contact": {
                "website": "rozetka.com.ua",
                "phone": "+380...",
                "email": "info@rozetka.com.ua",
                "address": "Kyiv, Ukraine"
            },
            "location": {
                "country": "Ukraine",
                "city": "Kyiv",
                "region": "Local"
            },
            "rating": 4.7,
            "review_count": 3000,
            "special_offers": "Free shipping",
            "payment_methods": [
                "Card",
                "Cash on delivery",
                "Bank transfer"
            ],
            "return_policy": "14 days return",
            "notes": "Trusted marketplace",
            "relevance_score": 0.9700000000000001,
            "is_recommended": true,
            "filters": {
                "local": true,
                "official": false,
                "fast_delivery": true,
                "low_price": true
            }
        },
        {
            "store_name": "Apple Store",
            "store_type": "official_retailer",
            "price_uah": 55000,
            "original_price": "$1800 USD",
            "availability": "In Stock",
            "delivery_time": "1-3 days",
            "shipping_cost_uah": 0,
            "total_cost_uah": 55000,
            "contact": {
                "website": "apple.com",
                "phone": "+380...",
                "email": "contact@apple.com",
                "address": "Not applicable"
            },
            "location": {
                "country": "USA",
                "city": "Cupertino",
                "region": "International"
            },
            "rating": 4.8,
            "review_count": 5000,
            "special_offers": "Free shipping",
            "payment_methods": [
                "Card",
                "Apple Pay"
            ],
            "return_policy": "14 days return",
            "notes": "Official Apple Store",
            "relevance_score": 0.88,
            "is_recommended": true,
            "filters": {
                "local": false,
                "official": true,
                "fast_delivery": true,
                "low_price": false
            }
        }
    ],
    "market_analysis": {
        "price_range": "50000-60000 UAH",
        "average_price": 55000,
        "best_local_deal": "Rozetka",
        "best_international_deal": "Apple Store",
        "recommendations": [
            "Buy from Rozetka for best local deal",
            "Buy from Apple Store for international warranty"
        ]
    },
    "report": {
        "total_stores": 2,
        "price_statistics": {
            "min": 53000,
            "max": 55000,
            "average": 54000,
            "median": 54000
        },
        "store_distribution": {
            "local": 1,
            "national": 0,
            "international": 1
        },
        "availability_summary": {
            "in_stock": 0,
            "limited": 0,
            "out_of_stock": 2
        }
    },
    "meta": {
        "search_type": "text",
        "search_timestamp": 1756719895,
        "tokens_used": 1287,
        "model_used": "gpt-4-0613",
        "api_cost_usd": 0.0567,
        "response_time_ms": 17743
    },
    "limited_results": false
}';

?>