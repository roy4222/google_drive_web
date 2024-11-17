<?php
require __DIR__ . '/../vendor/autoload.php';

$config = include(__DIR__ . '/../src/config.php');
require __DIR__ . '/../src/firebase.php';
require __DIR__ . '/../src/google-drive.php';

// 獲取搜尋和排序參數
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'title';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// 初始化服務
$firebaseService = new FirebaseService(
    $config['firebase']['credentials_path'],
    $config['firebase']['database_url']
);

// 獲取所有圖片
$allImages = $firebaseService->getImages();

// 按動漫名稱分組
$categories = [];
foreach ($allImages as $image) {
    $animeTitle = $image['anime_title'];
    if (!isset($categories[$animeTitle])) {
        $categories[$animeTitle] = [
            'title' => $animeTitle,
            'count' => 0,
            'thumbnail' => $image['path'],
            'latest_time' => $image['upload_time']
        ];
    }
    $categories[$animeTitle]['count']++;
    if ($image['upload_time'] > $categories[$animeTitle]['latest_time']) {
        $categories[$animeTitle]['latest_time'] = $image['upload_time'];
        $categories[$animeTitle]['thumbnail'] = $image['path'];
    }
}

// 搜尋過濾
if ($search !== '') {
    $categories = array_filter($categories, function($category) use ($search) {
        return stripos($category['title'], $search) !== false;
    });
}

// 排序
usort($categories, function($a, $b) use ($sort, $order) {
    $result = 0;
    switch ($sort) {
        case 'count':
            $result = $b['count'] - $a['count'];
            break;
        case 'latest':
            $result = strcmp($b['latest_time'], $a['latest_time']);
            break;
        case 'title':
        default:
            $result = strcmp($a['title'], $b['title']);
            break;
    }
    return $order === 'desc' ? -$result : $result;
});
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>動畫分類</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Microsoft JhengHei', sans-serif;
            background: rgb(36, 46, 54);
            color: #ffffff;
            line-height: 1.6;
            min-height: 100vh;
            padding: 3rem 0 50px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 25px;
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            animation: fadeIn 0.6s ease-out forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .category-card {
            background: rgb(46, 56, 64);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            position: relative;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ffd700, #ffb700);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }

        .category-card:hover::before {
            opacity: 1;
        }

        .category-thumbnail {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: #f0f0f0;
            transition: transform 0.3s ease;
        }

        .category-card:hover .category-thumbnail {
            transform: scale(1.05);
        }

        .category-info {
            padding: 20px;
            background: linear-gradient(180deg, rgba(46, 56, 64, 0) 0%, rgb(46, 56, 64) 100%);
        }

        .category-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #ffffff;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 8px;
        }

        .category-count {
            color: #ffffff;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .category-count::before {
            content: '•';
            color: #ffd700;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .category-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
            }
        }

        /* 新增搜尋和排序表單樣式 */
        .search-sort-container {
            max-width: 1400px;
            margin: 0 auto 2rem;
            padding: 0 25px;
        }

        .search-sort-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            background: rgb(46, 56, 64);
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            font-size: 1rem;
            background: rgb(36, 46, 54);
            color: #ffffff;
        }

        .search-input:focus {
            border-color: #ffd700;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }

        .sort-select {
            padding: 10px 15px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            font-size: 1rem;
            background: rgb(36, 46, 54);
            color: #ffffff;
            cursor: pointer;
            min-width: 150px;
        }

        .sort-select:focus {
            border-color: #ffd700;
            outline: none;
        }

        .search-button {
            padding: 10px 20px;
            background: linear-gradient(120deg, #ffd700 0%, #ffb700 100%);
            color: rgb(36, 46, 54);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .search-sort-form {
                flex-direction: column;
                gap: 10px;
            }

            .search-input, .sort-select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- 添加搜尋和排序表單 -->
    <div class="search-sort-container">
        <form class="search-sort-form" method="GET">
            <input type="text" 
                   name="search" 
                   class="search-input" 
                   placeholder="搜尋動畫名稱..."
                   value="<?php echo htmlspecialchars($search); ?>">
            
            <select name="sort" class="sort-select">
                <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>
                    依名稱
                </option>
                <option value="count" <?php echo $sort === 'count' ? 'selected' : ''; ?>>
                    依截圖數量
                </option>
                <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>
                    依最新更新
                </option>
            </select>
            
            <select name="order" class="sort-select">
                <option value="asc" <?php echo $order === 'asc' ? 'selected' : ''; ?>>
                    升序
                </option>
                <option value="desc" <?php echo $order === 'desc' ? 'selected' : ''; ?>>
                    降序
                </option>
            </select>
            
            <button type="submit" class="search-button">搜尋</button>
        </form>
    </div>

    <div class="container">
        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <a href="index.php?search=<?php echo urlencode($category['title']); ?>" class="category-card">
                    <img src="get_image.php?id=<?php echo urlencode($category['thumbnail']); ?>" 
                         alt="<?php echo htmlspecialchars($category['title']); ?>"
                         class="category-thumbnail"
                         loading="lazy"
                         decoding="async">
                    <div class="category-info">
                        <div class="category-title"><?php echo htmlspecialchars($category['title']); ?></div>
                        <div class="category-count"><?php echo $category['count']; ?> 張截圖</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html> 