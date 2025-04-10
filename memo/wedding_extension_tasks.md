# 結婚式ウェブサイト機能拡張タスクリスト

## 実装状況の概要
- [x] QRコードチェックイン機能：90%完了（テスト・マージ待ち）
- [ ] チェックイン後の案内表示機能：未着手（優先度：最高）
- [ ] 管理者用リアルタイムダッシュボード：未着手（優先度：最高）
- [ ] ブランチ戦略導入：未着手（優先度：低）
- [ ] デジタルゲストブック拡張：未着手（優先度：低） 
- [ ] パーソナライズ通知システム：未着手（優先度：低）
- [ ] ARフォトスポット機能：未着手（優先度：最低）

## 1. QRコードチェックイン機能（優先度：高）- 進行中
- [x] `feature/qr-checkin` ブランチを作成
- [x] データベース拡張
  - [x] `guests` テーブルに `qr_code_token` カラムを追加
  - [x] `checkins` テーブルを新規作成（ゲストID、チェックイン時間、メモ等）
- [x] QRコード生成モジュール実装 (`includes/qr_helper.php`)
- [x] reCAPTCHA検証を開発環境でスキップする機能を追加
- [x] ゲスト用マイページにQRコード表示機能追加 (`my_qrcode.php`)
- [x] インデックスページにQRコード機能へのリンク追加
- [x] お礼ページにQRコード案内を追加
- [x] 管理者用チェックインページ実装 (`admin/checkin.php`)
- [x] チェックイン履歴管理ページ実装 (`admin/checkin_list.php`)
- [x] カメラアクセスの問題修正
  - [x] QRコードスキャナーライブラリをローカルに保存 (`admin/js/html5-qrcode.min.js`)
  - [x] カメラ初期化プロセスの改善
  - [x] エラー処理・ユーザーガイダンスの追加
- [x] チェックイン履歴表示のSQLクエリ修正
- [x] タイムゾーンを日本時間（JST）に設定
- [ ] テスト・最終確認
- [ ] メインブランチへマージ

## 2. チェックイン後の案内表示機能（優先度：最高）- 新機能
- [ ] `feature/checkin-guidance` ブランチを作成
- [ ] データベース拡張
  - [ ] `seating_guidance` テーブルを作成（ゲストの席次情報保存用）
  - [ ] `event_notices` テーブルを作成（イベント関連の案内情報用）
- [ ] チェックイン直後の案内画面実装
  - [ ] `checkin_complete.php` 作成：チェックイン後のリダイレクト先
  - [ ] 席次情報表示機能（テーブル番号、座席番号）
  - [ ] 会場案内マップ表示機能（階数、部屋、トイレ位置など）
  - [ ] 特別注意事項表示機能（食物アレルギー、介助が必要なゲストへの案内など）
  - [ ] 時間スケジュール表示（受付時間、開宴時間、写真撮影時間など）
- [ ] 管理者用設定ページ実装
  - [ ] `admin/guidance_settings.php` 作成：案内情報の管理ページ
  - [ ] 席次情報一括登録機能（CSVインポート対応）
  - [ ] 会場マップのアップロード機能（画像と説明文の登録）
  - [ ] イベントスケジュールの設定機能
  - [ ] ゲスト属性ごとの案内文カスタマイズ機能
- [ ] スマートフォン表示の最適化
  - [ ] レスポンシブデザインの実装
  - [ ] タッチ操作に最適化された UI
  - [ ] 拡大可能なマップ表示
- [ ] テスト・デバッグ
- [ ] メインブランチへマージ

### 本番環境デプロイ準備（チェックイン後の案内表示機能）
1. データベース変更の適用
   ```sql
   -- 席次案内テーブル作成
   CREATE TABLE IF NOT EXISTS seating_guidance (
     id INT AUTO_INCREMENT PRIMARY KEY,
     guest_id INT NOT NULL,
     table_id INT,
     seat_number INT,
     custom_message TEXT,
     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
     updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
     FOREIGN KEY (table_id) REFERENCES seating_tables(id) ON DELETE SET NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   
   -- イベント案内テーブル作成
   CREATE TABLE IF NOT EXISTS event_notices (
     id INT AUTO_INCREMENT PRIMARY KEY,
     title VARCHAR(100) NOT NULL,
     content TEXT NOT NULL,
     priority INT DEFAULT 0,
     active TINYINT(1) DEFAULT 1,
     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
     updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   -- 会場マップテーブル作成
   CREATE TABLE IF NOT EXISTS venue_maps (
     id INT AUTO_INCREMENT PRIMARY KEY, 
     name VARCHAR(100) NOT NULL,
     description TEXT,
     image_path VARCHAR(255),
     is_default TINYINT(1) DEFAULT 0,
     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
     updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   -- イベントスケジュールテーブル作成
   CREATE TABLE IF NOT EXISTS event_schedule (
     id INT AUTO_INCREMENT PRIMARY KEY,
     event_name VARCHAR(100) NOT NULL,
     event_time DATETIME NOT NULL,
     description TEXT,
     location VARCHAR(100),
     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
     updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```

