# 結婚式ウェブサイト機能拡張タスクリスト

## 1. ブランチ戦略導入（優先度：高）
- [ ] `feature/branch-strategy` ブランチを作成
- [ ] `.github/workflows/deploy.yml` を修正してステージング環境デプロイを追加
- [ ] `README.md` に開発フローについての説明を追加
- [ ] メインブランチへマージ

## 2. QRコードチェックイン機能（優先度：高）
- [x] `feature/qr-checkin` ブランチを作成
- [x] データベース拡張
  - [x] `guests` テーブルに `qr_code_token` カラムを追加
  - [x] `checkins` テーブルを新規作成（ゲストID、チェックイン時間、メモ等）
- [x] QRコード生成モジュール実装
- [x] reCAPTCHA検証を開発環境でスキップする機能を追加
- [x] ゲスト用マイページにQRコード表示機能追加（`my_qrcode.php`）
- [x] インデックスページにQRコード機能へのリンク追加
- [x] お礼ページにQRコード案内を追加
- [ ] テスト・デバッグ
- [ ] メインブランチへマージ

## 3. デジタルゲストブック拡張（優先度：中）
- [ ] `feature/digital-guestbook` ブランチを作成
- [ ] 写真アップロード機能の追加
- [ ] リアルタイム表示機能の実装
- [ ] 管理画面への統計情報追加
- [ ] メインブランチへマージ

## 4. パーソナライズ通知システム（優先度：中）
- [ ] `feature/personalized-notifications` ブランチを作成
- [ ] タイムライン管理システムの実装
- [ ] ゲスト毎の通知設定ページ
- [ ] プッシュ通知の実装
- [ ] メインブランチへマージ

## 5. ARフォトスポット機能（優先度：低）
- [ ] `feature/ar-photospot` ブランチを作成
- [ ] ARライブラリの選定・導入
- [ ] ARマーカーとコンテンツの作成
- [ ] 専用ページの実装
- [ ] メインブランチへマージ

## 実装済みファイル
- [x] `includes/qr_helper.php` - QRコード生成と管理のためのヘルパー関数
- [x] `admin/checkin.php` - 管理者用QRコードスキャンページ
- [x] `admin/checkin_list.php` - チェックイン履歴管理ページ
- [x] `my_qrcode.php` - ゲストがQRコードを表示するページ
- [x] `create_qr_checkin_tables.sql` - QRチェックイン用テーブル作成SQL

## ブランチ作成・実装開始手順

```bash
# 1. 最新のメインブランチを取得
git checkout main
git pull origin main

# 2. 機能ブランチを作成
git checkout -b feature/qr-checkin

# 3. データベース変更用SQLを作成
cat > create_qr_checkin_tables.sql << 'EOL'
-- ゲストテーブルにQRコードトークン列を追加
ALTER TABLE guests ADD COLUMN qr_code_token VARCHAR(255);

-- チェックインテーブルを作成
CREATE TABLE IF NOT EXISTS checkins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT,
  checkin_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  notes TEXT,
  FOREIGN KEY (guest_id) REFERENCES guests(id)
);
EOL

# 4. QRコード関連ファイルを作成
touch includes/qr_helper.php
touch admin/checkin.php
touch admin/checkin_list.php

# 5. 変更をコミット
git add .
git commit -m "QRコードチェックイン機能の基本構造を追加"
``` 