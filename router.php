<?php
// Router for the local PHP preview server; Apache uses data/.htaccess.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if (preg_match('~(?:^|/)(?:data|\.git|\.agents|\.codex)(?:/|$)~i',$path)) {
    http_response_code(403);
    exit('Forbidden');
}
return false;
