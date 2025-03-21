# GitHub アップロードメモ

## プロジェクト概要
ウェディング招待状のウェブサイトプロジェクト。招待客が閲覧してRSVPできるウェブサイトです。

## アップロードするファイル/ディレクトリ
以下のファイルとディレクトリをアップロードします：
- PHPファイル（index.php, process_rsvp.php, guestbook.php など）
- SQLファイル（データベース構造定義）
- cssディレクトリ（スタイルシート）
- jsディレクトリ（JavaScript）
- imagesディレクトリ（画像）
- videosディレクトリ（動画）
- soundsディレクトリ（音声）
- includesディレクトリ（共通コード）
- その他の設定ファイル

## アップロード手順
1. GitHubリポジトリ「sopranohaction」を作成済み
2. ローカルプロジェクトディレクトリでGitを初期化
   ```
   git init
   ```
3. すべてのファイルをステージングエリアに追加
   ```
   git add .
   ```
4. 最初のコミットを作成
   ```
   git commit -m "最初のコミット"
   ```
5. ブランチ名をmainに設定
   ```
   git branch -M main
   ```
6. GitHubリポジトリとの接続を設定
   ```
   git remote add origin https://github.com/syou51158/sopranohaction.git
   ```
7. ローカルの変更をGitHubにプッシュ
   ```
   git push -u origin main
   ```

## 注意点
- このプロジェクトはテスト開発段階のため、データベース認証情報等があっても現時点では問題なし
- 今後実運用に移行する際は、config.phpなどの機密情報を.gitignoreに追加することを検討する
- 大きなファイル（動画・画像等）も含めてアップロードする 