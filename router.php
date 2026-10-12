<?php
$rel = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $rel;

if (preg_match('#^/(data|includes)(/|$)#', $rel) || str_contains($rel, '..')) {
    http_response_code(403);
    exit('Forbidden');
}

if ($rel === '/' || is_file($file)) {
    return false;
}

http_response_code(404);
exit('Not found');