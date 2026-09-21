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
    | RAG (Knowledge retrieval)
    |--------------------------------------------------------------------------
    */
    'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),

    // Khi Embeddings API tạm lỗi, RAG tự chuyển về keyword để không làm gián đoạn chat.
    'rag_embeddings_enabled' => (bool) env('OPENAI_RAG_EMBEDDINGS_ENABLED', true),

    // Fallback cho PDF scan/image-only: Poppler pdftoppm render trang PDF thành ảnh
    // để model vision có thể đọc. Ví dụ Linux: /usr/bin/pdftoppm.
    'pdf_scan_renderer_binary' => env('PDFTOPPM_BINARY'),
    'pdf_scan_max_pages' => (int) env('PDF_SCAN_MAX_PAGES', 3),
    'pdf_scan_max_width' => (int) env('PDF_SCAN_MAX_WIDTH', 1280),

    // Số đoạn liên quan nhất lấy vào system prompt mỗi lượt hỏi.
    'rag_top_k' => (int) env('RAG_TOP_K', 4),

    // Kích thước mỗi đoạn (ký tự) khi cắt file Knowledge.
    'rag_chunk_chars' => (int) env('RAG_CHUNK_CHARS', 900),

    // Số ký tự chồng lấn giữa 2 đoạn liền kề (giữ ngữ cảnh không bị đứt).
    'rag_chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 150),
];
