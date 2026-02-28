<?php

/**
 * Plugin Name: GTI AI Spam Filter
 * Description: Throws SPAM Away と連携し、コメントを AI でスパム判定。有効/無効をスイッチで切替可能。AIベンダー選択で項目を切替。
 * Version:     1.8.0
 * Author:      GTI Inc.
 */

if (!defined('ABSPATH')) exit;

class GTI_Ai_Spam_Filter
{
    const OPT_KEY = 'gti_ai_spam_filter_options';
    const PLUGIN_SLUG = 'tsa-ai-spam-filter';
    const UPDATE_CACHE_KEY = 'gti_ai_spam_filter_update_meta';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        if ($this->get_update_json_url() !== '') {
            add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update_info']);
            add_filter('plugins_api', [$this, 'filter_plugins_api'], 10, 3);
            add_action('upgrader_process_complete', [$this, 'clear_update_cache'], 10, 2);
        }

        // Throws SPAM Away フィルターフック
        add_filter('tsa_validate_comment', [$this, 'filter_tsa_validate_comment'], 20, 6);

        // ログ削除用
        add_action('admin_post_gti_ai_spam_filter_clear_log', [$this, 'clear_log_file']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    /**
     * WPの更新チェックへ独自配信情報を注入
     *
     * @param object $transient
     * @return object
     */
    public function inject_update_info($transient)
    {
        if (empty($transient) || empty($transient->checked) || !is_object($transient)) {
            return $transient;
        }

        $plugin_file = plugin_basename(__FILE__);
        $current_version = isset($transient->checked[$plugin_file]) ? $transient->checked[$plugin_file] : null;
        if (empty($current_version)) {
            return $transient;
        }

        $meta = $this->get_update_meta();
        if (empty($meta['version']) || empty($meta['download_url'])) {
            return $transient;
        }

        if (version_compare($meta['version'], $current_version, '>')) {
            $transient->response[$plugin_file] = (object) [
                'slug'        => self::PLUGIN_SLUG,
                'plugin'      => $plugin_file,
                'new_version' => $meta['version'],
                'url'         => isset($meta['url']) ? $meta['url'] : '',
                'package'     => $meta['download_url'],
            ];
        }

        return $transient;
    }

    /**
     * プラグイン情報モーダルへ独自配信情報を注入
     *
     * @param mixed  $result
     * @param string $action
     * @param object $args
     * @return mixed
     */
    public function filter_plugins_api($result, $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::PLUGIN_SLUG) {
            return $result;
        }

        $meta = $this->get_update_meta();
        if (empty($meta['version']) || empty($meta['download_url'])) {
            return $result;
        }

        $sections = isset($meta['sections']) && is_array($meta['sections']) ? $meta['sections'] : [];
        return (object) [
            'name'          => isset($meta['name']) ? $meta['name'] : 'GTI AI Spam Filter',
            'slug'          => self::PLUGIN_SLUG,
            'version'       => $meta['version'],
            'author'        => 'GTI Inc.',
            'homepage'      => isset($meta['url']) ? $meta['url'] : '',
            'download_link' => $meta['download_url'],
            'last_updated'  => isset($meta['last_updated']) ? $meta['last_updated'] : '',
            'sections'      => [
                'description' => isset($sections['description']) ? $sections['description'] : '',
                'changelog'   => isset($sections['changelog']) ? $sections['changelog'] : '',
            ],
        ];
    }

    /**
     * 更新処理後にversion.jsonキャッシュを破棄
     *
     * @param object $upgrader
     * @param array  $options
     * @return void
     */
    public function clear_update_cache($upgrader, $options)
    {
        if (empty($options['type']) || $options['type'] !== 'plugin') {
            return;
        }
        delete_site_transient(self::UPDATE_CACHE_KEY);
    }

