<?php

return [

    'api_key' => env('OPENAI_API_KEY'),

    'organization' => env('OPENAI_ORGANIZATION'),

    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    'model' => env('OPENAI_MODEL', 'gpt-5.6-luna'),

    // Kept separate from chat: image generation uses an image model and has its
    // own usage/cost profile.
    'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2.5-flare'),
    'image_size' => env('OPENAI_IMAGE_SIZE', '1024x1024'),
    'image_quality' => env('OPENAI_IMAGE_QUALITY', 'low'),

    // GPT-5+ dùng max_completion_tokens (đúng 8192 như đang chạy).
    'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 8192),

    // Tuỳ chọn — chưa nối vào OpenAIClient (để sau nếu cần điều chỉnh mức suy luận).
    'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),

    // KHÔNG dùng temperature cho GPT-5.6 Luna (client không gửi) — bỏ hẳn khỏi config.
    'timeout' => (int) env('OPENAI_TIMEOUT', 120),

    'retry_times' => (int) env('OPENAI_RETRY_TIMES', 2),
    'retry_delay_ms' => (int) env('OPENAI_RETRY_DELAY_MS', 300),

    /*
    |--------------------------------------------------------------------------
    | Safety and work-use monitoring
    |--------------------------------------------------------------------------
    |
    | Moderation is intentionally separate from the work-use classifier: the
    | former detects unsafe content while the latter records whether a request
    | appears related to LSTS work. During the initial monitoring phase, only
    | OpenAI-moderated unsafe input is blocked.
    |
    */
    'moderation_enabled' => (bool) env('OPENAI_MODERATION_ENABLED', true),
    'moderation_model' => env('OPENAI_MODERATION_MODEL', 'omni-moderation-latest'),

    /*
    |--------------------------------------------------------------------------
    | RAG (Knowledge retrieval)
    |--------------------------------------------------------------------------
    */
    'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),

    // Khi Embeddings API tạm lỗi, RAG tự chuyển về keyword để không làm gián đoạn chat.
    'rag_embeddings_enabled' => (bool) env('OPENAI_RAG_EMBEDDINGS_ENABLED', true),

    // Fallback cho PDF scan/image-only: Poppler pdftoppm render trang PDF thành ảnh
    // để model vision có thể đọc. Ví dụ Linux: /usr/bin/pdftoppm.
    'pdf_scan_renderer_binary' => env('PDFTOPPM_BINARY'),
    'pdf_scan_max_pages' => (int) env('PDF_SCAN_MAX_PAGES', 10),
    'pdf_scan_max_width' => (int) env('PDF_SCAN_MAX_WIDTH', 1280),

    // Agent Knowledge images are OCR'd locally before indexing, so their text
    // can participate in RAG without sending the original image to an AI API.
    'knowledge_ocr_binary' => env('KNOWLEDGE_OCR_BINARY'),
    'knowledge_ocr_languages' => env('KNOWLEDGE_OCR_LANGUAGES', 'vie+eng'),
    'knowledge_ocr_timeout' => (int) env('KNOWLEDGE_OCR_TIMEOUT', 30),
    'knowledge_ocr_max_chars' => (int) env('KNOWLEDGE_OCR_MAX_CHARS', 60000),

    // Số đoạn liên quan nhất lấy vào system prompt mỗi lượt hỏi.
    'rag_top_k' => (int) env('RAG_TOP_K', 4),

    // Minimum cosine similarity for an indexed Agent Knowledge result to be
    // presented as relevant. Keyword retrieval requires at least one match.
    'rag_relevance_threshold' => (float) env('RAG_RELEVANCE_THRESHOLD', 0.32),

    // Kích thước mỗi đoạn (ký tự) khi cắt file Knowledge.
    'rag_chunk_chars' => (int) env('RAG_CHUNK_CHARS', 900),

    // Số ký tự chồng lấn giữa 2 đoạn liền kề (giữ ngữ cảnh không bị đứt).
    'rag_chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 150),

    /*
    |--------------------------------------------------------------------------
    | Public web-page reading
    |--------------------------------------------------------------------------
    |
    | When a user explicitly includes a public HTTP(S) link in a chat message,
    | AI+ retrieves a bounded text-only copy for that one turn. The reader
    | rejects private/reserved addresses and non-standard ports.
    |
    */
    'web_reading_enabled' => (bool) env('AI_PLUS_WEB_READING_ENABLED', true),

    // Uses locally installed Playwright/Chromium to read tables and other text
    // populated by client-side JavaScript. Falls back to the HTTP reader if it
    // is unavailable on a deployment target.
    'web_browser_rendering_enabled' => (bool) env('AI_PLUS_WEB_BROWSER_RENDERING_ENABLED', true),
];
