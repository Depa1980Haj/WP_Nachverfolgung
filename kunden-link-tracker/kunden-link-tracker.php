<?php
/**
 * Plugin Name: Kunden Link Tracker
 * Description: Erfasst Aufrufe über den Parameter campaign, verwaltet Kundencodes und zeigt Statistiken im Backend.
 * Version: 1.2.0
 * Author: Codex
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Kunden_Link_Tracker
{
    private const OPTION_DB_VERSION = 'kunden_link_tracker_db_version';
    private const DB_VERSION = '1.0.0';
    private const CAMPAIGN_TABLE_SUFFIX = 'kunden_tracker_campaigns';
    private const VISIT_TABLE_SUFFIX = 'kunden_tracker_visits';

    public function __construct()
    {
        register_activation_hook(__FILE__, [$this, 'activate']);

        add_action('init', [$this, 'track_visit']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_post_klt_create_campaign', [$this, 'handle_create_campaign']);
        add_action('admin_post_klt_delete_campaign', [$this, 'handle_delete_campaign']);
    }

    public function activate(): void
    {
        $this->create_tables();
        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
    }

    private function create_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $campaign_sql = "CREATE TABLE {$this->campaign_table()} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_name VARCHAR(191) NOT NULL,
            campaign_code VARCHAR(191) NOT NULL,
            target_url TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY campaign_code (campaign_code)
        ) {$charset_collate};";

        $visit_sql = "CREATE TABLE {$this->visit_table()} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            campaign_code VARCHAR(191) NOT NULL,
            visited_at DATETIME NOT NULL,
            request_uri TEXT NOT NULL,
            referer_url TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY campaign_code (campaign_code),
            KEY visited_at (visited_at)
        ) {$charset_collate};";

        dbDelta($campaign_sql);
        dbDelta($visit_sql);
    }

    public function track_visit(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (!isset($_GET['campaign'])) {
            return;
        }

        $campaign_code = sanitize_text_field(wp_unslash($_GET['campaign']));
        if ($campaign_code === '') {
            return;
        }

        global $wpdb;

        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, campaign_code FROM {$this->campaign_table()} WHERE campaign_code = %s",
                $campaign_code
            )
        );

        if (!$campaign) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : null;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null;

        $wpdb->insert(
            $this->visit_table(),
            [
                'campaign_id' => (int) $campaign->id,
                'campaign_code' => $campaign->campaign_code,
                'visited_at' => current_time('mysql'),
                'request_uri' => $request_uri,
                'referer_url' => $referer,
                'ip_address' => $ip,
                'user_agent' => $user_agent,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            'Kunden Tracker',
            'Kunden Tracker',
            'manage_options',
            'kunden-tracker-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-chart-bar',
            26
        );

        add_submenu_page(
            'kunden-tracker-dashboard',
            'Kampagnen verwalten',
            'Kampagnen',
            'manage_options',
            'kunden-tracker-campaigns',
            [$this, 'render_campaign_page']
        );
    }

    public function handle_create_campaign(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Nicht erlaubt.');
        }

        check_admin_referer('klt_create_campaign');

        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $target_url = isset($_POST['target_url']) ? esc_url_raw(wp_unslash($_POST['target_url'])) : home_url('/');
        $campaign_code = isset($_POST['campaign_code']) ? sanitize_text_field(wp_unslash($_POST['campaign_code'])) : '';

        if ($customer_name === '') {
            wp_safe_redirect(add_query_arg('message', 'missing_customer', admin_url('admin.php?page=kunden-tracker-campaigns')));
            exit;
        }

        if ($campaign_code === '') {
            $campaign_code = wp_generate_password(24, false, false);
        }

        global $wpdb;
        $result = $wpdb->insert(
            $this->campaign_table(),
            [
                'customer_name' => $customer_name,
                'campaign_code' => $campaign_code,
                'target_url' => $target_url,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );

        $message = $result ? 'created' : 'create_failed';
        wp_safe_redirect(add_query_arg('message', $message, admin_url('admin.php?page=kunden-tracker-campaigns')));
        exit;
    }

    public function handle_delete_campaign(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Nicht erlaubt.');
        }

        check_admin_referer('klt_delete_campaign');

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) {
            wp_safe_redirect(add_query_arg('message', 'delete_failed', admin_url('admin.php?page=kunden-tracker-campaigns')));
            exit;
        }

        global $wpdb;
        $wpdb->delete($this->campaign_table(), ['id' => $id], ['%d']);

        wp_safe_redirect(add_query_arg('message', 'deleted', admin_url('admin.php?page=kunden-tracker-campaigns')));
        exit;
    }

    public function render_dashboard_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;

        $filters = $this->get_dashboard_filters();
        $where_data = $this->build_visit_filter_where($filters);

        $campaign_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->campaign_table()}");

        $count_sql = "SELECT COUNT(v.id)
            FROM {$this->visit_table()} v
            INNER JOIN {$this->campaign_table()} c ON c.id = v.campaign_id
            {$where_data['sql']}";
        $filtered_visit_count = (int) $wpdb->get_var($this->prepare_query($count_sql, $where_data['params']));

        $total_pages = max(1, (int) ceil($filtered_visit_count / $filters['per_page']));
        if ($filters['current_page'] > $total_pages) {
            $filters['current_page'] = $total_pages;
        }

        $top_campaigns_sql = "SELECT c.customer_name, c.campaign_code, COUNT(v.id) AS total_visits
            FROM {$this->visit_table()} v
            INNER JOIN {$this->campaign_table()} c ON c.id = v.campaign_id
            {$where_data['sql']}
            GROUP BY c.id
            ORDER BY total_visits DESC, c.customer_name ASC
            LIMIT 10";
        $top_campaigns = $wpdb->get_results($this->prepare_query($top_campaigns_sql, $where_data['params']));

        $offset = ($filters['current_page'] - 1) * $filters['per_page'];
        $visit_query_params = array_merge($where_data['params'], [$filters['per_page'], $offset]);

        $recent_visits_sql = "SELECT c.customer_name, v.campaign_code, v.visited_at, v.request_uri, v.referer_url
            FROM {$this->visit_table()} v
            INNER JOIN {$this->campaign_table()} c ON c.id = v.campaign_id
            {$where_data['sql']}
            ORDER BY v.visited_at DESC
            LIMIT %d OFFSET %d";
        $recent_visits = $wpdb->get_results($wpdb->prepare($recent_visits_sql, $visit_query_params));

        ?>
        <div class="wrap kunden-tracker-wrap">
            <h1>Kunden Tracker Dashboard</h1>
            <style>
                .kunden-tracker-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin:16px 0 24px; }
                .kunden-card { background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
                .kunden-card h2 { margin:0; font-size:14px; color:#50575e; }
                .kunden-card .value { margin-top:10px; font-size:30px; font-weight:700; color:#1d2327; }
                .kunden-table-wrap { background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:12px; margin-top:16px; }
                .kunden-filter-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin:8px 0 14px; }
                .kunden-filter-row label { display:block; font-weight:600; margin-bottom:4px; }
                .kunden-filter-actions { display:flex; gap:8px; }
                .kunden-pagination { display:flex; align-items:center; gap:10px; margin-top:12px; }
            </style>

            <div class="kunden-tracker-grid">
                <div class="kunden-card">
                    <h2>Angelegte Kampagnen</h2>
                    <div class="value"><?php echo esc_html((string) $campaign_count); ?></div>
                </div>
                <div class="kunden-card">
                    <h2>Klicks (gefiltert)</h2>
                    <div class="value"><?php echo esc_html((string) $filtered_visit_count); ?></div>
                </div>
            </div>

            <div class="kunden-table-wrap">
                <h2>Filter</h2>
                <?php $this->render_dashboard_filters($filters); ?>
            </div>

            <div class="kunden-table-wrap">
                <h2>Top-Kunden nach Klicks (gefiltert)</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Kunde</th>
                            <th>Campaign Code</th>
                            <th>Klicks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($top_campaigns)) : ?>
                        <?php foreach ($top_campaigns as $campaign) : ?>
                            <tr>
                                <td><?php echo esc_html($campaign->customer_name); ?></td>
                                <td><code><?php echo esc_html($campaign->campaign_code); ?></code></td>
                                <td><?php echo esc_html((string) $campaign->total_visits); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3">Keine Daten für die aktuelle Filterung.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="kunden-table-wrap">
                <h2>Aufrufe</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Zeitpunkt</th>
                            <th>Kunde</th>
                            <th>Campaign</th>
                            <th>Zielseite</th>
                            <th>Referer</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($recent_visits)) : ?>
                        <?php foreach ($recent_visits as $visit) : ?>
                            <tr>
                                <td><?php echo esc_html($visit->visited_at); ?></td>
                                <td><?php echo esc_html($visit->customer_name); ?></td>
                                <td><code><?php echo esc_html($visit->campaign_code); ?></code></td>
                                <td><?php echo esc_html($visit->request_uri); ?></td>
                                <td><?php echo esc_html($visit->referer_url ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5">Keine Klickdaten für die aktuelle Filterung.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php $this->render_pagination($filters, $total_pages); ?>
            </div>
        </div>
        <?php
    }

    private function get_dashboard_filters(): array
    {
        $period_options = ['all', '7', '30', '90', '365'];
        $per_page_options = [10, 20, 30, 50];

        $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : 'all';
        if (!in_array($period, $period_options, true)) {
            $period = 'all';
        }

        $customer_id = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : 0;
        $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 50;
        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 50;
        }

        $current_page = isset($_GET['klt_page']) ? absint($_GET['klt_page']) : 1;
        if ($current_page < 1) {
            $current_page = 1;
        }

        return [
            'period' => $period,
            'customer_id' => $customer_id,
            'per_page' => $per_page,
            'current_page' => $current_page,
            'period_options' => $period_options,
            'per_page_options' => $per_page_options,
        ];
    }

    private function build_visit_filter_where(array $filters): array
    {
        $conditions = [];
        $params = [];

        if ($filters['customer_id'] > 0) {
            $conditions[] = 'c.id = %d';
            $params[] = $filters['customer_id'];
        }

        if ($filters['period'] !== 'all') {
            $days = (int) $filters['period'];
            $from_timestamp = strtotime("-{$days} days", current_time('timestamp'));
            $from_date = date('Y-m-d H:i:s', $from_timestamp);

            $conditions[] = 'v.visited_at >= %s';
            $params[] = $from_date;
        }

        return [
            'sql' => empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions),
            'params' => $params,
        ];
    }


    private function prepare_query(string $sql, array $params): string
    {
        global $wpdb;

        if (empty($params)) {
            return $sql;
        }

        return $wpdb->prepare($sql, $params);
    }

    private function render_dashboard_filters(array $filters): void
    {
        global $wpdb;

        $customers = $wpdb->get_results("SELECT id, customer_name FROM {$this->campaign_table()} ORDER BY customer_name ASC");
        ?>
        <form method="get">
            <input type="hidden" name="page" value="kunden-tracker-dashboard" />
            <input type="hidden" name="klt_page" value="1" />
            <div class="kunden-filter-row">
                <div>
                    <label for="customer_id">Kunde</label>
                    <select name="customer_id" id="customer_id">
                        <option value="0">Alle Kunden</option>
                        <?php foreach ($customers as $customer) : ?>
                            <option value="<?php echo esc_attr((string) $customer->id); ?>" <?php selected($filters['customer_id'], (int) $customer->id); ?>>
                                <?php echo esc_html($customer->customer_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="period">Zeitraum</label>
                    <select name="period" id="period">
                        <option value="all" <?php selected($filters['period'], 'all'); ?>>Gesamter Zeitraum</option>
                        <option value="7" <?php selected($filters['period'], '7'); ?>>Letzte 7 Tage</option>
                        <option value="30" <?php selected($filters['period'], '30'); ?>>Letzte 30 Tage</option>
                        <option value="90" <?php selected($filters['period'], '90'); ?>>Letzte 90 Tage</option>
                        <option value="365" <?php selected($filters['period'], '365'); ?>>Letzte 365 Tage</option>
                    </select>
                </div>

                <div>
                    <label for="per_page">Einträge pro Seite</label>
                    <select name="per_page" id="per_page">
                        <?php foreach ($filters['per_page_options'] as $option) : ?>
                            <option value="<?php echo esc_attr((string) $option); ?>" <?php selected($filters['per_page'], $option); ?>>
                                <?php echo esc_html((string) $option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="kunden-filter-actions">
                    <?php submit_button('Filtern', 'primary', '', false); ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=kunden-tracker-dashboard')); ?>">Zurücksetzen</a>
                </div>
            </div>
        </form>
        <?php
    }

    private function render_pagination(array $filters, int $total_pages): void
    {
        if ($total_pages <= 1) {
            return;
        }

        $current_page = $filters['current_page'];
        $previous_page = max(1, $current_page - 1);
        $next_page = min($total_pages, $current_page + 1);

        $base_args = [
            'page' => 'kunden-tracker-dashboard',
            'customer_id' => $filters['customer_id'],
            'period' => $filters['period'],
            'per_page' => $filters['per_page'],
        ];

        ?>
        <div class="kunden-pagination">
            <?php if ($current_page > 1) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['klt_page' => $previous_page]), admin_url('admin.php'))); ?>">&larr; Zurück</a>
            <?php else : ?>
                <button class="button" disabled>&larr; Zurück</button>
            <?php endif; ?>

            <span>Seite <?php echo esc_html((string) $current_page); ?> von <?php echo esc_html((string) $total_pages); ?></span>

            <?php if ($current_page < $total_pages) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['klt_page' => $next_page]), admin_url('admin.php'))); ?>">Weiter &rarr;</a>
            <?php else : ?>
                <button class="button" disabled>Weiter &rarr;</button>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_campaign_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;

        $campaigns = $wpdb->get_results(
            "SELECT c.*, COUNT(v.id) AS total_visits
             FROM {$this->campaign_table()} c
             LEFT JOIN {$this->visit_table()} v ON v.campaign_id = c.id
             GROUP BY c.id
             ORDER BY c.created_at DESC"
        );

        $base_url = home_url('/');
        $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : '';

        ?>
        <div class="wrap">
            <h1>Kampagnen verwalten</h1>
            <?php $this->render_notice($message); ?>

            <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin-top:16px;max-width:680px;">
                <h2>Neuen Kunden-Link anlegen</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="klt_create_campaign" />
                    <?php wp_nonce_field('klt_create_campaign'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="customer_name">Kundenname</label></th>
                            <td><input name="customer_name" id="customer_name" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="target_url">Ziel-URL</label></th>
                            <td><input name="target_url" id="target_url" type="url" class="regular-text" value="<?php echo esc_attr($base_url); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="campaign_code">Campaign-Code (optional)</label></th>
                            <td><input name="campaign_code" id="campaign_code" type="text" class="regular-text" placeholder="leer lassen = automatisch"></td>
                        </tr>
                    </table>
                    <?php submit_button('Kampagne anlegen'); ?>
                </form>
            </div>

            <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin-top:18px;">
                <h2>Bestehende Kampagnen</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Kunde</th>
                            <th>Campaign-Code</th>
                            <th>Generierter Link</th>
                            <th>Klicks</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($campaigns)) : ?>
                        <?php foreach ($campaigns as $campaign) :
                            $tracking_link = add_query_arg('campaign', $campaign->campaign_code, $campaign->target_url);
                            ?>
                            <tr>
                                <td><?php echo esc_html($campaign->customer_name); ?></td>
                                <td><code><?php echo esc_html($campaign->campaign_code); ?></code></td>
                                <td><input type="text" readonly class="regular-text" value="<?php echo esc_attr($tracking_link); ?>" onclick="this.select()" style="width:100%;max-width:480px;"/></td>
                                <td><?php echo esc_html((string) $campaign->total_visits); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Kampagne wirklich löschen?');">
                                        <input type="hidden" name="action" value="klt_delete_campaign" />
                                        <input type="hidden" name="id" value="<?php echo esc_attr((string) $campaign->id); ?>" />
                                        <?php wp_nonce_field('klt_delete_campaign'); ?>
                                        <button type="submit" class="button button-link-delete">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5">Noch keine Kampagnen angelegt.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_notice(string $message): void
    {
        $messages = [
            'created' => ['success', 'Kampagne wurde angelegt.'],
            'create_failed' => ['error', 'Kampagne konnte nicht angelegt werden (Code ggf. bereits vergeben).'],
            'missing_customer' => ['error', 'Bitte einen Kundennamen angeben.'],
            'deleted' => ['success', 'Kampagne wurde gelöscht.'],
            'delete_failed' => ['error', 'Kampagne konnte nicht gelöscht werden.'],
        ];

        if (!isset($messages[$message])) {
            return;
        }

        [$type, $text] = $messages[$message];
        printf('<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr($type), esc_html($text));
    }

    private function campaign_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::CAMPAIGN_TABLE_SUFFIX;
    }

    private function visit_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . self::VISIT_TABLE_SUFFIX;
    }
}

new Kunden_Link_Tracker();
