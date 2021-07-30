<?php declare(strict_types=1);

namespace PhpCli\Tx;

class Response
{
    private $body;

    private int $code;

    private array $headers;

    private array $info;

    private string $message;

    private string $raw;

    public function __construct(string $body, array $headers = [], array $info = [])
    {
        $code = $info['http_code'] ?? 200;
        $this->info = $info;
        $this->setCode($code);
        $this->setMessage($code);
        $this->setHeaders($headers);
        $this->setBody($body);
    }

    public function fails(): bool
    {
        return $this->code >= 400;
    }

    public function redirects(): bool
    {
        return $this->code < 400 && $this->code >= 300;
    }

    public function succeeds(): bool
    {
        return $this->code < 400;
    }

    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        if ($name === 'raw' && isset($this->body)) {
            return $this->body;
        }

        return null;
    }

    private function getMessage(int $code)
    {
        switch ($code) {
            // Informational 1xx
            case 100: return "Continue";
            case 101: return "Switching Protocols";

            // Successful 2xx
            case 200: return "OK";
            case 201: return "Created";
            case 202: return "Accepted";
            case 203: return "Non-Authoritative Information";
            case 204: return "No Content";
            case 205: return "Reset Content";
            case 206: return "Partial Content";

            // Redirection 3xx
            case 300: return "Multiple Choices";
            case 301: return "Moved Permanently";
            case 302: return "Found";
            case 303: return "See Other";
            case 304: return "Not Modified";
            case 305: return "Use Proxy";
            case 306: return "(Unused)";
            case 307: return "Temporary Redirect";

            // Client Error 4xx
            case 400: return "Bad Request";
            case 401: return "Unauthorized";
            case 402: return "Payment Required";
            case 403: return "Forbidden";
            case 404: return "Not Found";
            case 405: return "Method Not Allowed";
            case 406: return "Not Acceptable";
            case 407: return "Proxy Authentication Required";
            case 408: return "Request Timeout";
            case 409: return "Conflict";
            case 410: return "Gone";
            case 411: return "Length Required";
            case 412: return "Precondition Failed";
            case 413: return "Request Entity Too Large";
            case 414: return "Request-URI Too Long";
            case 415: return "Unsupported Media Type";
            case 416: return "Requested Range Not Satisfiable";
            case 417: return "Expectation Failed";
            case 418: return "I'm a teapot";

            // Server Error 5xx
            case 500: return "Internal Server Error";
            case 501: return "Not Implemented";
            case 502: return "Bad Gateway";
            case 503: return "Service Unavailable";
            case 504: return "Gateway Timeout";
            case 505: return "HTTP Version Not Supported";
        }

        return '(Uknown)';
    }

    private function setBody(string $body): self
    {
        if (isset($this->headers['content-type']) && false !== strpos($this->headers['content-type'][0], 'application/json')) {
            $this->raw = $body;
            $body = json_decode($body);
        }

        $this->body = $body;

        return $this;
    }

    private function setCode(int $code = 200): self
    {
        $this->code = $code;

        return $this;
    }

    private function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    private function setMessage(int $code = 200): self
    {
        $this->message = $this->getMessage($code);

        return $this;
    }
}