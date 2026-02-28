=== GTI AI Spam Filter ===
Contributors: gti-inc
Tags: spam, ai, openai, gemini, throws-spam-away
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Throws SPAM Away と連携し、コメントを AI (OpenAI / Google Gemini / Custom API) でスパム判定します。

== Description ==

GTI AI Spam Filter は、WordPress プラグイン「Throws SPAM Away」の拡張です。  
コメント投稿時に外部 AI を利用してスパム判定を行い、判定結果に応じてブロックします。

* AIベンダーを選択可能（OpenAI / Google Gemini / Custom API）
* APIキー、モデル名、タイムアウト秒数などを設定画面から指定可能
* Throws SPAM Away のスパム判定フック `tsa_validate_comment` に連携
* ログファイル出力機能（wp-content/ai-spam-filter.log）
* 管理画面でログ閲覧可能（最新200行）
* ログ削除ボタンを管理画面から実行可能
* `config.cgi` に指定した URL の `version.json` から更新情報を取得可能

== Installation ==

1. プラグインを `wp-content/plugins/` にアップロード
2. WordPress 管理画面から有効化
3. Throws SPAM Away が有効化されている状態で、設定画面に「AIスパムフィルター」が追加されます
4. 各ベンダーの API キーやモデルを設定してください
5. 独自アップデートを利用する場合、プラグイン直下に `config.cgi` を作成し、1行目に `version.json` のURLを記載してください

== Frequently Asked Questions ==

= Throws SPAM Away がないと動作しますか？ =
いいえ。このプラグインは Throws SPAM Away の拡張機能です。有効化されていない場合はエラーメッセージを表示します。

= どの AI が利用可能ですか？ =
OpenAI、Google Gemini、または独自の Custom API を利用できます。  
現時点では **OpenAI と Google Gemini で動作確認済み** です。Custom API は未検証です。

= ログファイルはどう管理しますか？ =
`wp-content/ai-spam-filter.log` に出力されます。  
設定画面で最新200行を閲覧できます。  
「設定画面 → ログ削除」ボタンで削除可能です。  
自動ローテーション機能は未実装なので必要に応じて削除してください。

= アップデート情報はどこから取得しますか？ =
プラグイン直下の `config.cgi` 先頭行に設定した `version.json` のURLから取得します。  
`config.cgi` がない、またはURLが不正な場合は独自アップデート連携は無効です。

== Changelog ==

= 1.8.0 =
* AI判定を Throws SPAM Away の `tsa_validate_comment` フックに統合
* 管理画面にログ表示（最新200行）を追加
* ログ表示を1行整形に改善
* ログ削除ボタンを設定画面に追加
* `config.cgi` 経由の `version.json` 取得による独自アップデートチェックを追加
* OpenAI と Google Gemini での動作確認を反映

= 1.6.1 =
* 初回リリース

== Upgrade Notice ==

= 1.8.0 =
Throws SPAM Away 連携の AI スパム判定に加え、管理画面ログ表示と `config.cgi` ベースの独自アップデートチェックを追加。
