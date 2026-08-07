<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HAA_DRS_Database {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . HAA_DRS_TABLE;
    }

    public static function activate() {
        global $wpdb;
        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id                      BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id          VARCHAR(20)  NOT NULL,
            status                  VARCHAR(20)  NOT NULL DEFAULT 'new',
            priority_score          INT(11)      NOT NULL DEFAULT 0,
            priority_tier           VARCHAR(30)  NOT NULL DEFAULT '',

            /* their info */
            first_name              VARCHAR(255) NOT NULL DEFAULT '',
            last_name               VARCHAR(255) NOT NULL DEFAULT '',
            full_name               VARCHAR(255) NOT NULL DEFAULT '',
            address_1               VARCHAR(255) NOT NULL DEFAULT '',
            address_2               VARCHAR(255) NOT NULL DEFAULT '',
            city                    VARCHAR(100) NOT NULL DEFAULT 'Houston',
            state                   VARCHAR(2)   NOT NULL DEFAULT 'TX',
            zip                     VARCHAR(10)  NOT NULL DEFAULT '',
            phone                   VARCHAR(20)  NOT NULL DEFAULT '',
            email                   VARCHAR(255) NOT NULL DEFAULT '',
            dob                     DATE         NULL,
            preferred_language      VARCHAR(50)  NOT NULL DEFAULT 'English',
            emergency_contact_name  VARCHAR(255) NOT NULL DEFAULT '',
            emergency_contact_phone VARCHAR(20)  NOT NULL DEFAULT '',
            website                 VARCHAR(500) NOT NULL DEFAULT '',
            social_media            VARCHAR(500) NOT NULL DEFAULT '',
            cv_link                 VARCHAR(500) NOT NULL DEFAULT '',
            household_size          VARCHAR(20)  NOT NULL DEFAULT '',
            adults_count            INT(11)      NOT NULL DEFAULT 0,
            seniors_count           INT(11)      NOT NULL DEFAULT 0,
            children_count          INT(11)      NOT NULL DEFAULT 0,
            household_income        VARCHAR(32)  NOT NULL DEFAULT '',

            /* eligibility part */
            age_18_plus             VARCHAR(3)   NOT NULL DEFAULT '',
            houston_limits          VARCHAR(3)   NOT NULL DEFAULT '',
            county                  VARCHAR(50)  NOT NULL DEFAULT '',
            is_artist               VARCHAR(3)   NOT NULL DEFAULT '',
            artistic_discipline     VARCHAR(100) NOT NULL DEFAULT '',
            race_ethnicity          TEXT         NOT NULL,
            race_ethnicity_other    VARCHAR(255) NOT NULL DEFAULT '',
            svi_score               DECIMAL(4,2) NOT NULL DEFAULT 0.00,

            /* step 4 */
            disaster_event          VARCHAR(255) NOT NULL DEFAULT '',
            vulnerability_factors   TEXT         NOT NULL,
            vulnerability_factors_other VARCHAR(255) NOT NULL DEFAULT '',
            housing_status          INT(11)      NOT NULL DEFAULT 0,
            received_assistance     VARCHAR(3)   NOT NULL DEFAULT 'no',
            assistance_types        TEXT         NOT NULL,
            assistance_other        VARCHAR(255) NOT NULL DEFAULT '',
            fema_applied            VARCHAR(3)   NOT NULL DEFAULT 'no',
            sba_applied             VARCHAR(3)   NOT NULL DEFAULT 'no',
            external_support        TEXT         NOT NULL,
            external_support_other  VARCHAR(255) NOT NULL DEFAULT '',
            need_level              INT(11)      NOT NULL DEFAULT 0,
            current_needs           TEXT         NOT NULL,
            current_needs_other     VARCHAR(255) NOT NULL DEFAULT '',
            belongings_loss         INT(11)      NOT NULL DEFAULT 0,
            impact_statement        TEXT         NOT NULL,

            /* end consent part */
            consent_agreed          TINYINT(1)   NOT NULL DEFAULT 0,
            signature_name          VARCHAR(255) NOT NULL DEFAULT '',
            signature_date          DATETIME     NULL,

            /* admin etc */
            admin_notes             TEXT         NOT NULL,
            ip_address              VARCHAR(45)  NOT NULL DEFAULT '',
            created_at              DATETIME     NOT NULL,
            updated_at              DATETIME     NOT NULL,

            PRIMARY KEY  (id),
            UNIQUE KEY application_id (application_id),
            KEY status (status),
            KEY priority_tier (priority_tier),
            KEY created_at (created_at),
            KEY email (email(191))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'haa_drs_db_version', HAA_DRS_VERSION );
    }

    public static function next_application_id() {
        global $wpdb;
        $prefix = 'HAA-' . gmdate( 'ym' ) . '-';
        $last   = $wpdb->get_var( $wpdb->prepare(
            "SELECT application_id FROM %i WHERE application_id LIKE %s ORDER BY id DESC LIMIT 1",
            self::table(),
            $prefix . '%'
        ) );

        if ( $last ) {
            $seq = (int) substr( $last, -5 ) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad( $seq, 5, '0', STR_PAD_LEFT );
    }

    public static function insert( $data ) {
        global $wpdb;

        $data['created_at']     = current_time( 'mysql', true );
        $data['updated_at']     = current_time( 'mysql', true );

        // Calculate priority score
        $score = self::calculate_score( $data );
        $data['priority_score'] = $score['score'];
        $data['priority_tier']  = $score['tier'];

        $max_attempts = 3;
        for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
            $data['application_id'] = self::next_application_id();

            $wpdb->insert( self::table(), $data );

            if ( $wpdb->insert_id ) {
                $data['id'] = $wpdb->insert_id;
                self::fire_webhook( $data );
                return $data;
            }

            if ( strpos( $wpdb->last_error, 'Duplicate entry' ) === false ) {
                return false;
            }
        }

        return false;
    }

    public static function update( $id, $data ) {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql', true );
        return $wpdb->update( self::table(), $data, [ 'id' => $id ] );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM %i WHERE id = %d",
            self::table(),
            $id
        ) );
    }

    public static function query( $args = [] ) {
        global $wpdb;
        $table = self::table();

        $defaults = [
            'status'   => '',
            'tier'     => '',
            'search'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'per_page' => 20,
            'offset'   => 0,
        ];
        $args = wp_parse_args( $args, $defaults );

        $where = [ '1=1' ];
        $values = [];

        if ( $args['status'] ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }
        if ( $args['tier'] ) {
            $where[]  = 'priority_tier = %s';
            $values[] = $args['tier'];
        }
        if ( $args['search'] ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(full_name LIKE %s OR email LIKE %s OR application_id LIKE %s OR phone LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $allowed_orderby = [ 'created_at', 'priority_score', 'full_name', 'status', 'application_id' ];
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $where_sql = implode( ' AND ', $where );

        if ( ! empty( $values ) ) {
            $count_sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$values );
        } else {
            $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        }
        $total = (int) $wpdb->get_var( $count_sql );

        $limit_clause = sprintf( " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", (int) $args['per_page'], (int) $args['offset'] );
        if ( ! empty( $values ) ) {
            $rows_sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql}", ...$values ) . $limit_clause;
        } else {
            $rows_sql = "SELECT * FROM {$table} WHERE {$where_sql}" . $limit_clause;
        }
        $rows = $wpdb->get_results( $rows_sql );

        return [ 'total' => $total, 'rows' => $rows ];
    }

    public static function status_counts() {
        global $wpdb;
        $table = self::table();
        $rows  = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status" );
        $counts = [ 'all' => 0 ];
        foreach ( $rows as $row ) {
            $counts[ $row->status ] = (int) $row->cnt;
            $counts['all'] += (int) $row->cnt;
        }
        return $counts;
    }

    public static function calculate_score( $data ) {
        $score = 0;

        $hs = isset( $data['household_size'] ) ? $data['household_size'] : '';
        if ( $hs === '5_plus' ) $score += 2;
        elseif ( $hs === '3_4' ) $score += 1;

        $svi = isset( $data['svi_score'] ) ? floatval( $data['svi_score'] ) : 0;
        $score += (int) round( $svi * 10 );

        $vuln = isset( $data['vulnerability_factors'] ) ? json_decode( $data['vulnerability_factors'], true ) : [];
        if ( ! is_array( $vuln ) ) $vuln = [];
        $vc = count( $vuln );
        if ( $vc >= 5 ) $score += 5;
        elseif ( $vc >= 3 ) $score += 3;
        elseif ( $vc >= 1 ) $score += 2;

        $score += (int) ( $data['housing_status'] ?? 0 );

        $score += min( 3, (int) ( $data['need_level'] ?? 0 ) );

        $score += (int) ( $data['belongings_loss'] ?? 0 );

        if ( ( $data['received_assistance'] ?? 'no' ) === 'no' ) { $score += 2; }

        $needs = isset( $data['current_needs'] ) ? json_decode( $data['current_needs'], true ) : [];
        if ( ! is_array( $needs ) ) $needs = [];
        $nc = count( $needs );
        if ( $nc >= 8 ) $score += 3;
        elseif ( $nc >= 4 ) $score += 2;
        elseif ( $nc >= 1 ) $score += 1;

        if ( $score >= 24 ) $tier = 'Tier 1: Critical';
        elseif ( $score >= 15 ) $tier = 'Tier 2: High';
        elseif ( $score >= 8 )  $tier = 'Tier 3: Moderate';
        else                    $tier = 'Tier 4: Low';

        return [ 'score' => $score, 'tier' => $tier ];
    }

    const REVIEW_SVI_THRESHOLD = 0.50;

    /**
     * accepts either list table row obj or export row array
     *
     * @param object|array $row
     * @return bool
     */
    public static function should_flag_review( $row ) {
        $r = (array) $row;
        return (
            ( $r['household_income'] ?? '' ) === '$200,000 or more'
            && floatval( $r['svi_score'] ?? 0 ) < self::REVIEW_SVI_THRESHOLD
            && intval( $r['housing_status'] ?? 0 ) <= 1
        );
    }

    public static function is_rate_limited( $ip ) {
        $key = 'haa_drs_rate_' . md5( $ip );
        return (bool) get_transient( $key );
    }

    public static function set_rate_limit( $ip ) {
        $key     = 'haa_drs_rate_' . md5( $ip );
        $minutes = (int) get_option( 'haa_drs_rate_limit', 15 );
        set_transient( $key, 1, $minutes * MINUTE_IN_SECONDS );
    }

    private static function fire_webhook( $data ) {
        $url = get_option( 'haa_drs_webhook_url', '' );
        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return;
        }

        $safe = $data;
        unset( $safe['ip_address'] );
        unset( $safe['dob'] );
        unset( $safe['id'] );
        unset( $safe['updated_at'] );
        unset( $safe['admin_notes'] );

        if ( empty( $safe['status'] ) ) {
            $safe['status'] = 'new';
        }

        wp_remote_post( $url, [
            'timeout'  => 30,
            'blocking' => true,
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => wp_json_encode( $safe ),
        ] );
    }

    public static function get_all_for_export( $args = [] ) {
        global $wpdb;
        $table = self::table();

        $where  = [ '1=1' ];
        $values = [];

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        $where_sql = implode( ' AND ', $where );

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC", ...$values );
        } else {
            $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC";
        }

        return $wpdb->get_results( $sql, ARRAY_A );
    }
}