### 実装アプローチ（チェックイン後の案内表示機能）
1. `admin/checkin.php` の修正
   - チェックイン処理成功後、`checkin_complete.php?token=[token]` にリダイレクト

2. `checkin_complete.php` の新規作成
   ```php
   <?php
   // 設定の読み込み
   require_once 'config.php';
   require_once 'includes/qr_helper.php';

   // トークンの取得と検証
   $token = isset($_GET['token']) ? $_GET['token'] : '';
   $guest_info = null;

   if ($token) {
       $guest_info = getGuestInfoByToken($pdo, $token);
   }

   // 席次情報の取得
   $seating_info = null;
   if ($guest_info) {
       $stmt = $pdo->prepare("SELECT * FROM seating_guidance WHERE guest_id = ?");
       $stmt->execute([$guest_info['id']]);
       $seating_info = $stmt->fetch(PDO::FETCH_ASSOC);
   }

   // イベント案内の取得
   $event_notices = [];
   $stmt = $pdo->prepare("SELECT * FROM event_notices WHERE active = 1 ORDER BY priority DESC");
   $stmt->execute();
   $event_notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

   // 会場マップの取得
   $venue_map = null;
   $stmt = $pdo->prepare("SELECT * FROM venue_maps WHERE is_default = 1 LIMIT 1");
   $stmt->execute();
   $venue_map = $stmt->fetch(PDO::FETCH_ASSOC);

   // イベントスケジュールの取得
   $event_schedule = [];
   $stmt = $pdo->prepare("SELECT * FROM event_schedule ORDER BY event_time");
   $stmt->execute();
   $event_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

   // HTML/CSS レイアウト...
   ?>
   ```

3. 管理者設定ページの実装
   - `admin/guidance_settings.php` でCSVインポート、マップアップロード機能

## 3. 管理者用リアルタイムダッシュボード（優先度：最高）- 新機能
- [ ] `feature/realtime-dashboard` ブランチを作成
- [ ] チェックイン状況のリアルタイム表示
  - [ ] `admin/dashboard.php` 作成：リアルタイムダッシュボードページ
  - [ ] `admin/api/dashboard_data.php` 作成：JSONデータを返すAPI
  - [ ] 現在のチェックイン数/割合のリアルタイム表示
  - [ ] 時間帯別チェックイン数グラフ（Chart.js使用）
  - [ ] 重要ゲストのステータス表示（VIPゲスト優先表示）
  - [ ] 未チェックインの重要ゲストリスト
- [ ] 管理者向け通知機能
  - [ ] ブラウザプッシュ通知の実装（特定ゲストのチェックイン時）
  - [ ] チェックイン率閾値到達時の通知
  - [ ] 通知設定画面の実装
- [ ] 統計データ表示の強化
  - [ ] グループ別チェックイン状況（円グラフ）
  - [ ] 時系列チェックイン進捗グラフ
  - [ ] 席次表との連携表示（テーブル別チェックイン状況）
  - [ ] CSVエクスポート機能
- [ ] モバイル対応
  - [ ] レスポンシブデザイン
  - [ ] タッチ操作最適化
  - [ ] スワイプナビゲーション
- [ ] リアルタイム更新の実装
  - [ ] JavaScript Fetch APIによるポーリング
  - [ ] データキャッシュ機構
- [ ] テスト・デバッグ
- [ ] メインブランチへマージ

### 開発アプローチ（管理者用リアルタイムダッシュボード）
1. フロントエンド：
   - Chart.jsを使用したグラフ表示
   - JavaScriptのsetIntervalによる定期的なデータ更新（10秒ごと）
   - レスポンシブデザインでモバイル対応
   - 以下のデータエンドポイントを使用:
     ```javascript
     // ダッシュボード更新関数
     function updateDashboard() {
       fetch('api/dashboard_data.php')
         .then(response => response.json())
         .then(data => {
           updateCounters(data.counts);
           updateCharts(data.charts);
           updateGuestList(data.guests);
           checkNotifications(data.notifications);
         })
         .catch(error => console.error('Error fetching dashboard data:', error));
     }
     
     // 10秒ごとに更新
     setInterval(updateDashboard, 10000);
     ```

