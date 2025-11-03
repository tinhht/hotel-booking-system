<?php

namespace App\Presentation\Controllers\Api;

/**
 * Base REST Controller for API endpoints
 * Following Clean Architecture principles
 */
abstract class BaseRestController
{
    private static bool $corsHeadersSent = false;

    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * Handle CORS preflight (optimized to set headers only once)
     */
    protected function handleCors(): void
    {
        // Avoid sending CORS headers multiple times
        if (self::$corsHeadersSent) {
            return;
        }

        try {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

            self::$corsHeadersSent = true;

            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                header('Access-Control-Max-Age: 86400');
                http_response_code(204);
                exit;
            }
        } catch (\Throwable $e) {
            error_log('CORS error: ' . $e->getMessage());
            http_response_code(204);
            exit;
        }
    }


    /**
     * Send JSON response (CORS headers already set in constructor)
     */
    protected function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Success response with data
     */
    protected function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $this->json($response, $statusCode);
    }

    /**
     * Error response
     */
    protected function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->json($response, $statusCode);
    }

    /**
     * Created response (201)
     */
    protected function created(mixed $data, string $message = 'Resource created successfully'): void
    {
        $this->success($data, $message, 201);
    }

    /**
     * No content response (204)
     */
    protected function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    /**
     * Not found response (404)
     */
    protected function notFound(string $message = 'Resource not found'): void
    {
        $this->error($message, 404);
    }

    /**
     * Unauthorized response (401)
     */
    protected function unauthorized(string $message = 'Unauthorized access'): void
    {
        $this->error($message, 401);
    }

    /**
     * Forbidden response (403)
     */
    protected function forbidden(string $message = 'Access forbidden'): void
    {
        $this->error($message, 403);
    }

    /**
     * Validation error response (422)
     */
    protected function validationError(array $errors, string $message = 'Validation failed'): void
    {
        $this->error($message, 422, $errors);
    }

    /**
     * Conflict response (409)
     */
    protected function conflict(string $message = 'Resource conflict'): void
    {
        $this->error($message, 409);
    }

    /**
     * Internal server error (500)
     */
    protected function serverError(string $message = 'Internal server error'): void
    {
        $this->error($message, 500);
    }

    /**
     * Get JSON input from request body
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return [];
        }

        try {
            return json_decode($input, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (\JsonException $e) {
            $this->error('Invalid JSON format in request body', 400);
        }
        return [];
    }

    /**
     * Get query parameters
     */
    protected function getQueryParams(): array
    {
        return $_GET;
    }

    /**
     * Get request method
     */
    protected function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Get session data (session already started in index.php)
     */
    protected function session(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Check if user is authenticated (session already started in index.php)
     */
    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current authenticated user (session already started in index.php)
     */
    protected function getCurrentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->unauthorized('Authentication required');
        }
    }

    /**
     * Require admin role
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            $this->forbidden('Admin access required');
        }
    }

    /**
     * Validate request data
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($rulesArray as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleValue = $ruleParts[1] ?? null;

                switch ($ruleName) {
                    case 'required':
                        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                        }
                        break;

                    case 'email':
                        if (isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a valid email address';
                        }
                        break;

                    case 'min':
                        if (isset($data[$field]) && strlen((string)$data[$field]) < (int)$ruleValue) {
                            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at least {$ruleValue} characters";
                        }
                        break;

                    case 'max':
                        if (isset($data[$field]) && strlen((string)$data[$field]) > (int)$ruleValue) {
                            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$ruleValue} characters";
                        }
                        break;

                    case 'numeric':
                        if (isset($data[$field]) && !is_numeric($data[$field])) {
                            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
                        }
                        break;

                    case 'integer':
                        if (isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_INT)) {
                            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be an integer';
                        }
                        break;

                    case 'array':
                        if (isset($data[$field]) && !is_array($data[$field])) {
                            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be an array';
                        }
                        break;

                    case 'date':
                        if (isset($data[$field])) {
                            $d = \DateTime::createFromFormat('Y-m-d', $data[$field]);
                            if (!$d || $d->format('Y-m-d') !== $data[$field]) {
                                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a valid date (Y-m-d)';
                            }
                        }
                        break;
                }
            }
        }

        return $errors;
    }
}

