<?php
require __DIR__ . '/config.php';
require __DIR__ . '/firebase.php';

header('Content-Type: application/json');

$firebaseService = new FirebaseService(
    $config['firebase']['credentials_path'],
    $config['firebase']['database_url']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $imageId = $_POST['id'] ?? '';
    
    if (empty($imageId)) {
        echo json_encode(['success' => false, 'message' => '未提供圖片ID']);
        exit;
    }

    switch ($action) {
        case 'update':
            $animeTitle = $_POST['anime_title'] ?? '';
            $episode = $_POST['episode'] ?? '';
            $timestamp = $_POST['timestamp'] ?? '';
            
            $result = $firebaseService->updateImage($imageId, [
                'anime_title' => $animeTitle,
                'episode' => $episode,
                'timestamp' => $timestamp
            ]);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? '更新成功' : '更新失敗'
            ]);
            break;

        case 'delete':
            $result = $firebaseService->deleteImage($imageId);
            echo json_encode([
                'success' => $result,
                'message' => $result ? '刪除成功' : '刪除失敗'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => '無效的操作']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => '無效的請求方法']); 