# ナチュラルグリーンテーマ (v3_natural_green)

このテンプレートは、サンプルページ https://sample.weddingday.jp/?design=v3_natural_green を参考にしたシンプルで自然な雰囲気の結婚式Webサイトです。

## 特徴

- ナチュラルで落ち着いたグリーンをベースにしたデザイン
- シンプルで読みやすいレイアウト
- 伝統的な結婚式の招待状をモチーフにした文面
- レスポンシブデザイン（モバイル対応）

## 使い方

1. `templates/v3_natural_green/index.php` をブラウザで表示するには、以下のURLにアクセスします:
   ```
   https://あなたのドメイン/templates/v3_natural_green/index.php
   ```

2. グループIDを指定してゲスト別の情報を表示する場合:
   ```
   https://あなたのドメイン/templates/v3_natural_green/index.php?group=グループID
   ```

## カスタマイズ方法

カスタマイズは主に以下の2つの方法で行えます：

### 1. データベース設定

以下のデータベーステーブルにデータを設定することでカスタマイズできます：

- `wedding_settings`: 結婚式の基本情報（日付、時間、会場など）
  - `groom_name`, `bride_name`: 新郎新婦の名前（ローマ字）
  - `groom_name_ja`, `bride_name_ja`: 新郎新婦の名前（日本語）
  - `wedding_date`: 結婚式の日付（例: 2025年11月22日）
  - `ceremony_time`: 挙式の開始時間
  - `reception_time`: 披露宴の開始時間
  - `venue_name`: 会場名
  - `venue_address`: 会場の住所
  - `venue_map_url`: Googleマップの埋め込みURL
  - `venue_map_link`: Googleマップへのリンク

### 2. ファイル編集

- `index.php`: メインのHTMLとPHPロジック
- `style.css`: スタイルシート（インラインCSSを外部化する場合に使用）

## トラブルシューティング

- 画像が表示されない場合は、`../../images/` パスが正しいか確認してください
- データベースに接続できない場合は、`../../config.php` が正しく設定されているか確認してください

## 注意事項

- このテンプレートは、メインの結婚式Webサイト機能をベースにしていますが、デザインとレイアウトをシンプル化しています
- 付箋機能、ゲストブック、写真ギャラリーなどの詳細な機能は含まれていません。必要に応じてメインのindex.phpから機能を移植してください。

## クレジット

デザインインスピレーション: sample.weddingday.jp/?design=v3_natural_green 