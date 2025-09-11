=== GTI AI Spam Filter ===
Contributors: gtiinc, taman777
Tags: spam, ai, openai, anthropic, gemini, comment, filter
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.6.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI を利用して WordPress コメントをスパム判定する、Throws SPAM Away 専用の拡張プラグインです。

== Description ==

**GTI AI Spam Filter** は [Throws SPAM Away](https://ja.wordpress.org/plugins/throws-spam-away/) の拡張プラグインです。  
OpenAI / Anthropic Claude / Google Gemini / カスタム API を利用して、コメント投稿時に AI によるスパム判定を行います。

- Throws SPAM Away がインストールされ有効化されている場合のみ動作します
- AI のスコアリング結果に基づき、スパムコメントを即座にブロック
- ベンダーごとに API キーとモデルを設定可能
- ログ保存機能（任意）

**注意**: このプラグイン単体では動作しません。必ず先に Throws SPAM Away を導入してください。

== Installation ==

1. [Throws SPAM Away](https://ja.wordpress.org/plugins/throws-spam-away/) をインストール・有効化してください。
2. [GitHub リリースページ](https://github.com/your-account/gti-ai-spam-filter/releases) から最新の zip ファイルをダウンロードしてください。
3. WordPress 管理画面で「プラグイン > 新規追加 > プラグインのアップロード」を開きます。
4. ダウンロードした zip ファイルを選択して「今すぐインストール」→「有効化」を押してください。
5. 管理画面「Throws SPAM Away > AIスパムフィルター」から設定画面を開きます。
6. 使用する AI ベンダーを選び、APIキーとモデルを入力してください。
7. 「AIスパムフィルター有効化」をオンにすると稼働します。

== Frequently Asked Questions ==

= Q. Throws SPAM Away がなくても動きますか？ =
A. 動作しません。本プラグインは Throws SPAM Away の拡張です。

= Q. APIキーはどのように扱われますか？ =
A. セキュリティのため、保存後はマスクされ「（保存済み）」と表示されます。入力欄を空欄にして保存しても既存キーは消えません（ただし、他ベンダーを選択した場合はそのベンダーのキーは削除されます）。

= Q. ログはどこに出力されますか？ =
A. `wp-content/ai-spam-filter.log` に JSON 形式で記録されます。

== Screenshots ==

1. 設定画面（ベンダー選択）
2. OpenAI 設定例
3. スパム判定ログ出力例

== サンプルログ出力 ==

例: コメントがスパムと判定された場合のログ行

```
[2025-09-11 14:05:23] {
  "context": {
    "author": "test user",
    "comment": "今すぐ副業で月収100万円！誰でも簡単にできます！",
    "post_id": 123,
    "site": "https://example.com",
    "permalink": "https://example.com/?p=123"
  },
  "ai": {
    "error": false,
    "label": "SPAM",
    "score": 0.95,
    "reason": "広告的表現と煽り文句が含まれるためスパムと判定",
    "threshold": 0.7
  },
  "approved_before": 1,
  "approved_after": "spam"
}
```

== 懸念事項 ==

- **ログ削除機能は未実装** です。`wp-content/ai-spam-filter.log` が大きくなった場合は手動で削除してください。  
- **動作確認は OpenAI（ChatGPT API）でのみ実施済み** です。他のベンダー（Anthropic, Google Gemini, Custom）は設定UIを用意していますが、実運用での十分なテストは未実施です。  

== Changelog ==

= 1.6.1 =
* ログ保存機能を追加
* 各AIベンダーの判定処理を実装

= 1.5.0 =
* 初回リリース
* Throws SPAM Away との統合
* OpenAI 判定の実装