2. バックエンド：
   - リアルタイムデータを返すAPIエンドポイント作成
   ```php
   <?php
   // api/dashboard_data.php
   header('Content-Type: application/json');
   require_once '../../config.php';
   
   // 管理者認証チェック
   session_start();
   if (!isset($_SESSION['admin_id'])) {
       http_response_code(401);
       echo json_encode(['error' => 'Unauthorized']);
       exit;
   }
   
   // チェックイン統計データ取得
   function getCheckinStats($pdo) {
       // ここに統計データを取得するSQLクエリを実装
   }
   
   // 直近のチェックイン取得
   function getRecentCheckins($pdo, $limit = 10) {
       // ここに最新チェックインデータを取得するSQLクエリを実装
   }
   
   // 重要ゲスト状況取得
   function getVipGuestStatus($pdo) {
       // ここにVIPゲスト情報を取得するSQLクエリを実装
   }
   
   // データをJSONで返す
   $data = [
       'counts' => [
           'total_guests' => $total_count,
           'checked_in' => $checkin_count,
           'percentage' => $percentage
       ],
       'charts' => [
           'hourly' => $hourly_data,
           'groups' => $group_data
       ],
       'guests' => $recent_checkins,
       'vip_status' => $vip_guests,
       'notifications' => $notifications
   ];
   
   echo json_encode($data);
   ?>
   ```

## 4. ブランチ戦略導入（優先度：低）
- [ ] `feature/branch-strategy` ブランチを作成
- [ ] `.github/workflows/deploy.yml` を修正してステージング環境デプロイを追加
- [ ] `README.md` に開発フローについての説明を追加
- [ ] メインブランチへマージ

## 5. デジタルゲストブック拡張（優先度：低）
- [ ] `feature/digital-guestbook` ブランチを作成
- [ ] 写真アップロード機能の追加
- [ ] リアルタイム表示機能の実装
- [ ] 管理画面への統計情報追加
- [ ] メインブランチへマージ

## 6. パーソナライズ通知システム（優先度：低）
- [ ] `feature/personalized-notifications` ブランチを作成
- [ ] タイムライン管理システムの実装
- [ ] ゲスト毎の通知設定ページ
- [ ] プッシュ通知の実装
- [ ] メインブランチへマージ

## 7. ARフォトスポット機能（優先度：最低）
- [ ] `feature/ar-photospot` ブランチを作成
- [ ] ARライブラリの選定・導入
- [ ] ARマーカーとコンテンツの作成
- [ ] 専用ページの実装
- [ ] メインブランチへマージ

## 本番環境データベース設定

### 本番環境へのデータベース構造反映方法

#### 方法1: SQLエクスポート・インポート
1. ローカル環境でSQLエクスポート
   ```bash
   # XAMPPのツールを使用してエクスポート
   /Applications/XAMPP/xamppfiles/bin/mysqldump -u root wedding > wedding_db_backup.sql
   ```

2. 本番サーバーにSQLファイルをアップロード

3. 本番環境でインポート
   ```bash
   # 既存のデータベースがある場合は事前にバックアップを推奨
   mysql -u [本番ユーザー名] -p [本番DB名] < wedding_db_backup.sql
   ```

#### 方法2: 個別のテーブル変更スクリプト作成
上記の「本番環境デプロイ準備」セクションのSQLスクリプトを使用

#### 注意点
- 本番環境のデータを常にバックアップしておく
- 可能であれば、まず本番環境そっくりのステージング環境でテストする
- 重要なデータ更新は、メンテナンス時間を設けて実施する
- データベースのパスワードは強力なものを使用する

### 環境別の設定管理
`.env`ファイルを環境ごとに用意し、本番環境では適切な値に設定:
```
# 一般設定
APP_ENV=production  # 開発環境は "development"
APP_URL=https://www.example.com/wedding

# データベース設定
DB_HOST=localhost
DB_USER=production_user
DB_PASS=secure_password
DB_NAME=wedding_production

# メール設定
MAIL_HOST=smtp.example.com
MAIL_USER=notification@example.com
MAIL_PASS=mail_password
MAIL_PORT=587
MAIL_ENCRYPTION=tls
```

## 開発の注意点
1. 各機能は個別のブランチで開発
2. プルリクエスト作成前にステージング環境でテスト
3. コードレビュー後にmainブランチへマージ
4. デプロイ後の動作確認を必ず実施

## 新機能実装時の基本手順

```bash
# 1. 最新のメインブランチを取得
git checkout main
git pull origin main

# 2. 機能ブランチを作成
git checkout -b feature/[機能名]

# 3. 開発・変更を実施

# 4. 変更をコミット
git add .
git commit -m "[機能名]の基本実装"

# 5. リモートへプッシュ
git push origin feature/[機能名]

# 6. GitHub上でプルリクエスト作成
# 7. レビュー後、マージ
``` 