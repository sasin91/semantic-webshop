-- One vector per product, for MariaDB before 11.7: the vector is packed little-endian
-- float32 in a BLOB and cosine similarity is computed in PHP (fine for a few thousand products).
-- Same columns as vectors_native.sql, so the rest of the app does not care which one is used.

CREATE TABLE IF NOT EXISTS product_embeddings (
    product_id INT UNSIGNED NOT NULL,
    model VARCHAR(120) NOT NULL,
    model_version VARCHAR(120) NOT NULL,
    dimensions SMALLINT UNSIGNED NOT NULL,
    text_hash CHAR(64) NOT NULL,
    embedding BLOB NOT NULL,
    embedded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    CONSTRAINT fk_product_embeddings_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
