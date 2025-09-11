# GTI AI Spam Filter

[Throws SPAM Away](https://ja.wordpress.org/plugins/throws-spam-away/) の拡張プラグインです。  
WordPress コメント投稿時に AI (OpenAI / Anthropic Claude / Google Gemini / カスタム API) を利用してスパム判定を行います。  

> ⚠️ このプラグイン単体では動作しません。必ず先に **Throws SPAM Away** を導入してください。

---

## ✨ 主な機能

- Throws SPAM Away と統合し、コメント送信時に AI 判定を追加
- OpenAI / Anthropic Claude / Google Gemini / カスタム API をサポート
- スイッチで有効/無効を切り替え可能
- ベンダー選択に応じて設定項目を自動切替
- スパム判定結果をログに保存（オプション）

---

## 📦 インストール方法

1. [Throws SPAM Away](https://ja.wordpress.org/plugins/throws-spam-away/) をインストール・有効化してください。
2. [リリースページ](https://github.com/your-account/gti-ai-spam-filter/releases) から最新の `gti-ai-spam-filter.zip` をダウンロードしてください。
3. WordPress 管理画面で「プラグイン > 新規追加 > プラグインのアップロード」を開きます。
4. ダウンロードした zip ファイルを選択して「今すぐインストール」→「有効化」を押してください。
5. 管理画面の「Throws SPAM Away > AIスパムフィルター」から設定画面を開きます。

---

## ⚙️ 設定

### 共通設定
- **AIスパムフィルター有効化** : ON/OFF スイッチ
- **AIベンダー選択** : OpenAI / Anthropic / Google Gemini / カスタム API
- **SPAM判定スコア閾値 (0〜1)** : 既定値 0.7
- **HTTPタイムアウト秒** : 既定値 12
- **ログ保存** : チェックすると `wp-content/ai-spam-filter.log` に判定結果を保存

### ベンダー別設定
- **OpenAI** : APIキー・モデル名 (`gpt-4o-mini` など)
- **Anthropic** : APIキー・モデル名 (`claude-3-haiku` など)
- **Google Gemini** : APIキー・モデル名 (`gemini-1.5-flash` など)
- **カスタム API** : APIキー・エンドポイント URL・モデル名

---

## 📑 サンプルログ出力

```json
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

---

## ⚠️ 懸念事項

- ログ削除機能は未実装です。`wp-content/ai-spam-filter.log` が大きくなった場合は手動で削除してください。  
- 動作確認は **OpenAI (ChatGPT API)** でのみ実施済みです。Anthropic, Google Gemini, カスタム API については UI を実装済みですが、実運用テストは未完了です。  

---

## 📜 ライセンス

GPL v2 or later  
https://www.gnu.org/licenses/gpl-2.0.html
