# aite

AIに聞いて、貼るだけ。

aiteは、アカウント不要で使える超軽量の日程調整サービスです。Google Calendar連携は行わず、候補日時を作成し、参加者がAIに予定確認用プロンプトを渡して返ってきたJSONを貼り付けることで、○△×を一括入力できます。

GitHub: [TetsuakiBaba/aite](https://github.com/TetsuakiBaba/aite)

## 動作環境

- PHP 8.x
- SQLite PDO拡張
- JavaScriptが有効なブラウザ

外部ライブラリ、npm、Composerは不要です。

## 設置方法

1. このディレクトリをPHPが動くWebサーバへ配置します。
2. `data/` ディレクトリを書き込み可能にします。
3. ブラウザで `index.php` を開きます。

`data/aite.sqlite` が存在しない場合は、初回アクセス時に自動作成されます。

## 権限設定

Webサーバの実行ユーザーが以下を書き込める必要があります。

```sh
chmod 775 data
```

`data/` が存在しない場合もアプリ側で作成を試みますが、本番環境では事前に作成して権限を設定しておくと確実です。

## GitHub Actionsでのデプロイ

`.github/workflows/deploy.yml` はFTPで任意のWebサーバへデプロイします。GitHubリポジトリの `Settings` → `Secrets and variables` → `Actions` に以下のSecretsを登録してください。

- `FTP_SERVER`: FTPサーバーのホスト名
- `FTP_USERNAME`: FTPユーザー名
- `FTP_PASSWORD`: FTPパスワード
- `FTP_SERVER_DIR`: デプロイ先ディレクトリ。未設定時は `./`。末尾の `/` は省略できます。
- `FTP_PORT`: FTPポート。未設定時は `21`
- `FTP_PROTOCOL`: `ftp` または `ftps`。未設定時は `ftp`

`main` ブランチへのpushで自動デプロイされます。Actions画面から手動実行もでき、`dry_run` を有効にするとサーバを書き換えずに転送対象だけ確認できます。

デプロイ対象から `data/*.sqlite` と `data/admin_token.txt` は除外しています。DBと管理者トークンはサーバ側で保持してください。Apache環境では `data/.htaccess` により `data/` への直アクセスを拒否します。Nginxなど `.htaccess` が効かない環境では、サーバ設定で `data/` へのHTTPアクセスを拒否してください。

## 使い方

1. トップページから「イベントを作成する」を押します。
2. イベント名、説明、候補日時を入力します。
3. 月カレンダーで日付を選び、タイムラインをドラッグして時間帯を作ります。
4. 必要に応じて「手入力モード」で `2026-07-01 13:00-14:00` のように直接入力します。
5. 保存すると回答URLと管理URLが発行されます。
6. 回答者は回答URLから名前と編集用パスワードを入力し、作成者が示した時間範囲の中で参加可能な範囲をドラッグして保存します。初期状態では、名前を入力すると同じ値が編集用パスワードに入ります。
7. 後から編集する場合は、同じ名前と編集用パスワードを入力して「前回回答を読み込む」を押し、修正して保存します。
8. 管理URLでは集計、回答一覧、CSVダウンロードを確認できます。

## AI回答機能

回答画面の「AIに聞く用プロンプトをコピー」を押すと、候補日時と `slot_id` を含むプロンプトがクリップボードにコピーされます。

AIにはJSONだけを返すよう依頼します。回答画面では、参加可能な時間帯を `ok_ranges`、候補時間内に入っている予定を `busy_events` として貼り付けられます。

```json
[
  {
    "slot_id":"slot_xxx",
    "ok_ranges":[{"start":"13:30","end":"15:00"}],
    "busy_events":[{"title":"定例MTG","start":"13:00","end":"13:30"}]
  },
  {
    "slot_id":"slot_yyy",
    "ok_ranges":[],
    "busy_events":[{"title":"外出","start":"10:00","end":"12:00"}]
  }
]
```

返答JSONを textarea に貼り付けると、有効なJSONであれば該当する候補のOK範囲が自動選択され、AIが確認した予定名と時間も画面に表示されます。予定名つきの `busy_events` は確認用で、回答保存時には保存されません。

## ファイル構成

```text
/
  index.php
  create.php
  event.php
  admin.php
  api.php
  app.js
  style.css
  assets/
    aite-icon.svg
    aite-ogp.svg
  data/
    aite.sqlite
    admin_token.txt
    .htaccess
  .github/workflows/
    deploy.yml
  README.md
```
