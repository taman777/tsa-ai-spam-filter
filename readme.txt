=== GTI AI Spam Filter ===
Contributors: gti-inc
Tags: spam, ai, openai, gemini, throws-spam-away
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.7.1
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
* ログ削除ボタンを管理画面から実行可能

== Installation ==

1. プラグインを `wp-content/plugins/` にアップロード
2. WordPress 管理画面から有効化
3. Throws SPAM Away が有効化されている状態で、設定画面に「AIスパムフィルター」が追加されます
4. 各ベンダーの API キーやモデルを設定してください

== Frequently Asked Questions ==

= Throws SPAM Away がないと動作しますか？ =
いいえ。このプラグインは Throws SPAM Away の拡張機能です。有効化されていない場合はエラーメッセージを表示します。

= どの AI が利用可能ですか？ =
OpenAI、Google Gemini、または独自の Custom API を利用できます。  
現時点では **OpenAI と Google Gemini で動作確認済み** です。Custom API は未検証です。

= ログファイルはどう管理しますか？ =
`wp-content/ai-spam-filter.log` に出力されます。現在のところ、自動ローテーション機能はありません。  
必要に応じて「設定画面 → ログ削除」ボタン、または手動で削除してください。

== Changelog ==

= 1.7.1 =
* AI判定を Throws SPAM Away の `tsa_validate_comment` フックに統合
* ログ削除ボタンを設定画面に追加
* OpenAI と Google Gemini での動作確認を反映

= 1.6.1 =
* 初回リリース

== Upgrade Notice ==

= 1.7.1 =
Throws SPAM Away の判定に AI を組み込み。OpenAI と Google Gemini で動作確認済み。
