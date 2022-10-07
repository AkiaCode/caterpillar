<?php
// HTTP proxy implementation with PHP socket
// Namhyeon Go <gnh1201@gmail.com>
// Created at: 2022-10-06
// Updated at: 2022-10-07

if (strpos($_SERVER['HTTP_USER_AGENT'], "php-httpproxy/") !== 0) {
    exit('<!DOCTYPE html><html><head><title>It works!</title><meta charset="utf-8"></head><body><h1>It works!</h1></body></html>');
}

ini_set("default_socket_timeout", 1);  // must be. because of `feof()` works
ini_set("max_execution_time", 0);

function parse_headers($str) { // Parses HTTP headers into an array
    // https://stackoverflow.com/questions/16934409/curl-as-proxy-deal-with-https-connect-method
    // https://stackoverflow.com/questions/12433958/how-to-parse-response-headers-in-php
    $headers = array();

    $lines = preg_split("'\r?\n'", $str);

    $first_line = array_shift($lines);
    $headers['@method'] = explode(' ', $first_line);

    foreach ($lines as $line) {
        if (!preg_match('/^([^:]+):(.*)$/', $line, $out)) continue;
        $headers[$out[1]] = trim($out[2]);
    }

    return $headers;
}

$data = json_decode(file_get_contents('php://input'), true);

$buffer_size = $data['chunksize'];

$relay_data = base64_decode($data['data']);
$relay_headers = parse_headers($relay_data);
$relay_port = intval($data['port']);
$relay_scheme = $data['scheme'];
$relay_hostname = $data['server'];

switch ($relay_headers['@method'][0]) {
    case "CONNECT": // {
        echo sprintf("%s 200 Connection Established\r\n\r\n", $relay_headers['@method'][2]);
        break;
    // }

    default: // {
        $fp = fsockopen($relay_hostname, $relay_port, $errno, $errstr, 1);

        if (!$fp) {
            echo "$errstr ($errno)<br />\n";
        } else {
            fwrite($fp, $relay_data);

            $buf = null;
            while (!feof($fp) && $buf !== false) {
                $buf = fgets($fp, $buffer_size);
                echo $buf;
            }

            fclose($fp);
        }
    // }
}
