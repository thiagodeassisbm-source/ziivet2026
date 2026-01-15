<?php

namespace App\Utils;

class Response
{
    /**
     * Sends a JSON response and exits.
     *
     * @param array $data
     * @param int $status
     * @return void
     */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
