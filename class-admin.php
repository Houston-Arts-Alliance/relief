<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class HAA_DRS_Admin {

    public static function init() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_haa_drs_update_status', [ __CLASS__, 'ajax_update_status' ] );
        add_action( 'wp_ajax_haa_drs_save_notes', [ __CLASS__, 'ajax_save_notes' ] );
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'haa-drs' ) === false ) return;

        wp_enqueue_style(
            'haa-drs-admin',
            HAA_DRS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            HAA_DRS_VERSION
        );
    }

    public static function render_page() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        if ( $action === 'view' && ! empty( $_GET['id'] ) ) {
            self::render_detail_page( intval( $_GET['id'] ) );
        } else {
            self::render_list_page();
        }
    }

    public static function render_list_page() {
        $table = new HAA_DRS_List_Table();
        $table->prepare_items();

        $counts = HAA_DRS_Database::status_counts();
        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

        $statuses = [
            ''              => 'All',
            'new'           => 'New',
            'under_review'  => 'Under Review',
            'needs_followup'=> 'Needs Follow-up',
            'approved'      => 'Approved',
            'declined'      => 'Declined',
            'archived'      => 'Archived',
        ];
        ?>
        <div class="wrap haa-drs-admin">
            <h1 class="wp-heading-inline">SOAR Applications</h1>

            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=haa-drs&export=csv' . ( $current_status ? '&status=' . $current_status : '' ) ), 'haa_drs_export_csv' ) ); ?>" class="page-title-action">Export CSV</a>

            <hr class="wp-header-end">

            <ul class="subsubsub">
                <?php $i = 0; foreach ( $statuses as $slug => $label ) :
                    $count = $slug === '' ? ( $counts['all'] ?? 0 ) : ( $counts[ $slug ] ?? 0 );
                    $active = $current_status === $slug ? ' class="current"' : '';
                    $url = admin_url( 'admin.php?page=haa-drs' . ( $slug ? '&status=' . $slug : '' ) );
                    if ( $i > 0 ) echo ' | ';
                ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>"<?php echo $active; ?>>
                        <?php echo esc_html( $label ); ?> <span class="count">(<?php echo intval( $count ); ?>)</span>
                    </a>
                </li>
                <?php $i++; endforeach; ?>
            </ul>

            <form method="get">
                <input type="hidden" name="page" value="haa-drs">
                <?php if ( $current_status ) : ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
                <?php endif; ?>
                <?php $table->search_box( 'Search Applications', 'haa-drs-search' ); ?>
            </form>

            <form method="get">
                <input type="hidden" name="page" value="haa-drs">
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }

    public static function render_detail_page( $id ) {
        $sub = HAA_DRS_Database::get( $id );
        if ( ! $sub ) {
            echo '<div class="wrap"><h1>Application Not Found</h1><p>This application does not exist.</p></div>';
            return;
        }

        $statuses = [
            'new'            => 'New',
            'under_review'   => 'Under Review',
            'needs_followup' => 'Needs Follow-up',
            'approved'       => 'Approved',
            'declined'       => 'Declined',
            'archived'       => 'Archived',
        ];

        $tier_colors = [
            'Tier 1' => '#e74c3c',
            'Tier 2' => '#f39c12',
            'Tier 3' => '#3498db',
            'Tier 4' => '#95a5a6',
        ];
        $tier_color = '#95a5a6';
        foreach ( $tier_colors as $prefix => $color ) {
            if ( strpos( $sub->priority_tier, $prefix ) === 0 ) {
                $tier_color = $color;
                break;
            }
        }
        ?>
        <div class="wrap haa-drs-admin haa-drs-detail">
            <h1>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=haa-drs' ) ); ?>">&larr; All Applications</a>
                &nbsp;&middot;&nbsp; <?php echo esc_html( $sub->application_id ); ?>
            </h1>

            <div class="haa-drs-detail-grid">
                <div class="haa-drs-detail-main">

                    <div class="haa-drs-detail-card haa-drs-detail-header-card">
                        <div class="haa-drs-detail-header-row">
                            <div>
                                <h2 style="margin:0;"><?php echo esc_html( $sub->full_name ); ?></h2>
                                <p style="margin:4px 0 0;color:#666;"><?php echo esc_html( $sub->email ); ?> &middot; <?php echo esc_html( $sub->phone ); ?></p>
                            </div>
                            <div class="haa-drs-tier-badge" style="background:<?php echo esc_attr( $tier_color ); ?>;">
                                <?php echo esc_html( $sub->priority_tier ); ?> (<?php echo intval( $sub->priority_score ); ?>)
                            </div>
                        </div>
                        <div class="haa-drs-detail-meta">
                            <span>Submitted: <?php echo esc_html( wp_date( 'M j, Y g:ia', strtotime( $sub->created_at ) ) ); ?></span>
                            <span>Updated: <?php echo esc_html( wp_date( 'M j, Y g:ia', strtotime( $sub->updated_at ) ) ); ?></span>
                        </div>
                    </div>

                    <?php
                    $race_raw = $sub->race_ethnicity ?? '';
                    $race_arr = json_decode( $race_raw, true );
                    if ( is_array( $race_arr ) ) {
                        $race_display = implode( ', ', $race_arr );
                        if ( ! empty( $sub->race_ethnicity_other ) ) {
                            $race_display .= ' (' . $sub->race_ethnicity_other . ')';
                        }
                    } else {
                        $race_display = ( $race_raw === 'other_description' ? ( $sub->race_ethnicity_other ?? '' ) : $race_raw ) ?: '-';
                    }
                    ?>
                    <?php self::detail_section( 'Personal Information', [
                        'First Name'      => $sub->first_name ?? '',
                        'Last Name'       => $sub->last_name ?? '',
                        'Full Name'       => $sub->full_name,
                        'Address'         => trim( $sub->address_1 . ' ' . $sub->address_2 . ', ' . $sub->city . ', ' . $sub->state . ' ' . $sub->zip ),
                        'Phone'           => $sub->phone,
                        'Email'           => $sub->email,
                        'Date of Birth'   => $sub->dob ? wp_date( 'M j, Y', strtotime( $sub->dob ) ) : '',
                        'Language'        => $sub->preferred_language,
                        'Race/Ethnicity'  => $race_display,
                        'Emergency Contact' => trim( ( $sub->emergency_contact_name ?? '' ) . ' ' . ( $sub->emergency_contact_phone ?? '' ) ),
                    ] ); ?>

                    <?php self::detail_section( 'Online Presence', [
                        'Website'      => $sub->website  ? '<a href="' . esc_url( $sub->website ) . '" target="_blank">' . esc_html( $sub->website ) . '</a>' : '-',
                        'Social Media' => $sub->social_media ? '<a href="' . esc_url( $sub->social_media ) . '" target="_blank">' . esc_html( $sub->social_media ) . '</a>' : '-',
                        'CV Link'      => $sub->cv_link ? '<a href="' . esc_url( $sub->cv_link ) . '" target="_blank">' . esc_html( $sub->cv_link ) . '</a>' : '-',
                    ], true ); ?>

                    <?php
                    $hs_labels = [ '1_2' => '1–2 people', '3_4' => '3–4 people', '5_plus' => '5 or more' ];
                    self::detail_section( 'Household', [
                        'Household Size'   => $hs_labels[ $sub->household_size ] ?? $sub->household_size,
                        'Adults 18-64'     => $sub->adults_count,
                        'Seniors 65+'      => $sub->seniors_count,
                        'Children <18'     => $sub->children_count,
                        'Household Income' => $sub->household_income ?: '-',
                    ] ); ?>

                    <?php self::detail_section( 'Eligibility', [
                        'Age 18+'          => ucfirst( $sub->age_18_plus ),
                        'County'           => $sub->county ?: '-',
                        'Artist'           => ucfirst( $sub->is_artist ),
                        'Discipline'       => $sub->artistic_discipline ?: '-',
                        'SVI Score'        => number_format( $sub->svi_score, 2 ),
                    ] ); ?>

                    <?php self::detail_section( 'Disaster / Emergency', [
                        'Event' => $sub->disaster_event,
                    ] ); ?>

                    <?php
                    $vuln    = json_decode( $sub->vulnerability_factors, true ) ?: [];
                    $assist  = json_decode( $sub->assistance_types, true ) ?: [];
                    $ext     = json_decode( $sub->external_support, true ) ?: [];
                    $needs   = json_decode( $sub->current_needs, true ) ?: [];

                    $housing_labels = [
                        '4' => 'Uninhabitable: destroyed/unsafe',
                        '3' => 'Not habitable: utility outage/quarantine',
                        '2' => 'Major damage: significant repairs needed',
                        '1' => 'Minor damage: minor repairs needed',
                        '0' => 'No damage: fully habitable',
                    ];
                    $need_labels = [
                        '3' => 'Major: basic needs won\'t be met in 7 days',
                        '2' => 'Moderate: important gaps',
                        '1' => 'Minor: small gaps remain',
                        '0' => 'Covered: needs met',
                    ];
                    $loss_labels = [
                        '4' => 'Everything lost',
                        '3' => 'Most lost (>50%)',
                        '2' => 'Some lost',
                        '1' => 'Temporarily inaccessible or not yet assessed',
                        '0' => 'No physical loss',
                    ];
                    ?>
                    <div class="haa-drs-detail-card">
                        <h3>Assessment</h3>
                        <table class="haa-drs-detail-table">
                            <tr><th>Vulnerability Factors</th><td><?php echo $vuln ? esc_html( implode( ', ', $vuln ) ) : '-'; ?><?php echo ! empty( $sub->vulnerability_factors_other ) ? ': ' . esc_html( $sub->vulnerability_factors_other ) : ''; ?></td></tr>
                            <tr><th>Housing</th><td><?php echo esc_html( $housing_labels[ (string) $sub->housing_status ] ?? $sub->housing_status ); ?></td></tr>
                            <tr><th>Received Assistance</th><td><?php echo esc_html( ucfirst( $sub->received_assistance ) ); ?></td></tr>
                            <?php if ( $assist ) : ?>
                            <tr><th>Assistance Types</th><td><?php echo esc_html( implode( ', ', $assist ) ); ?><?php echo $sub->assistance_other ? ': ' . esc_html( $sub->assistance_other ) : ''; ?></td></tr>
                            <?php endif; ?>
                            <tr><th>FEMA Applied</th><td><?php echo esc_html( ucfirst( $sub->fema_applied ) ); ?></td></tr>
                            <tr><th>SBA Applied</th><td><?php echo esc_html( ucfirst( $sub->sba_applied ) ); ?></td></tr>
                            <?php if ( $ext || $sub->external_support_other ) : ?>
                            <tr><th>External Support</th><td><?php echo $ext ? esc_html( implode( ', ', $ext ) ) : '-'; ?><?php echo $sub->external_support_other ? ': ' . esc_html( $sub->external_support_other ) : ''; ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Need Level</th><td><?php echo esc_html( $need_labels[ (string) $sub->need_level ] ?? $sub->need_level ); ?></td></tr>
                            <tr><th>Current Needs</th><td><?php echo $needs ? esc_html( implode( ', ', $needs ) ) : '-'; ?><?php echo ! empty( $sub->current_needs_other ) ? ': ' . esc_html( $sub->current_needs_other ) : ''; ?></td></tr>
                            <tr><th>Belongings Loss</th><td><?php echo esc_html( $loss_labels[ (string) $sub->belongings_loss ] ?? $sub->belongings_loss ); ?></td></tr>
                        </table>
                    </div>

                    <div class="haa-drs-detail-card">
                        <h3>Impact Statement</h3>
                        <div class="haa-drs-impact-text">
                            <?php echo $sub->impact_statement ? nl2br( esc_html( $sub->impact_statement ) ) : '<em>No statement provided.</em>'; ?>
                        </div>
                    </div>

                    <?php self::detail_section( 'Consent and Signature', [
                        'Consent Agreed'    => $sub->consent_agreed ? 'Yes' : 'No',
                        'Signature'         => $sub->signature_name,
                        'Signed Date (UTC)' => $sub->signature_date ? wp_date( 'M j, Y g:ia', strtotime( $sub->signature_date ) ) : '',
                    ] ); ?>

                </div>

                <div class="haa-drs-detail-sidebar">

                    <div class="haa-drs-detail-card">
                        <h3>Status</h3>
                        <select id="haa-drs-status-select" class="haa-drs-admin-select" data-id="<?php echo intval( $sub->id ); ?>">
                            <?php foreach ( $statuses as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $sub->status, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="haa-drs-save-status" class="button button-primary" style="margin-top:8px;width:100%;">Update Status</button>
                        <div id="haa-drs-status-msg" style="margin-top:6px;font-size:13px;"></div>
                    </div>

                    <div class="haa-drs-detail-card">
                        <h3>Admin Notes</h3>
                        <textarea id="haa-drs-admin-notes" rows="6" style="width:100%;" data-id="<?php echo intval( $sub->id ); ?>"><?php echo esc_textarea( $sub->admin_notes ); ?></textarea>
                        <button type="button" id="haa-drs-save-notes" class="button" style="margin-top:8px;width:100%;">Save Notes</button>
                        <div id="haa-drs-notes-msg" style="margin-top:6px;font-size:13px;"></div>
                    </div>

                    <div class="haa-drs-detail-card">
                        <h3>Quick Facts</h3>
                        <ul class="haa-drs-quick-facts">
                            <li><strong>Score:</strong> <?php echo intval( $sub->priority_score ); ?></li>
                            <li><strong>Tier:</strong> <?php echo esc_html( $sub->priority_tier ); ?></li>
                            <li><strong>IP:</strong> <?php echo esc_html( $sub->ip_address ); ?></li>
                            <li><strong>ID:</strong> <?php echo esc_html( $sub->application_id ); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function(){
            document.getElementById('haa-drs-save-status').addEventListener('click', function(){
                var btn = this, sel = document.getElementById('haa-drs-status-select'), msg = document.getElementById('haa-drs-status-msg');
                btn.disabled = true;
                msg.textContent = 'Saving...';
                var fd = new FormData();
                fd.append('action', 'haa_drs_update_status');
                fd.append('id', sel.dataset.id);
                fd.append('status', sel.value);
                fd.append('_wpnonce', '<?php echo wp_create_nonce( 'haa_drs_admin' ); ?>');
                fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(function(r){
                    msg.textContent = r.success ? 'Saved.' : 'Error.';
                    msg.style.color = r.success ? '#1B7A4A' : '#c53030';
                    btn.disabled = false;
                });
            });
            document.getElementById('haa-drs-save-notes').addEventListener('click', function(){
                var btn = this, ta = document.getElementById('haa-drs-admin-notes'), msg = document.getElementById('haa-drs-notes-msg');
                btn.disabled = true;
                msg.textContent = 'Saving...';
                var fd = new FormData();
                fd.append('action', 'haa_drs_save_notes');
                fd.append('id', ta.dataset.id);
                fd.append('notes', ta.value);
                fd.append('_wpnonce', '<?php echo wp_create_nonce( 'haa_drs_admin' ); ?>');
                fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(function(r){
                    msg.textContent = r.success ? 'Saved.' : 'Error.';
                    msg.style.color = r.success ? '#1B7A4A' : '#c53030';
                    btn.disabled = false;
                });
            });
        })();
        </script>
        <?php
    }

    private static function detail_section( $title, $fields, $allow_html = false ) {
        ?>
        <div class="haa-drs-detail-card">
            <h3><?php echo esc_html( $title ); ?></h3>
            <table class="haa-drs-detail-table">
                <?php foreach ( $fields as $label => $value ) : ?>
                <tr>
                    <th><?php echo esc_html( $label ); ?></th>
                    <td><?php echo $allow_html ? wp_kses_post( $value ) : esc_html( $value ?: '-' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    public static function ajax_update_status() {
        check_ajax_referer( 'haa_drs_admin' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();

        $id     = intval( $_POST['id'] );
        $status = sanitize_text_field( $_POST['status'] );
        $valid  = [ 'new', 'under_review', 'needs_followup', 'approved', 'declined', 'archived' ];

        if ( ! in_array( $status, $valid, true ) ) wp_send_json_error();

        HAA_DRS_Database::update( $id, [ 'status' => $status ] );
        wp_send_json_success();
    }

    public static function ajax_save_notes() {
        check_ajax_referer( 'haa_drs_admin' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();

        $id    = intval( $_POST['id'] );
        $notes = sanitize_textarea_field( $_POST['notes'] );

        HAA_DRS_Database::update( $id, [ 'admin_notes' => $notes ] );
        wp_send_json_success();
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( isset( $_POST['haa_drs_settings_nonce'] ) && wp_verify_nonce( $_POST['haa_drs_settings_nonce'], 'haa_drs_settings' ) ) {
            update_option( 'haa_drs_notify_email', sanitize_email( $_POST['haa_drs_notify_email'] ?? '' ) );
            update_option( 'haa_drs_webhook_url', esc_url_raw( $_POST['haa_drs_webhook_url'] ?? '' ) );
            update_option( 'haa_drs_hud_api_token', sanitize_text_field( $_POST['haa_drs_hud_api_token'] ?? '' ) );
            update_option( 'haa_drs_rate_limit', intval( $_POST['haa_drs_rate_limit'] ?? 15 ) );
            update_option( 'haa_drs_current_event', sanitize_text_field( $_POST['haa_drs_current_event'] ?? '' ) );
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>SOAR Settings</h1>
            <form method="post">
                <?php wp_nonce_field( 'haa_drs_settings', 'haa_drs_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="haa_drs_current_event">Current Disaster / Emergency Event</label></th>
                        <td>
                            <input type="text" id="haa_drs_current_event" name="haa_drs_current_event" value="<?php echo esc_attr( get_option( 'haa_drs_current_event', '' ) ); ?>" class="regular-text" placeholder="e.g. Hurricane Season 2026">
                            <p class="description">The name of the active disaster or emergency event. This is automatically applied to all new applications so applicants do not need to enter it. Update this when the active event changes.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="haa_drs_notify_email">Staff Notification Email</label></th>
                        <td>
                            <input type="email" id="haa_drs_notify_email" name="haa_drs_notify_email" value="<?php echo esc_attr( get_option( 'haa_drs_notify_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text">
                            <p class="description">Receives a notification each time a new application is submitted.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="haa_drs_hud_api_token">HUD API Token</label></th>
                        <td>
                            <input type="text" id="haa_drs_hud_api_token" name="haa_drs_hud_api_token" value="<?php echo esc_attr( get_option( 'haa_drs_hud_api_token', '' ) ); ?>" class="large-text" autocomplete="off">
                            <p class="description">HUD User API token for the USPS ZIP-to-Census-Tract crosswalk. Required for the SVI ZIP code lookup on the application form. Get a token at <a href="https://www.huduser.gov/hudapi/public/register" target="_blank">huduser.gov</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="haa_drs_webhook_url">Webhook URL (optional)</label></th>
                        <td>
                            <input type="url" id="haa_drs_webhook_url" name="haa_drs_webhook_url" value="<?php echo esc_attr( get_option( 'haa_drs_webhook_url', '' ) ); ?>" class="regular-text">
                            <p class="description">If set, each new submission sends JSON to this URL. Use with Power Automate to sync to Excel/SharePoint.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="haa_drs_rate_limit">Rate Limit (minutes)</label></th>
                        <td>
                            <input type="number" id="haa_drs_rate_limit" name="haa_drs_rate_limit" value="<?php echo intval( get_option( 'haa_drs_rate_limit', 15 ) ); ?>" min="1" max="120" class="small-text">
                            <p class="description">Minimum minutes between submissions from the same IP address.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }
}

class HAA_DRS_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'application',
            'plural'   => 'applications',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'application_id' => 'Application ID',
            'full_name'      => 'Applicant',
            'email'          => 'Email',
            'priority_tier'  => 'Priority',
            'status'         => 'Status',
            'created_at'     => 'Submitted',
        ];
    }

    public function get_sortable_columns() {
        return [
            'application_id' => [ 'application_id', false ],
            'full_name'      => [ 'full_name', false ],
            'priority_tier'  => [ 'priority_score', true ],
            'status'         => [ 'status', false ],
            'created_at'     => [ 'created_at', true ],
        ];
    }

    public function prepare_items() {
        $per_page = 20;
        $current  = $this->get_pagenum();

        $args = [
            'status'   => isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '',
            'search'   => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
            'orderby'  => isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at',
            'order'    => isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC',
            'per_page' => $per_page,
            'offset'   => ( $current - 1 ) * $per_page,
        ];

        $result = HAA_DRS_Database::query( $args );

        $this->items = $result['rows'];
        $this->set_pagination_args( [
            'total_items' => $result['total'],
            'per_page'    => $per_page,
        ] );

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    public function column_default( $item, $column_name ) {
        return esc_html( $item->$column_name ?? '' );
    }

    public function column_application_id( $item ) {
        $url = admin_url( 'admin.php?page=haa-drs&action=view&id=' . $item->id );
        return '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $item->application_id ) . '</strong></a>';
    }

    public function column_full_name( $item ) {
        $url = admin_url( 'admin.php?page=haa-drs&action=view&id=' . $item->id );
        return '<a href="' . esc_url( $url ) . '">' . esc_html( $item->full_name ) . '</a>';
    }

    public function column_priority_tier( $item ) {
        $colors = [
            'Tier 1' => '#e74c3c',
            'Tier 2' => '#f39c12',
            'Tier 3' => '#3498db',
            'Tier 4' => '#95a5a6',
        ];
        $color = '#95a5a6';
        foreach ( $colors as $prefix => $c ) {
            if ( strpos( $item->priority_tier, $prefix ) === 0 ) { $color = $c; break; }
        }
        $html = '<span class="haa-drs-badge" style="background:' . esc_attr( $color ) . ';">'
            . esc_html( $item->priority_tier )
            . ' <small>(' . intval( $item->priority_score ) . ')</small></span>';

        // Soft staff-review context flag — display only, never affects score/tier.
        if ( HAA_DRS_Database::should_flag_review( $item ) ) {
            $pill_title = sprintf(
                /* translators: %s is the SVI threshold, e.g. 0.50 */
                __( 'Top income bracket, low area vulnerability (SVI < %s), and minimal housing damage: context for staff review, not a scoring factor.', 'haa-drs' ),
                number_format( HAA_DRS_Database::REVIEW_SVI_THRESHOLD, 2 )
            );
            $html .= ' <span class="haa-drs-review-pill" title="' . esc_attr( $pill_title ) . '">'
                . esc_html__( 'Review context', 'haa-drs' ) . '</span>';
        }
        return $html;
    }

    public function column_status( $item ) {
        $labels = [
            'new'            => 'New',
            'under_review'   => 'Under Review',
            'needs_followup' => 'Needs Follow-up',
            'approved'       => 'Approved',
            'declined'       => 'Declined',
            'archived'       => 'Archived',
        ];
        $label = $labels[ $item->status ] ?? $item->status;
        return '<span class="haa-drs-status haa-drs-status--' . esc_attr( $item->status ) . '">' . esc_html( $label ) . '</span>';
    }

    public function column_created_at( $item ) {
        return esc_html( wp_date( 'M j, Y', strtotime( $item->created_at ) ) );
    }

    public function no_items() {
        echo 'No applications found.';
    }
}
