<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';

$config = include(__DIR__ . '/../src/config.php');
require __DIR__ . '/../src/firebase.php';
require __DIR__ . '/../src/google-drive.php';

// 分頁設定
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// 添加排序和搜尋參數
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'random';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// 初始化服務
$firebaseService = new FirebaseService(
    $config['firebase']['credentials_path'],
    $config['firebase']['database_url']
);

$googleDriveService = new GoogleDriveService(
    $config['google_drive']['credentials_path'],
    $config['google_drive']['folder_id']
);

// 獲取圖片資料
try {
    $allImages = $firebaseService->getImages();
    $originalImages = $allImages; // 保存原始數據
} catch (Exception $e) {
    $allImages = [];
    $originalImages = [];
    echo '<div class="error-message">載入圖片資料時發生錯誤: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// 首先初始化隨機排序（如果需要）
if ($sort === 'random' && (!isset($_SESSION['random_order']) || isset($_GET['new_random']))) {
    // 使用原始圖片數組生成隨機順序
    $imageKeys = array_keys($originalImages); // 使用原始數據的鍵
    shuffle($imageKeys);
    $_SESSION['random_order'] = $imageKeys;
    
    if (isset($_GET['new_random'])) {
        // 重定向到第一頁以移除 new_random 參數，保持搜尋條件
        header("Location: ?page=1&sort=random" . ($search ? "&search=" . urlencode($search) : ""));
        exit;
    }
}

// 然後進行搜尋過濾
if ($search !== '') {
    $allImages = array_filter($allImages, function($image) use ($search) {
        $animeTitle = isset($image['anime_title']) ? $image['anime_title'] : '';
        $episode = isset($image['episode']) ? $image['episode'] : '';
        
        return (stripos($animeTitle, $search) !== false ||
                stripos($episode, $search) !== false);
    });
}

// 最後應用排序
if ($sort === 'random') {
    if (isset($_SESSION['random_order'])) {
        // 使用保存的隨機順序重新排列圖片，但只包含搜尋後的結果
        $randomImages = [];
        foreach ($_SESSION['random_order'] as $key) {
            if (isset($allImages[$key])) {  // 確保只包含搜尋結果中的圖片
                $randomImages[$key] = $allImages[$key];
            }
        }
        $allImages = $randomImages;
    }
} else {
    // 不是隨機排序時清除隨機排序的session
    if (isset($_SESSION['random_order'])) {
        unset($_SESSION['random_order']);
    }
    
    // 進行其他排序
    usort($allImages, function($a, $b) use ($sort, $order) {
        $result = 0;
        switch ($sort) {
            case 'anime_title':
                $titleA = isset($a['anime_title']) ? $a['anime_title'] : '';
                $titleB = isset($b['anime_title']) ? $b['anime_title'] : '';
                $result = strcmp($titleA, $titleB);
                break;
            case 'episode':
                $episodeA = isset($a['episode']) ? $a['episode'] : '';
                $episodeB = isset($b['episode']) ? $b['episode'] : '';
                $result = strcmp($episodeA, $episodeB);
                break;
            case 'upload_time':
            default:
                $timeA = isset($a['upload_time']) ? $a['upload_time'] : '';
                $timeB = isset($b['upload_time']) ? $b['upload_time'] : '';
                $result = strcmp($timeA, $timeB);
                break;
        }
        return $order === 'desc' ? -$result : $result;
    });
}

// 分頁處理
$totalImages = count($allImages);
$totalPages = ceil($totalImages / $perPage);
$page = min($page, $totalPages);
$images = array_slice($allImages, ($page - 1) * $perPage, $perPage, true);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>動畫截圖展示</title>
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
        }

        h1 {
            text-align: center;
            padding: 2.5rem 0;
            color: rgb(36, 46, 54);
            font-size: 2.4rem;
            background: linear-gradient(120deg, #ffd700 0%, #ffb700 100%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
            color: white;
            text-shadow: none;
        }

        h1::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            to {
                left: 100%;
            }
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            padding: 0 25px;
            max-width: 1500px;
            margin: 0 auto;
        }

        .image-card {
            background: rgb(46, 56, 64);
            min-height: 350px;
            border-radius: 16px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            contain: content;
            position: relative;
        }

        .image-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 30px rgba(0,0,0,0.12);
        }

        .image-card::before {
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

        .image-card:hover::before {
            opacity: 1;
        }

        .image-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            transition: transform 0.3s ease, opacity 0.3s ease;
            background-color: #f0f0f0;
            opacity: 0;
        }

        .image-card img.loaded {
            opacity: 1;
        }

        .image-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 220px;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            animation: loading 1.5s infinite;
        }

        .image-card.loaded::after {
            display: none;
        }

        @keyframes loading {
            to {
                left: 100%;
            }
        }

        .image-info {
            padding: 20px;
            background: linear-gradient(180deg, rgba(46, 56, 64, 0) 0%, rgb(46, 56, 64) 100%);
        }

        .image-info p {
            margin: 8px 0;
            font-size: 1rem;
            position: relative;
            padding-left: 20px;
        }

        .image-info p:before {
            content: '•';
            position: absolute;
            left: 5px;
            color: #ffd700;
            font-weight: bold;
        }

        .image-info p:first-child {
            font-weight: bold;
            color: #ffffff;
            font-size: 1.1rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 40px 20px;
            flex-wrap: wrap;
            margin-top: 3rem;
        }

        .pagination a, .pagination span {
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            color: #ffffff;
            background: rgb(46, 56, 64);
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .pagination .current {
            background: linear-gradient(120deg, #ffd700 0%, #ffb700 100%);
            color: rgb(36, 46, 54);
            font-weight: bold;
        }

        .pagination a:hover {
            background: rgb(56, 66, 74);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .image-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
                padding: 0 15px;
            }

            h1 {
                font-size: 1.8rem;
                padding: 1.8rem 0;
            }

            .pagination a, .pagination span {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
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

        .image-card {
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
        }

        <?php for($i = 0; $i < $perPage; $i++): ?>
        .image-card:nth-child(<?php echo $i+1; ?>) {
            animation-delay: <?php echo $i * 0.1; ?>s;
        }
        <?php endfor; ?>

        /* 燈箱樣式 */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(36, 46, 54, 0.95);
            z-index: 1000;
            cursor: zoom-out;
        }

        .lightbox.active {
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-out;
        }

        .lightbox img {
            max-width: 90%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            cursor: default;
            transform: scale(0.9);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .lightbox.active img {
            transform: scale(1);
            opacity: 1;
        }

        .close-lightbox {
            position: fixed;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 215, 0, 0.2);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-lightbox:hover {
            background: rgba(255, 215, 0, 0.3);
            transform: rotate(90deg);
        }

        /* 搜尋和排序區域樣式 */
        .search-sort-container {
            max-width: 1500px;
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
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
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
            background-color: rgb(36, 46, 54);
            cursor: pointer;
            min-width: 150px;
            color: #ffffff;
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

        .error-message {
            padding: 20px;
            text-align: center;
            color: #ff6b6b;
            background: rgba(255,107,107,0.1);
            border-radius: 8px;
        }
        
        img[src="images/error.jpg"] {
            opacity: 0.5;
            filter: grayscale(100%);
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
            min-width: 200px;
        }

        .search-input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            font-size: 1rem;
            background: rgb(36, 46, 54);
            color: #ffffff;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #ffd700;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }

        .clear-search {
            position: absolute;
            right: 35px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.5);
            cursor: pointer;
            padding: 5px;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .search-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.5);
            cursor: pointer;
            padding: 5px;
            font-size: 14px;
        }

        .clear-search:hover,
        .search-icon:hover {
            color: rgba(255,255,255,0.8);
        }
    </style>

    <?php if ($page < $totalPages): ?>
        <?php 
        $nextPageImages = array_slice($allImages, $page * $perPage, $perPage, true);
        foreach ($nextPageImages as $image): 
        ?>
            <link rel="prefetch" href="get_image.php?id=<?php echo urlencode($image['path']); ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 添加預載入提示 -->
    <?php foreach (array_slice($images, 0, 4) as $image): ?>
        <link rel="preload" as="image" href="get_image.php?id=<?php echo urlencode($image['path']); ?>">
    <?php endforeach; ?>

    <!-- 添加 Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <h1>動畫截圖展示</h1>

    <!-- 修改搜尋和排序表單 -->
    <div class="search-sort-container">
        <form class="search-sort-form" method="GET" id="searchForm">
            <div class="search-input-wrapper">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="搜尋動畫名稱或集數..."
                       value="<?php echo htmlspecialchars($search); ?>"
                       id="searchInput">
                <?php if ($search): ?>
                    <button type="button" 
                            class="clear-search" 
                            onclick="clearSearch()">
                        <i class="fas fa-times"></i>
                    </button>
                <?php endif; ?>
                <button type="submit" class="search-icon">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            
            <select name="sort" class="sort-select" onchange="handleSortChange(this)">
                <option value="random" <?php echo $sort === 'random' ? 'selected' : ''; ?>>
                    隨機排序
                </option>
                <option value="upload_time" <?php echo $sort === 'upload_time' ? 'selected' : ''; ?>>
                    上傳時間
                </option>
                <option value="anime_title" <?php echo $sort === 'anime_title' ? 'selected' : ''; ?>>
                    動畫名稱
                </option>
                <option value="episode" <?php echo $sort === 'episode' ? 'selected' : ''; ?>>
                    集數
                </option>
            </select>
            
            <?php if ($sort === 'random'): ?>
                <input type="hidden" name="order" value="desc">
            <?php else: ?>
                <select name="order" class="sort-select">
                    <option value="desc" <?php echo $order === 'desc' ? 'selected' : ''; ?>>
                        降序
                    </option>
                    <option value="asc" <?php echo $order === 'asc' ? 'selected' : ''; ?>>
                        升序
                    </option>
                </select>
            <?php endif; ?>
            
            <button type="submit" class="search-button">搜尋</button>
            
            <?php if ($sort === 'random'): ?>
                <button type="button" class="search-button" onclick="refreshRandomOrder()">
                    重新隨機
                </button>
            <?php endif; ?>
            
            <?php if ($sort === 'random'): ?>
                <div style="width: 100%; text-align: right; font-size: 0.9rem; color: rgba(255,255,255,0.7);">
                    共 <?php echo count($allImages); ?> 張圖片
                    <?php if ($search): ?>
                        （搜尋結果：<?php echo count($allImages); ?> / <?php echo count($originalImages); ?>）
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="image-grid">
        <?php foreach ($images as $id => $image): ?>
            <div class="image-card">
                <?php if (isset($image['path'])): ?>
                    <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" 
                         data-src="get_image.php?id=<?php echo urlencode($image['path']); ?>" 
                         alt="<?php echo isset($image['name']) ? htmlspecialchars($image['name']) : '命名'; ?>"
                         loading="lazy"
                         decoding="async"
                         width="320"
                         height="200"
                         onerror="this.src='images/error.jpg'"
                         onload="this.classList.add('loaded'); this.parentElement.classList.add('loaded')"
                         onclick="openLightbox(this, 
                            '<?php echo isset($image['anime_title']) ? htmlspecialchars($image['anime_title']) : '未知動畫'; ?>', 
                            '<?php echo isset($image['episode']) ? htmlspecialchars($image['episode']) : '未知集數'; ?>', 
                            '<?php echo isset($image['timestamp']) ? htmlspecialchars($image['timestamp']) : '00:00:00'; ?>')">
                    <div class="image-info">
                        <p>動畫名稱: <?php echo isset($image['anime_title']) ? htmlspecialchars($image['anime_title']) : '未知動畫'; ?></p>
                        <p>集數: <?php echo isset($image['episode']) ? htmlspecialchars($image['episode']) : '未知集數'; ?></p>
                        <p>時間戳: <?php echo isset($image['timestamp']) ? htmlspecialchars($image['timestamp']) : '00:00:00'; ?></p>
                    </div>
                <?php else: ?>
                    <div class="error-message">圖片載入失敗</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">首頁</a>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">上一頁</a>
        <?php endif; ?>

        <?php
        $range = 2;
        $startPage = max(1, $page - $range);
        $endPage = min($totalPages, $page + $range);

        if ($startPage > 1) {
            echo '<span>...</span>';
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $page) {
                echo "<span class=\"current\">$i</span>";
            } else {
                echo "<a href=\"?page=$i&search=" . urlencode($search) . "&sort=" . urlencode($sort) . "&order=" . urlencode($order) . "\">$i</a>";
            }
        }

        if ($endPage < $totalPages) {
            echo '<span>...</span>';
        }
        ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">下一頁</a>
            <a href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>">末頁</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 修改燈箱元素移除資訊框 -->
    <div class="lightbox" onclick="closeLightbox(event)">
        <div class="close-lightbox" onclick="closeLightbox(event)">×</div>
        <img src="" alt="" onclick="event.stopPropagation()">
    </div>

    <!-- 添加 JavaScript -->
    <script>
        function openLightbox(imgElement, animeTitle, episode, timestamp) {
            const lightbox = document.querySelector('.lightbox');
            const lightboxImg = lightbox.querySelector('img');
            
            lightboxImg.src = imgElement.src;
            lightboxImg.alt = imgElement.alt;
            
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox(event) {
            if (event) {
                event.stopPropagation();
            }
            const lightbox = document.querySelector('.lightbox');
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }

        // 添加鍵盤事件監聽
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeLightbox();
            }
        });

        // 添加圖片延遲載入腳本
        document.addEventListener('DOMContentLoaded', function() {
            const lazyImages = document.querySelectorAll('img[data-src]');
            
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            lazyImages.forEach(img => imageObserver.observe(img));
        });

        // 優化圖片快取
        function preloadNextPageImages() {
            const nextPage = <?php echo $page + 1; ?>;
            if (nextPage <= <?php echo $totalPages; ?>) {
                fetch(`?page=${nextPage}`)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const images = doc.querySelectorAll('.image-card img');
                        images.forEach(img => {
                            const preloadLink = document.createElement('link');
                            preloadLink.rel = 'prefetch';
                            preloadLink.href = img.dataset.src;
                            document.head.appendChild(preloadLink);
                        });
                    });
            }
        }

        // 當用戶接近頁面底部時預載入下一頁
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    preloadNextPageImages();
                    observer.disconnect();
                }
            });
        });

        observer.observe(document.querySelector('.pagination'));

        // 優化圖片預載入
        function preloadImages() {
            const images = document.querySelectorAll('img[data-src]');
            const preloadQueue = [];
            
            images.forEach(img => {
                if (isElementInViewport(img)) {
                    preloadQueue.push(img.dataset.src);
                }
            });
            
            preloadQueue.forEach((src, index) => {
                setTimeout(() => {
                    const img = new Image();
                    img.src = src;
                }, index * 100);
            });
        }
        
        function isElementInViewport(el) {
            const rect = el.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        }
        
        window.addEventListener('scroll', debounce(preloadImages, 200));
        window.addEventListener('resize', debounce(preloadImages, 200));
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sortSelect = document.querySelector('select[name="sort"]');
            const orderSelect = document.querySelector('select[name="order"]');
            
            sortSelect.addEventListener('change', function() {
                if (this.value === 'random') {
                    orderSelect.style.display = 'none';
                } else {
                    orderSelect.style.display = '';
                }
            });
        });

        function handleSortChange(select) {
            const orderSelect = document.querySelector('select[name="order"]');
            if (select.value === 'random') {
                orderSelect.style.display = 'none';
                // 清除已有的隨機排序
                if (window.location.href.includes('sort=random')) {
                    // 如果已經是隨機排序，不做任何操作
                    return;
                }
            } else {
                orderSelect.style.display = '';
            }
        }

        // 修改重新隨機的函數
        function refreshRandomOrder() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('new_random', '1');
            currentUrl.searchParams.set('page', '1');  // 重置到第一頁
            window.location.href = currentUrl.toString();
        }

        // 添加表單提交處理
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const params = new URLSearchParams(formData);
            
            // 如果當前是隨機排序，保持隨機排序
            if (this.querySelector('select[name="sort"]').value === 'random') {
                params.set('sort', 'random');
                params.delete('order');  // 移除order參數
            }
            
            // 重置到第一頁
            params.set('page', '1');
            
            window.location.href = '?' + params.toString();
        });

        function clearSearch() {
            const searchInput = document.getElementById('searchInput');
            searchInput.value = '';
            // 自動提交表單
            document.getElementById('searchForm').submit();
        }

        // 添加按下 Esc 鍵清空搜尋
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                clearSearch();
            }
        });
    </script>
</body>
</html> 