    /**
     * version.json を取得して配列化
     *
     * @return array
     */
    private function get_update_meta()
    {
        $cached = get_site_transient(self::UPDATE_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $update_url = $this->get_update_json_url();
        if ($update_url === '') {
            return [];
        }

        $raw = '';
        $res = wp_remote_get($update_url, ['timeout' => 8]);
        if (!is_wp_error($res)) {
            $raw = wp_remote_retrieve_body($res);
        }

        if (empty($raw)) {
            return [];
        }

        $meta = json_decode($raw, true);
        if (!is_array($meta) || empty($meta['version']) || empty($meta['download_url'])) {
            return [];
        }

        set_site_transient(self::UPDATE_CACHE_KEY, $meta, HOUR_IN_SECONDS * 6);
        return $meta;
    }

    /**
     * config.cgi の先頭有効行から更新用version.jsonのURLを取得
     *
     * @return string
     */
    private function get_update_json_url()
    {
        static $cached_url = null;
        if ($cached_url !== null) {
            return $cached_url;
        }

        $config_file = dirname(__FILE__) . '/config.cgi';
        if (!file_exists($config_file) || !is_readable($config_file)) {
            $cached_url = '';
            return $cached_url;
        }

        $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            $cached_url = '';
            return $cached_url;
        }

        $url = trim((string) $lines[0]);
        $cached_url = filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
        return $cached_url;
    }

    /** メニュー追加 */
    public function add_submenu()
    {
        if ($this->is_tsa_active()) {
            add_submenu_page(
                'throws-spam-away',
                'AIスパムフィルター設定',
                'AIスパムフィルター',
                'manage_options',
                'gti-ai-spam-filter',
                [$this, 'render_settings_page']
            );
        } else {
            add_menu_page(
                'AIスパムフィルター',
                'AIスパムフィルター',
                'manage_options',
                'gti-ai-spam-filter',
                [$this, 'render_error_page'],
                'dashicons-shield',
                81
            );
        }
    }

