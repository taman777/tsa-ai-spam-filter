# GTI AI Spam Filter

**WordPress plugin – Extension for [Throws SPAM Away](https://ja.wordpress.org/plugins/throws-spam-away/)**

AI (OpenAI / Google Gemini / Custom API) を利用してコメントのスパム判定を行うプラグインです。  
Throws SPAM Away のフック `tsa_validate_comment` に統合されます。

## Features

- AIベンダー選択: OpenAI / Google Gemini / Custom API
- APIキー・モデル名・タイムアウト秒数を設定画面から管理
- Throws SPAM Away のスパム判定に AI 判定を統合
- ログファイル出力機能（`wp-content/ai-spam-filter.log`）
- 管理画面でログ閲覧可能（最新200行）
- 管理画面からログ削除可能
- `config.cgi` のURL指定による独自アップデート情報取得（`version.json`）

## Requirements

- WordPress 6.0+
- [Throws SPAM Away](https://ja.wordpress.org/plugins/throws-spam-away/) が有効化されていること
- 各ベンダーの API キー

## Installation

1. `wp-content/plugins/` にアップロード
2. 管理画面で有効化
3. Throws SPAM Away が有効な場合、メニューに「AIスパムフィルター」が追加されます
4. API キーやモデル名を入力して保存してください
5. 自動アップデート連携を使う場合は、プラグインディレクトリに `config.cgi` を作成し、1行目に `version.json` のURLを記載してください

## Tested Vendors

- ✅ OpenAI (動作確認済み)
- ✅ Google Gemini (動作確認済み)
- ⚠️ Custom API（未検証）

## Notes

- ログファイルは `wp-content/ai-spam-filter.log` に出力されます。
- 管理画面ではログを1行整形で表示します（最新200行）。
- 自動削除機能はありません。設定画面の「ログ削除」ボタン、または手動で削除してください。
- `config.cgi` が未設定またはURL不正の場合、独自アップデートチェックは無効になります。

## License

GPL v2 or later
