# 動畫截圖展示網站

這是一個使用 PHP 開發的網站，用於展示存儲在 Google Drive 中的動畫截圖，並使用 Firebase Realtime Database 來管理圖片的元數據。

## 功能特點

1. **圖片展示**
   - 支援分頁顯示（每頁 20 張圖片）
   - 使用 lazy loading 優化載入速度
   - 支援圖片快取
   - 點擊圖片可查看大圖

2. **資料管理**
   - 使用 Firebase Realtime Database 儲存圖片元數據
   - 使用 Google Drive 儲存實際圖片檔案
   - 支援動畫名稱、集數、時間戳等資訊顯示

3. **使用者介面**
   - 響應式網格布局
   - 分頁導航
   - 圖片詳細資訊頁面

## 目錄結構

```
/project-root
│
├── /public
│   ├── index.php       # 主頁面（圖片列表）
│   ├── image.php       # 圖片詳細頁面
│   └── get_image.php   # 圖片讀取處理
│
├── /src
│   ├── config.php           # 配置檔案
│   ├── firebase.php         # Firebase 服務
│   ├── google-drive.php     # Google Drive 服務
│   ├── credentials.json     # Google Drive API 憑證
│   └── serviceAccountKey.json # Firebase Admin SDK 憑證
│
├── /vendor              # Composer 依賴
├── .env                # 環境變數
├── composer.json       # 專案依賴配置
└── README.md          # 說明文件
```

## 安裝步驟

1. **安裝相依套件**
   ```bash
   composer require google/apiclient
   composer require kreait/firebase-php
   composer require vlucas/phpdotenv
   ```

2. **設定 Google Drive API**
   - 在 Google Cloud Console 建立專案
   - 啟用 Google Drive API
   - 建立 OAuth 2.0 憑證
   - 下載憑證並重命名為 `credentials.json`
   - 放置於 `src` 目錄

3. **設定 Firebase**
   - 在 Firebase Console 建立專案
   - 建立 Realtime Database
   - 下載服務帳號金鑰
   - 重命名為 `serviceAccountKey.json`
   - 放置於 `src` 目錄

4. **環境變數設定**
   建立 `.env` 檔案：
   ```env
   GOOGLE_DRIVE_FOLDER_ID="你的Google Drive資料夾ID"
   FIREBASE_DATABASE_URL="你的Firebase資料庫URL"
   APP_ENV=development
   APP_DEBUG=true
   ```

5. **Firebase 資料結構**
   ```json
   {
     "images": {
       "image_id": {
         "anime_title": "動畫名稱",
         "episode": "集數",
         "name": "檔案名稱",
         "path": "Google Drive檔案ID",
         "timestamp": "時間戳",
         "upload_time": "上傳時間"
       }
     }
   }
   ```

## 使用說明

1. **啟動開發伺服器**
   ```bash
   php -S localhost:8000 -t public
   ```

2. **瀏覽網站**
   - 開啟瀏覽器訪問 `http://localhost:8000`
   - 主頁面顯示圖片列表
   - 點擊圖片可查看詳細資訊

## 注意事項

1. **權限設定**
   - 確保 `credentials.json` 和 `serviceAccountKey.json` 檔案權限正確
   - Google Drive 資料夾需設定適當的存取權限

2. **安全性考慮**
   - 不要將憑證檔案提交到版本控制系統
   - 確保 `.env` 檔案不被公開存取
   - 設定適當的 HTTP 快取標頭

3. **效能優化**
   - 使用圖片 lazy loading
   - 實作圖片快取機制
   - 分頁限制每頁顯示數量

## 技術堆疊

- PHP 7.4+
- Google Drive API
- Firebase Realtime Database
- Composer 套件管理
- 環境變數管理

## 開發者

- 建立時間：2024年
- 版本：1.0.0

## 授權

本專案採用 MIT 授權條款。