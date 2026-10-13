-- Semantic webshop schema. Safe to run again: every statement is IF NOT EXISTS.
-- Applied by the installer (admin/install), which then adds the vector table
-- from db/vectors_native.sql (MariaDB 11.7+) or db/vectors_blob.sql (older).

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    icon VARCHAR(16) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku VARCHAR(32) NOT NULL,
    name VARCHAR(160) NOT NULL,
    brand VARCHAR(80) NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    attributes TEXT NOT NULL, -- JSON object of key attributes, e.g. {"Weight": "450 g"}
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id),
    KEY idx_products_price (price),
    FULLTEXT KEY ft_products (name, brand, description, attributes),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Query text -> vector, so repeat and example queries skip the embeddings service.
-- cache_key = sha256 of model signature + query; vectors are packed little-endian float32.
CREATE TABLE IF NOT EXISTS query_embeddings (
    cache_key CHAR(64) NOT NULL,
    query VARCHAR(500) NOT NULL,
    model VARCHAR(190) NOT NULL,
    dimensions SMALLINT UNSIGNED NOT NULL,
    embedding BLOB NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
