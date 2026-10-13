<?php
/**
 * Settings for the shop's search. trongate.cloud renders config.php and
 * database.php itself, so everything else the app needs lives here, with
 * defaults that are safe to deploy.
 *
 * Each setting can be overridden by an environment variable with the same
 * name in upper case (EMBEDDINGS_URL, ADMIN_PASSWORD, ...), and for local
 * development by config/search.local.php (git-ignored), which returns an
 * array of the keys it changes. The environment wins over both.
 */
$settings = [
    // The embeddings service (llama.cpp's llama-server running EmbeddingGemma 2).
    // On trongate.cloud the project service named "embeddings" sets EMBEDDINGS_URL.
    'embeddings_url' => 'http://127.0.0.1:8081',

    // Stored with every vector: change any of them and every product is re-embedded.
    'embeddings_model' => 'google/embeddinggemma-2',
    'embeddings_model_version' => 'ggml-org/embeddinggemma-2-GGUF Q8_0',
    'embeddings_dimensions' => 768, // 768, or a Matryoshka size: 512, 256 or 128

    // A visitor's query gives up after this long and falls back to keyword results
    'query_timeout_seconds' => 2.0,
    // Indexing is not waited on by a visitor, so it may take longer and retry
    'document_timeout_seconds' => 30.0,
    'document_retries' => 2,
    'document_batch_size' => 16,

    // Vector backend: 'auto' uses MariaDB's VECTOR type from 11.7, else packed float BLOBs
    'vector_backend' => 'auto',

    // Hybrid ranking (Reciprocal Rank Fusion); tune with bin/eval.php
    'rrf_k' => 60,
    'keyword_weight' => 1.0,
    'semantic_weight' => 1.0,
    'candidates' => 40,          // results taken from each list before merging
    'min_similarity' => 0.30,    // semantic hits below this cosine similarity are dropped

    // Admin pages are locked while this is empty
    'admin_password' => '',
];

$local_file = __DIR__ . '/search.local.php';
if (is_file($local_file)) {
    $settings = array_merge($settings, require $local_file);
}

foreach ($settings as $key => $value) {
    $env = getenv(strtoupper($key));
    if ($env === false || $env === '') {
        continue;
    }

    $settings[$key] = match (true) {
        is_int($value) => (int) $env,
        is_float($value) => (float) $env,
        default => $env,
    };
}

return $settings;
