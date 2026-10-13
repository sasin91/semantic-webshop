# Semantic Shop

A small web shop whose search understands **meaning**, not just keywords. Search for
*"warm jacket for hiking in the rain"* and it finds the *Insulated Softshell, Water-Resistant*,
although the two share no words. Search in Danish (*"gave til en kaffeelsker"*) and it finds the
coffee gift box in the English catalogue. Paste a SKU or a model number and keyword search still
gets it exactly right.

Built with [Trongate v2](https://trongate.io) on PHP 8.4 and MariaDB, with
[EmbeddingGemma 2](https://huggingface.co/google/embeddinggemma-2) served by llama.cpp. No other
framework, no build step: plain CSS and a little vanilla JS. Made to run on trongate.cloud.

## What it demonstrates

- **Semantic search**: every product is embedded with EmbeddingGemma 2; a query is embedded
  the same way and the closest products by cosine similarity win.
- **Hybrid search** (the default): semantic and MariaDB FULLTEXT results merged with
  Reciprocal Rank Fusion, so exact SKUs, brand names and model numbers still hit.
- **Why it matched**: every result has a badge (Semantic, Keyword or Both) and its score.
- **Compare mode** (`/compare`): one query as *Keyword only*, *Semantic* and *Hybrid*, side by
  side, with example queries that show where each one wins.
- **More like this** on product pages: the nearest products to the product's own vector.
- **Filters** (category, price, in stock) are ordinary SQL `WHERE` clauses, applied the same way
  in every search mode.
- **Graceful fallback**: if the embeddings service is down or takes over 2 s, you get keyword
  results and a small notice, never an error page.
- **Admin** (`/admin`): install and seed, edit products (re-embedded on save), embed or re-embed
  the catalogue with a progress bar, embedding status per product, service health and latency.

## How it is built

| Path | What it does |
| --- | --- |
| `modules/shop/` | The shop front: grid, filters, product page, compare page |
| `modules/search/` | Keyword, semantic and hybrid search (`Search::_run()`), RRF in `Search::_fuse()` |
| `modules/embeddings/Embeddings.php` | The embeddings client: `embed(array $texts, string $mode)`, query cache, indexing, health |
| `modules/embeddings/Embeddings_model.php` | The vector store (both backends behind one class) and `document_text()` |
| `modules/admin/` | Install, product editing, embedding progress, service health |
| `modules/health/` | `GET /health`: 200 without touching the database or the model |
| `config/search.php` | Every app setting, each overridable by an environment variable |
| `db/schema.sql`, `db/vectors_*.sql` | Tables; the vector table depends on the MariaDB version |
| `db/seed/products.json` | 400 demo products in 10 categories |
| `bin/eval.php` | recall@10 of the three search modes over 22 queries |

### Embeddings

`Embeddings::embed()` posts to llama-server's OpenAI-compatible `/v1/embeddings` and adds the
prompts from the EmbeddingGemma 2 model card, which the model was trained with:

- query: `task: search result | query: {text}`
- document: `title: {name} | text: {category}. {brand}. {description} {attributes}.`

Documents are embedded in batches of 16 with a 30 s timeout and 2 retries with backoff; a
visitor's query gets one try with a 2 s timeout. Vectors are cut to the configured size
(EmbeddingGemma is a Matryoshka model: 768, 512, 256 or 128) and normalized to unit length.

Every stored vector records the **model, model version and dimensions** that made it, plus a
hash of the text it was made from. Search only uses vectors from the configured model. A
product whose text changed, or whose vector came from another model, shows as outdated in the
admin and is re-embedded by *Embed missing and outdated*. Saving a product in the admin embeds
it straight away.

Query vectors are cached in the `query_embeddings` table, so repeated and example queries never
wait for the model (and keep working while it is down).

### Vector storage

`Embeddings_model::install()` checks the server version:

- **MariaDB 11.7 or newer**: a `VECTOR(768)` column with a cosine `VECTOR INDEX`, searched
  with `ORDER BY VEC_DISTANCE_COSINE(...) LIMIT n` in SQL.
- **Older MariaDB, or MySQL**: vectors packed as little-endian float32 in a `BLOB`, and cosine
  similarity computed in PHP over the filtered catalogue, which is fast enough for a few
  thousand products.

Both sit behind the same methods (`save()`, `nearest()`, `product_vector()`), so the rest of the
app does not know which one is in use. Set `VECTOR_BACKEND=blob` to force the fallback.

### How the hybrid ranking works

1. **Keyword**: MariaDB `MATCH ... AGAINST` in natural language mode over name, brand,
   description and attributes. A query that equals a SKU always matches that product.
2. **Semantic**: the 40 nearest products to the query vector, dropping any with a cosine
   similarity under `min_similarity` (0.60; unrelated products score around 0.6).
3. **Reciprocal Rank Fusion**: each list gives every product it found `weight / (k + rank)`,
   and the sums decide the order. Only ranks are compared, never the raw scores, which live on
   different scales (FULLTEXT relevance vs cosine). A product near the top of both lists wins
   and gets the *Both* badge.
4. An exact SKU match is pinned to the top.

The settings were tuned with `bin/eval.php`: `k = 20` and a keyword weight of 0.5, because
FULLTEXT matches any shared word ("hiking", "rain", "home") and would otherwise crowd out the
better semantic matches. Results on the seed catalogue:

| | keyword | semantic | hybrid |
| --- | --- | --- | --- |
| mean recall@10 (22 queries) | 0.54 | 0.83 | 0.87 |

Keyword wins on SKUs and model numbers, semantic on intent, synonyms and Danish queries, and
hybrid keeps both. Run it yourself from the app folder:

```bash
php bin/eval.php -v
```

Try other settings without editing anything, e.g. `KEYWORD_WEIGHT=0.7 RRF_K=60 php bin/eval.php`.

## Local setup

You need PHP 8.2+ with `pdo_mysql` and `curl`, MariaDB (11.7+ to try the native vector
backend), and Docker for the embeddings server.

1. **Embeddings server**: llama.cpp with EmbeddingGemma 2, downloaded from Hugging Face on
   first start (about 560 MB):

   ```bash
   docker run -d --name embeddings -p 8081:8080 -v llama-cache:/root/.cache -e LLAMA_ARG_HF_REPO=ggml-org/embeddinggemma-2-GGUF:Q8_0 -e LLAMA_ARG_EMBEDDINGS=1 -e LLAMA_ARG_POOLING=mean -e LLAMA_ARG_CTX_SIZE=2048 -e LLAMA_ARG_BATCH=2048 -e LLAMA_ARG_UBATCH=2048 -e LLAMA_ARG_HOST=0.0.0.0 -e LLAMA_ARG_PORT=8080 ghcr.io/ggml-org/llama.cpp:server-b11552
   ```

   It is ready when `curl http://127.0.0.1:8081/health` says `ok`.

2. **The app**: serve this folder (e.g. with Laravel Herd as `http://semantic-webshop.test/`),
   create an empty database, and set `BASE_URL` in `config/config.php` and the connection in
   `config/database.php`.

3. **Local settings**: create `config/search.local.php` (git-ignored):

   ```php
   <?php
   return [
       'admin_password' => 'pick-one',
       'embeddings_url' => 'http://127.0.0.1:8081',
   ];
   ```

4. Open `/admin`, sign in, press **Install and seed**, then **Embed missing and outdated**
   (400 products take about 10 s on a laptop CPU).

## trongate.cloud setup

1. **Create the app** from this repository. There is no Dockerfile on purpose: the platform
   builds the image itself and renders `config/config.php` and `config/database.php`, which is
   why the app's own settings live in `config/search.php`.
2. **Add a project service named `embeddings`**:
   - Image: `ghcr.io/ggml-org/llama.cpp:server-b11552`
   - Port: `8080`
   - Memory: `1024` MB (the Q8_0 model uses about 500 MB of RAM; the rest is headroom)
   - Environment variables:

     | Name | Value |
     | --- | --- |
     | `LLAMA_ARG_HF_REPO` | `ggml-org/embeddinggemma-2-GGUF:Q8_0` |
     | `LLAMA_ARG_EMBEDDINGS` | `1` |
     | `LLAMA_ARG_POOLING` | `mean` |
     | `LLAMA_ARG_CTX_SIZE` | `2048` |
     | `LLAMA_ARG_BATCH` | `2048` |
     | `LLAMA_ARG_UBATCH` | `2048` |
     | `LLAMA_ARG_HOST` | `0.0.0.0` |
     | `LLAMA_ARG_PORT` | `8080` |
     | `LLAMA_CACHE` | `/var/tmp/llama` |

     The service runs as an unprivileged user, so the model goes to `/var/tmp`, which anyone
     may write to and which, unlike the 512 MB `/tmp` volume, has room for it. It is downloaded
     again whenever the service restarts.

   The app gets `EMBEDDINGS_URL` (`http://s{id}:8080`) on its next deploy.
3. **App settings**:
   - Health check path: `/health`, so a deploy never waits for the model to load.
   - Environment variable `ADMIN_PASSWORD`: the admin is locked until it is set.
   - Trongate `ENV`: `prod`, so visitors never see PHP error details.
4. **Deploy**, then open `https://{your-app}/admin`, press **Install and seed**, then
   **Embed missing and outdated**. Both are safe to run again.

### Cold starts

Apps can scale to zero, while project services stay on. If the model is still loading
(llama-server answers 503 until it is ready) or answers slower than 2 s, search shows keyword
results with a notice until it is back, and cached queries keep working.

## Settings

All in `config/search.php`. Each can be set with an environment variable of the same name in
upper case, which wins over the file.

| Setting | Default | |
| --- | --- | --- |
| `EMBEDDINGS_URL` | `http://127.0.0.1:8081` | Set by trongate.cloud from the `embeddings` service |
| `EMBEDDINGS_MODEL` | `google/embeddinggemma-2` | Stored with every vector |
| `EMBEDDINGS_MODEL_VERSION` | `ggml-org/embeddinggemma-2-GGUF Q8_0` | Change it when you change the model file |
| `EMBEDDINGS_DIMENSIONS` | `768` | 768, 512, 256 or 128; press Install again after changing it |
| `QUERY_TIMEOUT_SECONDS` | `2.0` | Slower than this, a search falls back to keywords |
| `DOCUMENT_TIMEOUT_SECONDS` | `30.0` | Per batch while indexing |
| `DOCUMENT_RETRIES` | `2` | |
| `DOCUMENT_BATCH_SIZE` | `16` | |
| `VECTOR_BACKEND` | `auto` | `native`, `blob`, or `auto` (by MariaDB version) |
| `RRF_K` | `20` | |
| `KEYWORD_WEIGHT` | `0.5` | |
| `SEMANTIC_WEIGHT` | `1.0` | |
| `CANDIDATES` | `40` | Results taken from each list before merging |
| `MIN_SIMILARITY` | `0.60` | |
| `ADMIN_PASSWORD` | *(empty: admin locked)* | |

Changing the model, version or dimensions makes every stored vector outdated: press
**Re-embed all** in the admin.
