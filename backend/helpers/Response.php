<?php

/**
 * Response Helper — Standardize API responses
 * Gunakan: Response::json($data, 200) atau Response::error($message, 400)
 */
class Response {
    
    /**
     * Success response
     * @param mixed $data
     * @param int $code HTTP status code (default 200)
     */
    public static function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Success dengan struktur konsisten
     * @param mixed $data
     * @param string $message Optional message
     */
    public static function success($data = null, $message = null) {
        $response = ['success' => true];
        if ($message) $response['message'] = $message;
        if ($data !== null) $response['data'] = $data;
        self::json($response);
    }

    /**
     * Error response
     * @param string $message
     * @param int $code HTTP status code (default 400)
     */
    public static function error($message, $code = 400) {
        self::json(['error' => $message], $code);
    }

    /**
     * Validation error
     * @param array $errors Array of validation errors
     */
    public static function validationError($errors) {
        self::json(['error' => 'Validation failed', 'details' => $errors], 422);
    }

    /**
     * Unauthorized (401)
     * @param string $message
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }

    /**
     * Not found (404)
     * @param string $message
     */
    public static function notFound($message = 'Not found') {
        self::error($message, 404);
    }

    /**
     * Method not allowed (405)
     * @param string $message
     */
    public static function methodNotAllowed($message = 'Method not allowed') {
        self::error($message, 405);
    }

    /**
     * Server error (500)
     * @param string $message
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
}
