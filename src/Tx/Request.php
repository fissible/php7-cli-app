<?php declare(strict_types=1);

namespace PhpCli\Tx;

use PhpCli\Exceptions\RequestException;

class Request
{
    private array $headers;

    private string $method;

    private array $options;

    private array $testParams;

    private string $url;

    public function __construct(string $url, array $headers = [], array $options = [], string $method = 'GET')
    {
        $this->setUrl($url);
        $this->setHeaders($headers);
        $this->setOptions($options);
        $this->setMethod($method);
    }

    public function delete(array $options = []): Response
    {
        return $this->exe('DELETE', $options);
    }

    public function get(array $query = []): Response
    {
        return $this->exe('GET', $query);
    }

    public function patch(array $body = [], array $files = []): Response
    {
        return $this->exe('PATCH', [], [
            CURLOPT_POSTFIELDS => static::fields($body, $this->headers, $files)
        ]);
    }

    public function post(array $body = [], array $files = []): Response
    {
        return $this->exe('POST', [], [
            CURLOPT_POSTFIELDS => static::fields($body, $this->headers, $files)
        ]);
    }

    public function put(array $body = [], array $files = []): Response
    {
        return $this->exe('PUT', [], [
            CURLOPT_POSTFIELDS => static::fields($body, $this->headers, $files)
        ]);
    }

    public function setHeader(string $key, $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function setHeaders(array $headers = []): self
    {
        $this->headers = static::headers($headers);

        return $this;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function setOption($key, $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function setOptions(array $options = []): self
    {
        $this->options = $options;

        return $this;
    }

    public function setTest(array $params): self
    {
        $this->testParams = $params;

        return $this;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Make a cURL request.
     * 
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param array $options
     * @return Response
     */
    private function exe(string $method = null, array $query = [], array $options = []): Response
    {
        $method = strtoupper($method ?? $this->method ?? 'GET');
        $url = $this->url;
        $headers = [];
        $error = null;

        if (!empty($query)) {
            $url .= strpos($url, '?') === false ? '?' : '';
            $url .= http_build_query($query);
        }

        $options = $this->options + $options;
        $options[CURLOPT_URL] = $url;
        $options[CURLOPT_HEADER] = $this->headers ?: 0;
        $options[CURLINFO_HEADER_OUT] = true;

        switch ($method) {
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'PATCH':
                $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                break;
            case 'POST':
                $options[CURLOPT_POST] = true;
                break;
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = "PUT";
                break;
        }

        if (isset($this->testParams)) {
            $result = $this->testParams['result'] ?? false;
            $error  = $this->testParams['error'] ?? '';
            $info   = $this->testParams['info'] ?? ['http_code' => 200];
            $info['request_header'] = $this->headers ? implode("\n", $this->headers) : '';

            if (is_bool($result)) {
                $result = '';
            }
        } else {
            $ch = curl_init();
            curl_setopt_array($ch, $options);

            curl_setopt($ch, CURLOPT_HEADERFUNCTION,
                function($curl, $header) use (&$headers) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    $headerName = strtolower(trim($header[0]));
                    if (count($header) < 2) // ignore invalid headers
                        return $len;

                    $headers[$headerName] = array_map('trim', explode(',', trim($header[1])));

                    if ($headerName === 'date' && count($headers[$headerName]) > 1) {
                        $headers[$headerName] = [implode(', ', $headers[$headerName])];
                    }

                    return $len;
                }
            );

            $result = curl_exec($ch);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);

            if (is_bool($result)) {
                $result = '';
            }

            // Trim header from response
            $headerstring = substr($result, 0, $info['header_size']);
            $result = substr($result, $info['header_size']);

            curl_close($ch);
        }

        if (!empty($error)) {
            throw new RequestException($error);
        }

        return new Response($result, $headers, $info);
    }

    /**
     * Normalize the request fields.
     * 
     * @param array $body
     * @param array $headers
     * @param array $files
     * @return array|string
     */
    private static function fields(array $body = [], array $headers = [], array $files = [])
    {
        if ($files = static::files($files)) {
            // @note: if this is needed, must pass $headers by reference
            // if (!isset($headers['Content-Type'])) {
            //     $headers['Content-Type'] = 'multipart/form-data';
            // }
            $body = array_merge($body, $files);
        } else {
            if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') {
                $body = json_encode($body);
            } else {
                // @note: if this is needed, must pass $headers by reference
                // if (!isset($headers['Content-Type'])) {
                //     $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                // }
                $body = http_build_query($body);
            }
        }

        return $body;
    }

    /**
     * Normalize and convert files paths or File objects into CURLFile objects.
     * 
     * @param array $files
     * @return null|array
     */
    private static function files(array $files): ?array
    {
        if (empty($files)) {
            return null;
        }

        return array_map(function ($File) {
            if (is_array($File)) {
                return static::files($File);
            } elseif (is_string($File)) {
                $File = new File($File);
            }

            if (!($File instanceof File)) {
                throw new \InvalidArgumentException('Files must be instance of File class or path string.');
            }

            return new \CURLFile($File->path, $File->mime, $File->name);
        }, $files);
    }

    /**
     * Normalize request headers.
     * 
     * @param array $headers
     * @return null|array
     */
    private static function headers(array $headers): ?array
    {
        if (empty($headers)) {
            return [];
        }

        if (!is_int(array_key_first($headers))) {
            $h = [];
            foreach ($headers as $key => $value) {
                $h[] = $key.': '.$value;
            }
            $headers = $h;
        }

        return $headers;
    }
}