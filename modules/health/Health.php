<?php
/**
 * GET /health: 200 as soon as PHP serves requests. It touches neither the
 * database nor the embeddings service, so a deploy never waits for the model.
 */
class Health extends Trongate {

    function index(): void {
        http_response_code(200);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode(['status' => 'ok']);
    }
}
