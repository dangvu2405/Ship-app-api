<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
    ],

    'cloudinary' => [
        'url' => env('CLOUDINARY_URL'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_EMBEDDING_MODEL', 'bge-m3'),
    ],

    'rag' => [
        'agent_enabled' => env('RAG_AGENT_ENABLED', false),
        'sql_enabled' => env('RAG_SQL_ENABLED', false),
        'top_k' => (int) env('RAG_TOP_K', 5),
        'min_score' => (float) env('RAG_MIN_SCORE', 0.72),
        'max_context_length' => (int) env('RAG_MAX_CONTEXT_LENGTH', 4000),
    ],

    'map' => [
        'driver' => env('MAP_SERVICE_DRIVER', 'google'),
        'google_key' => env('GOOGLE_MAPS_API_KEY'),
        'mapbox_token' => env('MAPBOX_ACCESS_TOKEN'),
        'goong_key' => env('GOONG_API_KEY'),
    ],

    'shipping' => [
        'base_fee' => env('SHIPPING_BASE_FEE', 15000),
        'fee_per_km' => env('SHIPPING_FEE_PER_KM', 5000),
    ],

];
