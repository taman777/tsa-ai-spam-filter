<?php

/**
 * Plugin Name: GTI AI Spam Filter
 * Description: Throws SPAM Away と連携し、コメントを AI でスパム判定。有効/無効をスイッチで切替可能。AIベンダー選択で項目を切替。
 * Version:     1.7.0
 * Author:      GTI Inc.
 */

if (!defined('ABSPATH')) exit;

class GTI_Ai_Spam_Filter
{
    const OPT_KEY = 'gti_ai_spam_filter_options';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('tsa_validate_comment', [$this, 'filter_tsa_validate_comment'], 20, 5);
        add_filter('pre_comment_approved', [$this, 'filter_pre_comment_approved'], 20, 2);
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

    public function render_settings_page()
    {
        echo '<div class="wrap" id="gti-ai-spam-filter"><h1>AIスパムフィルター設定</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPT_KEY);
        do_settings_sections('gti-ai-spam-filter');
        submit_button();
        echo '</form></div>';
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
                'anthropic' => 'Anthropic',
                'google'    => 'Google Gemini',
                'custom'    => 'カスタムAPI'
            ], 'common'],
            ['openai_api_key', 'OpenAI APIキー', 'password', [], 'vendor-openai'],
            ['openai_model', 'OpenAI モデル名', 'text', [], 'vendor-openai'],
            ['anthropic_api_key', 'Anthropic APIキー', 'password', [], 'vendor-anthropic'],
            ['anthropic_model', 'Anthropic モデル名', 'text', [], 'vendor-anthropic'],
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
                'anthropic_api_key' => '',
                'anthropic_model'   => 'claude-3-haiku',
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

    /**
     * 設定保存時のサニタイズ
     *
     * @param [type] $input
     * @return void
     */
    public function sanitize_options($input)
    {
        $cur = $this->get_options();
        $out = $cur;

        // 初期化
        $out['gti-ai-spam-filter-enabled']    = 0;
        $out['gti-ai-spam-filter-enable_log'] = 0;

        $vendor = $input['gti-ai-spam-filter-vendor'] ?? $cur['gti-ai-spam-filter-vendor'];

        foreach ($input as $k => $v) {
            // 有効化・ログフラグ
            if (in_array($k, ['gti-ai-spam-filter-enabled', 'gti-ai-spam-filter-enable_log'], true)) {
                $out[$k] = !empty($v) ? 1 : 0;

                // 閾値・タイムアウト
            } elseif ($k === 'gti-ai-spam-filter-threshold') {
                $out[$k] = max(0, min(1, floatval($v)));
            } elseif ($k === 'gti-ai-spam-filter-timeout') {
                $out[$k] = max(3, intval($v));

                // APIキー系
            } elseif (preg_match('/api_key$/', $k)) {
                // 選択中のベンダーなら「空欄＝保持」
                if (strpos($k, $vendor) !== false) {
                    if (!empty($v)) {
                        $out[$k] = sanitize_text_field($v);
                    } else {
                        // 空欄なら既存値を残す
                        $out[$k] = $cur[$k];
                    }
                } else {
                    // 他ベンダーは空欄保存でクリア
                    $out[$k] = !empty($v) ? sanitize_text_field($v) : '';
                }

                // その他（モデル名やエンドポイントなど）
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
            printf(
                '<input type="checkbox" name="%s" value="1" %s />',
                esc_attr($name),
                checked(1, $val, false)
            );
            echo '<span class="gti-slider"></span></label>';
            echo '<style>
                .gti-switch{position:relative;display:inline-block;width:50px;height:24px;}
                .gti-switch input{opacity:0;width:0;height:0;}
                .gti-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;
                    background:#ccc;transition:.4s;border-radius:24px;}
                .gti-slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;
                    background:#fff;transition:.4s;border-radius:50%;}
                .gti-switch input:checked+.gti-slider{background:#2271b1;}
                .gti-switch input:checked+.gti-slider:before{transform:translateX(26px);}
            </style>';
        } elseif ($type === 'checkbox') {
            printf(
                '<input type="checkbox" name="%s" value="1" %s />',
                esc_attr($name),
                checked(1, $val, false)
            );
        } elseif ($type === 'select') {
            echo '<select name="' . esc_attr($name) . '">';
            foreach ($args['options'] as $v => $label) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($v),
                    selected($val, $v, false),
                    esc_html($label)
                );
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

    public function enqueue_admin_assets($hook)
    {
        if (
            $hook !== 'settings_page_gti-ai-spam-filter' &&
            $hook !== 'throws-spam-away_page_gti-ai-spam-filter'
        ) return;

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

    public function filter_tsa_validate_comment($valid, $author, $comment, $post_id, $tsa_on_flg)
    {
        $opt = $this->get_options();
        if (empty($opt['enabled'])) return $valid;
        return $valid; // 本来はAI判定処理
    }

    public function filter_pre_comment_approved($approved, $commentdata)
    {
        $opt = $this->get_options();
        if (empty($opt['enabled'])) return $approved;
        if ($approved === 'spam') return 'spam';

        $ctx = [
            'author'   => $commentdata['comment_author'] ?? '',
            'comment'  => $commentdata['comment_content'] ?? '',
            'post_id'  => $commentdata['comment_post_ID'] ?? 0,
            'site'     => home_url('/'),
            'permalink' => !empty($commentdata['comment_post_ID']) ? get_permalink($commentdata['comment_post_ID']) : ''
        ];

        $ai = $this->judge_with_ai($ctx);

        $this->write_log([
            'context' => $ctx,
            'ai' => $ai,
            'approved_before' => $approved,
            'approved_after' => ($ai['label'] === 'SPAM' && $ai['score'] >= $ai['threshold']) ? 'spam' : $approved,
        ]);

        if ($ai['error']) return $approved;
        if ($ai['label'] === 'SPAM' && $ai['score'] >= $ai['threshold']) return 'spam';
        return $approved;
    }

    private function write_log($data)
    {
        $opt = $this->get_options();
        if (empty($opt['enable_log'])) return;
        $file = WP_CONTENT_DIR . '/ai-spam-filter.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** ---- 以下 judge_with_ai 系は前回コードのまま ---- */
    private function judge_with_ai($ctx)
    {
        $opt = $this->get_options();
        switch ($opt['vendor']) {
            case 'openai':
                return $this->judge_openai($ctx, $opt);
            case 'anthropic':
                return $this->judge_anthropic($ctx, $opt);
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
     * AI応答のパース
     *
     * @param [type] $raw
     * @param [type] $opt
     * @return void
     */
    private function parse_result($raw, $opt)
    {
        if (is_wp_error($raw)) {
            return ['error' => true, 'reason' => $raw->get_error_message()];
        }
        $body = wp_remote_retrieve_body($raw);
        if (empty($body)) {
            return ['error' => true, 'reason' => 'Empty response'];
        }

        // まず一次decode
        $json = json_decode($body, true);

        // OpenAI: choices[0].message.content にJSON文字列が入っている場合
        if (isset($json['choices'][0]['message']['content'])) {
            $candidate = trim($json['choices'][0]['message']['content']);
            $parsed = json_decode($candidate, true);
            if (is_array($parsed)) {
                $json = $parsed;
            }
        }
        // Anthropic: content[0].text にJSON文字列
        elseif (isset($json['content'][0]['text'])) {
            $candidate = trim($json['content'][0]['text']);
            $parsed = json_decode($candidate, true);
            if (is_array($parsed)) {
                $json = $parsed;
            }
        }
        // Google Gemini: candidates[0].content.parts[0].text にJSON文字列
        elseif (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            $candidate = trim($json['candidates'][0]['content']['parts'][0]['text']);
            $parsed = json_decode($candidate, true);
            if (is_array($parsed)) {
                $json = $parsed;
            }
        }

        // 最後にチェック
        if (!is_array($json) || empty($json['label'])) {
            return ['error' => true, 'reason' => 'Invalid JSON', 'raw' => $body];
        }

        return [
            'error'     => false,
            'label'     => strtoupper($json['label']) === 'SPAM' ? 'SPAM' : 'HAM',
            'score'     => isset($json['score']) ? floatval($json['score']) : 0.0,
            'reason'    => $json['reason'] ?? '',
            'threshold' => $opt['gti-ai-spam-filter-threshold'],
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

    private function judge_anthropic($ctx, $opt)
    {
        if (empty($opt['anthropic_api_key'])) return ['error' => true, 'reason' => 'Anthropic API key missing'];
        $endpoint = 'https://api.anthropic.com/v1/messages';
        $payload = [
            'model' => $opt['anthropic_model'],
            'max_tokens' => 300,
            'messages' => [['role' => 'user', 'content' => $this->build_prompt($ctx)]]
        ];
        $args = [
            'headers' => [
                'x-api-key' => $opt['anthropic_api_key'],
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ],
            'timeout' => $opt['timeout'],
            'body' => wp_json_encode($payload)
        ];
        return $this->parse_result(wp_remote_post($endpoint, $args), $opt);
    }

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
        $payload = ['model' => $opt['custom_model'], 'comment' => $ctx['comment'], 'context' => $ctx];
        $args = ['headers' => [
            'Authorization' => 'Bearer ' . $opt['custom_api_key'],
            'Content-Type' => 'application/json',
        ], 'timeout' => $opt['timeout'], 'body' => wp_json_encode($payload)];
        return $this->parse_result(wp_remote_post($opt['custom_endpoint'], $args), $opt);
    }
}

new GTI_Ai_Spam_Filter();
