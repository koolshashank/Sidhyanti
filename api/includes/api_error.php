<?php
/** Thrown inside a request handler to unwind to a specific HTTP status + message. */
class ApiError extends Exception {
    public int $httpCode;

    public function __construct(int $httpCode, string $message) {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }
}
