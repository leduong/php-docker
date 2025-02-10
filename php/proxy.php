<?php
header("Content-Type: application/json");

// Ghi log vào file
function logToFile($message)
{
  $debug = getenv('DEBUG') ?: false;
  if ($debug) {
    file_put_contents("/var/log/php_proxy.log", date("[Y-m-d H:i:s]") . " " . $message . "\n", FILE_APPEND);
  }
}

// Đọc JSON từ body
$rawInput = file_get_contents("php://input");
logToFile("Received request: " . $rawInput);

$inputData = json_decode($rawInput, true);

// Kiểm tra đầu vào hợp lệ
if (!isset($inputData['url'])) {
  http_response_code(400);
  echo json_encode(["error" => "Missing 'url' parameter"]);
  logToFile("Error: Missing 'url' parameter");
  exit;
}

$targetUrl = $inputData['url'];
$headers = $inputData['headers'] ?? [];
$body = $inputData['body'] ?? [];
$args = $inputData['args'] ?? [];
$method = strtoupper($inputData['method'] ?? 'GET');

$userAgent = $headers['User-Agent'] ?? 'Mozilla/5.0 (X11; Linux i686; rv:133.0) Gecko/20100101 Firefox/133.0';

// Nếu có args, thêm vào URL dưới dạng query string
if (!empty($args)) {
  $queryString = http_build_query($args);
  $targetUrl .= (strpos($targetUrl, '?') === false ? '?' : '&') . $queryString;
}

// Kiểm tra Content-Type để encode dữ liệu phù hợp
$contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
if (stripos($contentType, 'application/x-www-form-urlencoded') !== false) {
  logToFile("Encoding body as x-www-form-urlencoded");
  $body = http_build_query($body);
} else {
  $body = json_encode($body);
}

// Ghi log dữ liệu nhận được
logToFile("Target URL: " . $targetUrl);
logToFile("Method: " . $method);
logToFile("Headers: " . json_encode($headers));
logToFile("Body: " . $body);

// Cấu hình proxy (nếu cần)
$proxy = getenv('PROXY_URL') ?: '';
if ($proxy) {
  logToFile("Using proxy: " . $proxy);
}

// Khởi tạo cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

if ($proxy != '') {
  curl_setopt($ch, CURLOPT_PROXY, $proxy);
}

curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(
  fn($k, $v) => "$k: $v",
  array_keys($headers),
  $headers
));

if ($method !== 'GET' && !empty($body)) {
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

// Gửi request và nhận response
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

logToFile("Response Code: " . $httpCode);
logToFile("Response Body: " . $response);
if ($error) {
  logToFile("cURL Error: " . $error);
}

// Trả về kết quả
http_response_code($httpCode);
echo $response;
