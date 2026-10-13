-- One vector per product, MariaDB 11.7+ (native VECTOR type with a cosine vector index).
-- {dimensions} is replaced by the installer with embeddings_dimensions from config/search.php.
-- model, model_version and dimensions say which model made the vector; a vector from any
-- other model is ignored by search and re-embedded. text_hash spots products whose text changed.

CREATE TABLE IF NOT EXISTS product_embeddings (
    product_id INT UNSIGNED NOT NULL,
    model VARCHAR(120) NOT NULL,
    model_version VARCHAR(120) NOT NULL,
    dimensions SMALLINT UNSIGNED NOT NULL,
    text_hash CHAR(64) NOT NULL,
    embedding VECTOR({dimensions}) NOT NULL,
    embedded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    VECTOR INDEX vx_product_embeddings (embedding) M=8 DISTANCE=cosine,
    CONSTRAINT fk_product_embeddings_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
