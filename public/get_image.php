<?php
require __DIR__ . '/../vendor/autoload.php';

$config = include(__DIR__ . '/../src/config.php');
require __DIR__ . '/../src/google-drive.php';

$fileId = $_GET['id'] ?? null;
if (!$fileId) {
    header('HTTP/1.1 400 Bad Request');
    exit('File ID is required');
}

// 設置快取目錄
$cacheDir = __DIR__ . '/../cache/images';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$cacheFile = $cacheDir . '/' . md5($fileId);
$cacheTime = 604800; // 7天快取

try {
    // 檢查快取
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        $imageData = file_get_contents($cacheFile);
        $contentType = mime_content_type($cacheFile);
    } else {
        $googleDriveService = new GoogleDriveService(
            $config['google_drive']['credentials_path'],
            $config['google_drive']['folder_id']
        );

        $imageUrl = $googleDriveService->getImageUrl($fileId);
        
        // 使用 cURL 獲取圖片
        $ch = curl_init($imageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_ENCODING => '', // 接受壓縮
            CURLOPT_USERAGENT => 'Mozilla/5.0'
        ]);
        
        $imageData = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // 儲存到快取
        if ($imageData) {
            file_put_contents($cacheFile, $imageData);
        }
    }

    // 設置響應標頭
    header('Content-Type: ' . ($contentType ?: 'image/jpeg'));
    header('Cache-Control: public, max-age=' . $cacheTime);
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $cacheTime));
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', filemtime($cacheFile)));
    header('ETag: "' . md5($fileId) . '"');
    
    // 檢查客戶端快取
    $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? 
        strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
    $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? 
        trim($_SERVER['HTTP_IF_NONE_MATCH']) : false;
        
    if (($ifNoneMatch && $ifNoneMatch === '"' . md5($fileId) . '"') || 
        ($ifModifiedSince && filemtime($cacheFile) <= $ifModifiedSince)) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }

    // 輸出圖片
    echo $imageData;

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log('Image loading error: ' . $e->getMessage());
    exit('Error loading image');
} 