    private function is_tsa_active()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        return is_plugin_active('throws-spam-away/throws_spam_away.php');
    }

    public function render_error_page()
    {
        echo '<div class="wrap"><h1>AIスパムフィルター</h1>';
        echo '<div class="notice notice-error"><p>Throws SPAM Away が有効化されていません。</p></div></div>';
    }

    /** 設定ページ */
    public function render_settings_page()
    {
        echo '<div class="wrap" id="gti-ai-spam-filter"><h1>AIスパムフィルター設定</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPT_KEY);
        do_settings_sections('gti-ai-spam-filter');
        submit_button();
        echo '</form>';

        // ログ表示
        $log_preview = $this->get_log_preview();
        echo '<h2 style="margin-top:24px;">ログ表示（最新200行）</h2>';
        echo '<textarea readonly class="large-text code" rows="14" style="font-family:monospace;">' . esc_textarea($log_preview) . '</textarea>';

        // ログ削除フォーム
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:20px;">';
        wp_nonce_field('gti_ai_spam_filter_clear_log');
        echo '<input type="hidden" name="action" value="gti_ai_spam_filter_clear_log">';
        submit_button('ログ削除', 'delete');
        echo '</form>';

        echo '</div>';
    }

    /**
     * ログ表示用に末尾を取得
     *
     * @param int $max_lines
     * @return string
     */
    private function get_log_preview($max_lines = 200)
    {
        $file = WP_CONTENT_DIR . '/ai-spam-filter.log';
        if (!file_exists($file)) {
            return 'ログファイルはまだ作成されていません。';
        }

        if (!is_readable($file)) {
            return 'ログファイルを読み取れません。';
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return 'ログファイルの読み取りに失敗しました。';
        }

        $total = count($lines);
        if ($total === 0) {
            return 'ログファイルは空です。';
        }

        $tail = array_slice($lines, -1 * absint($max_lines));
        $formatted = [];

        // 新しいログを先頭に表示
        for ($i = count($tail) - 1; $i >= 0; $i--) {
            $log_line = $tail[$i];

            $date_str = '';
            $json_str = $log_line;
            if (preg_match('/^\[([^\]]+)\]\s*(.+)$/u', $log_line, $m)) {
                $date_str = $m[1];
                $json_str = $m[2];
            }

            $row = json_decode($json_str, true);
            if (!is_array($row)) {
                $formatted[] = sprintf('[%s] RAW: %s', $date_str ?: '-', $log_line);
                continue;
            }

            $ctx = isset($row['context']) && is_array($row['context']) ? $row['context'] : [];
            $ai = isset($row['ai']) && is_array($row['ai']) ? $row['ai'] : [];
            // 一行で
            $comment = preg_replace('/\s+/u', ' ', (string) ($ctx['comment'] ?? ''));
            $reason = preg_replace('/\s+/u', ' ', (string) ($ai['reason'] ?? ''));
            $formatted[] = sprintf(
                "[%s] author=%s | comment=%s | post_id=%s | site=%s | permalink=%s | ai.error=%s | ai.label=%s | ai.score=%s | ai.threshold=%s | ai.reason=%s | tsa_result=%s",
                $date_str ?: '-',
                (string) ($ctx['author'] ?? ''),
                trim((string) $comment),
                (string) ($ctx['post_id'] ?? ''),
                (string) ($ctx['site'] ?? ''),
                (string) ($ctx['permalink'] ?? ''),
                isset($ai['error']) ? ($ai['error'] ? 'true' : 'false') : '',
                (string) ($ai['label'] ?? ''),
                isset($ai['score']) ? (string) $ai['score'] : '',
                isset($ai['threshold']) ? (string) $ai['threshold'] : '',
                trim((string) $reason),
                isset($row['tsa_result']) ? ($row['tsa_result'] ? 'true' : 'false') : ''
            );
        }

        return implode(PHP_EOL, $formatted);
    }

    /** ログ削除処理 */
    public function clear_log_file()
    {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        check_admin_referer('gti_ai_spam_filter_clear_log');

        $file = WP_CONTENT_DIR . '/ai-spam-filter.log';
        if (file_exists($file)) {
            if (@unlink($file)) {
                wp_safe_redirect(admin_url('admin.php?page=gti-ai-spam-filter&log_cleared=1'));
            } else {
                wp_safe_redirect(admin_url('admin.php?page=gti-ai-spam-filter&log_error=1'));
            }
        } else {
            wp_safe_redirect(admin_url('admin.php?page=gti-ai-spam-filter&log_missing=1'));
        }
        exit;
    }

    /** 通知 */
    public function admin_notices()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'gti-ai-spam-filter') return;

        if (isset($_GET['log_cleared'])) {
            echo '<div class="notice notice-success is-dismissible"><p>AIスパムフィルターログを削除しました。</p></div>';
        } elseif (isset($_GET['log_error'])) {
            echo '<div class="notice notice-error"><p>ログファイルの削除に失敗しました。</p></div>';
        } elseif (isset($_GET['log_missing'])) {
            echo '<div class="notice notice-warning is-dismissible"><p>ログファイルは存在しませんでした。</p></div>';
        }
    }

    /** 設定項目 */
    public function register_settings()
    {
        register_setting(self::OPT_KEY, self::OPT_KEY, [$this, 'sanitize_options']);

        add_settings_section(
            'gti_ai_spam_section',
            'AI Spam 判定設定',
            function () {
                echo '<p>コメントを外部AIで判定します。</p>';
            },
            'gti-ai-spam-filter'
        );

        $fields = [
            ['enabled', 'AIスパムフィルター有効化', 'switch', [], 'common'],
            ['vendor', 'AIベンダー', 'select', [
                'openai'    => 'OpenAI',
                'google'    => 'Google Gemini',
                'custom'    => 'カスタムAPI'
            ], 'common'],
            ['openai_api_key', 'OpenAI APIキー', 'password', [], 'vendor-openai'],
            ['openai_model', 'OpenAI モデル名', 'text', [], 'vendor-openai'],
            ['google_api_key', 'Google APIキー', 'password', [], 'vendor-google'],
            ['google_model', 'Google モデル名', 'text', [], 'vendor-google'],
            ['custom_api_key', 'カスタム APIキー', 'password', [], 'vendor-custom'],
            ['custom_endpoint', 'カスタム APIエンドポイントURL', 'text', [], 'vendor-custom'],
            ['custom_model', 'カスタム モデル名', 'text', [], 'vendor-custom'],
            ['threshold', 'SPAM判定スコア閾値 (0〜1)', 'number', [], 'common'],
            ['timeout', 'HTTPタイムアウト秒', 'number', [], 'common'],
            ['enable_log', 'ログ保存', 'checkbox', [], 'common'],
        ];

        foreach ($fields as $f) {
            $row_class = 'gti-ai-row ' . $f[4];
            add_settings_field(
                'gti_' . $f[0],
                esc_html($f[1]),
                [$this, 'render_field'],
                'gti-ai-spam-filter',
                'gti_ai_spam_section',
                [
                    'key'      => $f[0],
                    'type'     => $f[2],
                    'options'  => $f[3] ?? [],
                    'row_class' => $row_class,
                ]
            );
        }
    }

    public function get_options()
    {
        return wp_parse_args(
            get_option(self::OPT_KEY, []),
            [
                'enabled'        => 1,
                'vendor'         => 'openai',
                'openai_api_key' => '',
                'openai_model'   => 'gpt-4o-mini',
                'google_api_key' => '',
                'google_model'   => 'gemini-1.5-flash',
                'custom_api_key' => '',
                'custom_endpoint' => '',
                'custom_model'   => '',
                'threshold'      => 0.7,
                'timeout'        => 12,
                'enable_log'     => 0,
            ]
        );
    }

    public function sanitize_options($input)
    {
        $cur = $this->get_options();
        $out = $cur;

        // 初期化
        $out['enabled']    = 0;
        $out['enable_log'] = 0;

        $vendor = $input['vendor'] ?? $cur['vendor'];

        foreach ($input as $k => $v) {
            if (in_array($k, ['enabled', 'enable_log'], true)) {
                $out[$k] = !empty($v) ? 1 : 0;
            } elseif ($k === 'threshold') {
                $out[$k] = max(0, min(1, floatval($v)));
            } elseif ($k === 'timeout') {
                $out[$k] = max(3, intval($v));
            } elseif (preg_match('/api_key$/', $k)) {
                if (strpos($k, $vendor) !== false) {
                    $out[$k] = !empty($v) ? sanitize_text_field($v) : $cur[$k];
                } else {
                    $out[$k] = !empty($v) ? sanitize_text_field($v) : '';
                }
            } else {
                $out[$k] = sanitize_text_field($v);
            }
        }
        return $out;
    }

    public function render_field($args)
    {
        $opt  = $this->get_options();
        $key  = $args['key'];
        $type = $args['type'];
        $name = self::OPT_KEY . '[' . $key . ']';
        $val  = $opt[$key];
        $row_class = $args['row_class'];

        echo '<div class="' . esc_attr($row_class) . '">';

        if ($type === 'switch') {
            echo '<label class="gti-switch">';
            printf('<input type="checkbox" name="%s" value="1" %s />', esc_attr($name), checked(1, $val, false));
            echo '<span class="gti-slider"></span></label>';
            echo '<style>.gti-switch{position:relative;display:inline-block;width:50px;height:24px;}
                .gti-switch input{opacity:0;width:0;height:0;}
                .gti-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;
                    background:#ccc;transition:.4s;border-radius:24px;}
                .gti-slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;
                    background:#fff;transition:.4s;border-radius:50%;}
                .gti-switch input:checked+.gti-slider{background:#2271b1;}
                .gti-switch input:checked+.gti-slider:before{transform:translateX(26px);}</style>';
        } elseif ($type === 'checkbox') {
            printf('<input type="checkbox" name="%s" value="1" %s />', esc_attr($name), checked(1, $val, false));
        } elseif ($type === 'select') {
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($args['options'] as $v => $label) {
                printf('<option value="%s" %s>%s</option>', esc_attr($v), selected($val, $v, false), esc_html($label));
            }
            echo '</select>';
        } elseif ($type === 'password') {
            echo '<input type="password" name="' . esc_attr($name) . '" value="" class="regular-text" />';
            if (!empty($val)) echo '<span style="color:green">（保存済み）</span>';
        } else {
            $extra = ($type === 'number') ? ' step="0.01" min="0" ' : '';
            printf(
                '<input type="%s" name="%s" value="%s" class="regular-text" %s/>',
                esc_attr($type),
                esc_attr($name),
                esc_attr($val),
                $extra
            );
        }
        echo '</div>';
    }

    /**
     * 管理画面用スクリプト
     *
     * @param [type] $hook
     * @return void
     */
    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'settings_page_gti-ai-spam-filter' && $hook !== 'throws-spam-away_page_gti-ai-spam-filter') return;
        wp_add_inline_script('jquery-core', "
            jQuery(function($){
                function toggleVendorFields(){
                    var v = $('select[name=\"" . self::OPT_KEY . "[vendor]\"]').val();
                    $('tr:has(.gti-ai-row)').hide();
                    $('tr:has(.vendor-' + v + ')').show();
                    $('tr:has(.common)').show();
                }
                toggleVendorFields();
                $('select[name=\"" . self::OPT_KEY . "[vendor]\"]').on('change', toggleVendorFields);
            });
        ");
    }

    /**
     * TSA バリデーション拡張
     * @param bool $valid TSAの判定結果
     * @param string $author 投稿者名
     * @param string $comment コメント内容
     * @param int $post_id 投稿ID
     * @param bool $tsa_on_flg TSAが有効かどうか
     * @param object $tsa_obj TSAの内部オブジェクト（エラー情報設定用）
     * @return bool 拒否なら false を返す
     */
    public function filter_tsa_validate_comment($valid, $author, $comment, $post_id, $tsa_on_flg, $tsa_obj)
    {
        $opt = $this->get_options();

        // 無効ならスルー
        if (empty($opt['enabled'])) return $valid;

        // TSAですでにブロック済みなら尊重
        if ($valid === false) return false;

        // AI判定
        $ctx = [
            'author'    => $author,
            'comment'   => $comment,
            'post_id'   => $post_id,
            'site'      => home_url('/'),
            'permalink' => get_permalink($post_id),
        ];

        $ai = $this->judge_with_ai($ctx);

        // ログ
        $this->write_log([
            'context' => $ctx,
            'ai'      => $ai,
            'tsa_result' => $valid,
        ]);

        if ($ai['error']) return $valid;

        if ($ai['label'] === 'SPAM' && $ai['score'] >= $ai['threshold']) {
            if (is_object($tsa_obj)) {
                $tsa_obj->error_type = 'AI_SPAM';
                $tsa_obj->error_message = !empty($ai['reason'])
                    ? $ai['reason']
                    : 'AI によりスパムと判定されました。';
            }
            return false;
        }

        return $valid;
    }

    /**
     * ログ書き込み
     *
     * @param [type] $data
     * @return void
     */
    private function write_log($data)
    {
        $opt = $this->get_options();
        if (empty($opt['enable_log'])) return;
        $file = WP_CONTENT_DIR . '/ai-spam-filter.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * 外部から使用可能なログ記録メソッド
     *
     * @param array $data ログデータ
     * @return void
     */
    public function log_event($data)
    {
        $this->write_log($data);
    }

    /** ---- 以下 judge_with_ai 系 ---- */
    private function judge_with_ai($ctx)
    {
        $opt = $this->get_options();
        switch ($opt['vendor']) {
            case 'openai':
                return $this->judge_openai($ctx, $opt);
                // case 'anthropic':
                //     return $this->judge_anthropic($ctx, $opt);
            case 'google':
                return $this->judge_google($ctx, $opt);
            case 'custom':
                return $this->judge_custom($ctx, $opt);
        }
        return ['error' => true, 'reason' => 'Unsupported vendor'];
    }

    private function build_prompt($ctx)
    {
        return "以下のコメントがスパムかどうか判定してください。\n"
            . "JSONのみで答えてください。\n"
            . "フォーマット: {\"label\":\"SPAM|HAM\",\"score\":0.0~1.0,\"reason\":\"...\"}\n\n"
            . "サイト: {$ctx['site']}\n投稿: {$ctx['permalink']}\n"
            . "投稿者: {$ctx['author']}\nコメント: {$ctx['comment']}\n";
    }

    /**
     * AIの応答を解析する
     *
     * @param [type] $raw
     * @param [type] $opt
     * @return void
     */
    private function parse_result($raw, $opt)
    {
        if (is_wp_error($raw)) return ['error' => true, 'reason' => $raw->get_error_message()];
        $body = wp_remote_retrieve_body($raw);
        if (empty($body)) return ['error' => true, 'reason' => 'Empty response'];

        $json = json_decode($body, true);

        // OpenAI
        if (isset($json['choices'][0]['message']['content'])) {
            $candidate = trim($json['choices'][0]['message']['content']);
        }
        // // Anthropic
        // elseif (isset($json['content'][0]['text'])) {
        //     $candidate = trim($json['content'][0]['text']);
        // }
        // Google Gemini
        elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            $candidate = trim($json['candidates'][0]['content']['parts'][0]['text']);
        }

        // コードブロック除去 (```json ... ```)
        if (!empty($candidate)) {
            $candidate = preg_replace('/^```[a-zA-Z]*\n?/', '', $candidate);
            $candidate = preg_replace('/```$/', '', $candidate);
            $parsed = json_decode(trim($candidate), true);
            if (is_array($parsed)) $json = $parsed;
        }

        if (!is_array($json) || empty($json['label'])) {
            return ['error' => true, 'reason' => 'Invalid JSON', 'raw' => $body];
        }

        return [
            'error'     => false,
            'label'     => strtoupper($json['label']) === 'SPAM' ? 'SPAM' : 'HAM',
            'score'     => isset($json['score']) ? floatval($json['score']) : 0.0,
            'reason'    => $json['reason'] ?? '',
            'threshold' => $opt['threshold'],
        ];
    }


    private function judge_openai($ctx, $opt)
    {
        if (empty($opt['openai_api_key'])) return ['error' => true, 'reason' => 'OpenAI API key missing'];
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $payload = [
            'model' => $opt['openai_model'],
            'messages' => [
                ['role' => 'system', 'content' => 'You are a strict spam classifier.'],
                ['role' => 'user', 'content' => $this->build_prompt($ctx)]
            ],
            'temperature' => 0,
            'max_tokens' => 300,
        ];
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $opt['openai_api_key'],
                'Content-Type' => 'application/json',
            ],
            'timeout' => $opt['timeout'],
            'body' => wp_json_encode($payload)
        ];
        return $this->parse_result(wp_remote_post($endpoint, $args), $opt);
    }

    // private function judge_anthropic($ctx, $opt)
    // {
    //     if (empty($opt['anthropic_api_key'])) return ['error' => true, 'reason' => 'Anthropic API key missing'];
    //     $endpoint = 'https://api.anthropic.com/v1/messages';
    //     $payload = [
    //         'model' => $opt['anthropic_model'],
    //         'max_tokens' => 300,
    //         'messages' => [['role' => 'user', 'content' => $this->build_prompt($ctx)]]
    //     ];
    //     $args = [
    //         'headers' => [
    //             'x-api-key' => $opt['anthropic_api_key'],
    //             'Content-Type' => 'application/json',
    //             'anthropic-version' => '2023-06-01',
    //         ],
    //         'timeout' => $opt['timeout'],
    //         'body' => wp_json_encode($payload)
    //     ];
    //     return $this->parse_result(wp_remote_post($endpoint, $args), $opt);
    // }

    private function judge_google($ctx, $opt)
    {
        if (empty($opt['google_api_key'])) return ['error' => true, 'reason' => 'Google API key missing'];
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $opt['google_model'] . ':generateContent?key=' . $opt['google_api_key'];
        $payload = ['contents' => [['parts' => [['text' => $this->build_prompt($ctx)]]]]];
        $args = [
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => $opt['timeout'],
            'body' => wp_json_encode($payload)
        ];
        return $this->parse_result(wp_remote_post($endpoint, $args), $opt);
    }

    private function judge_custom($ctx, $opt)
    {
        if (empty($opt['custom_endpoint'])) return ['error' => true, 'reason' => 'Custom API endpoint missing'];
        $payload = [
            'model'   => $opt['custom_model'],
            'comment' => $ctx['comment'],
            'context' => $ctx,
        ];
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $opt['custom_api_key'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => $opt['timeout'],
            'body'    => wp_json_encode($payload),
        ];
        return $this->parse_result(wp_remote_post($opt['custom_endpoint'], $args), $opt);
    }
}

// グローバルに保存してfunctions.phpなどから利用可能に
$GLOBALS['gti_ai_spam_filter'] = new GTI_Ai_Spam_Filter();
