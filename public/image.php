<?php
require __DIR__ . '/../vendor/autoload.php';

$config = include(__DIR__ . '/../src/config.php');
require __DIR__ . '/../src/firebase.php';
require __DIR__ . '/../src/google-drive.php';

$imageId = $_GET['id'] ?? null;
if (!$imageId) {
    header('Location: index.php');
    exit;
}

// 初始化服務
$firebaseService = new FirebaseService(
    $config['firebase']['credentials_path'],
    $config['firebase']['database_url']
);

$googleDriveService = new GoogleDriveService(
    $config['google_drive']['credentials_path'],
    $config['google_drive']['folder_id']
);

// 獲取圖片資訊
$image = $firebaseService->getImageById($imageId);
if (!$image) {
    header('Location: index.php');
    exit;
}

// 設置頁面快取
header('Cache-Control: public, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($image['anime_title']); ?> - 圖片詳情</title>
    <meta name="description" content="<?php echo htmlspecialchars($image['anime_title'] . ' - ' . $image['episode']); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Microsoft JhengHei', sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 2rem;
            padding: 15px 0;
            border-bottom: 2px solid #eee;
        }

        .image-detail {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .image-detail img {
            width: 100%;
            height: auto;
            display: block;
            background: #f0f0f0;
        }

        .image-info {
            padding: 20px;
        }

        .image-info p {
            margin: 10px 0;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .back-button {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(120deg, #84fab0 0%, #8fd3f4 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($image['anime_title']); ?></h1>
        <div class="image-detail">
            <img src="get_image.php?id=<?php echo urlencode($image['path']); ?>" 
                 alt="<?php echo htmlspecialchars($image['name']); ?>"
                 loading="lazy"
                 decoding="async">
            <div class="image-info">
                <p><strong>檔案名稱:</strong> <?php echo htmlspecialchars($image['name']); ?></p>
                <p><strong>集數:</strong> <?php echo htmlspecialchars($image['episode']); ?></p>
                <p><strong>時間戳:</strong> <?php echo htmlspecialchars($image['timestamp']); ?></p>
                <p><strong>上傳時間:</strong> <?php echo htmlspecialchars($image['upload_time']); ?></p>
            </div>
        </div>
        <a href="index.php" class="back-button">返回列表</a>
    </div>
</body>
</html> 