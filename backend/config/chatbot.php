<?php

return [
    // Base URL of the AI team's service. Leave empty to use the built-in stub reply during development.
    'url' => env('CHATBOT_URL'),
    'api_key' => env('CHATBOT_API_KEY'),
    'timeout' => (int) env('CHATBOT_TIMEOUT', 20),
    'voice_timeout' => (int) env('CHATBOT_VOICE_TIMEOUT', 60), // speech-to-text + emotion takes longer

    // How many previous messages are sent to the AI as context.
    'history_limit' => (int) env('CHATBOT_HISTORY_LIMIT', 10),

    'fallback_reply' => 'معلش، في مشكلة مؤقتة عندي دلوقتي. جرّب تبعت رسالتك تاني بعد شوية.',
    'stub_voice_reply' => 'وصلتني رسالتك الصوتية. (رد تجريبي لحد ما يتوصل فريق الـ AI)',
    'stub_reply' => 'أنا سامعك. تحب تحكيلي أكتر عن اللي حاسس بيه؟',
];
