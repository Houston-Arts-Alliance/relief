<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HAA_DRS_Export {

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'maybe_export' ] );
    }

    public static function maybe_export() {
        if ( ! isset( $_GET['page'], $_GET['export'] ) ) return;
        if ( $_GET['page'] !== 'haa-drs' || $_GET['export'] !== 'csv' ) return;
        if ( ! current_user_can( 'edit_posts' ) ) return;
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'haa_drs_export_csv' ) ) {
            wp_die( __( 'Security check failed.', 'haa-drs' ), 403 );
        }

        $args = [];
        if ( ! empty( $_GET['status'] ) ) {
            $args['status'] = sanitize_text_field( $_GET['status'] );
        }

        $rows = HAA_DRS_Database::get_all_for_export( $args );
        self::send_csv( $rows );
    }

    private static function send_csv( $rows ) {
        $filename = 'haa-drs-export-' . gmdate( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        $headers = [
            'Application ID', 'Status', 'Priority Score', 'Priority Tier',
            'First Name', 'Last Name', 'Full Name',
            'Address 1', 'Address 2', 'City', 'State', 'ZIP',
            'Phone', 'Email', 'Date of Birth', 'Preferred Language',
            'Race/Ethnicity', 'Race/Ethnicity Other',
            'Emergency Contact Name', 'Emergency Contact Phone',
            'Website', 'Social Media', 'CV Link',
            'Household Size', 'Adults', 'Seniors', 'Children', 'Household Income',
            'Age 18+', 'County', 'Is Artist', 'Discipline', 'SVI Score',
            'Disaster/Event',
            'Vulnerability Factors', 'Vulnerability Factors Other', 'Housing Status', 'Received Assistance',
            'Assistance Types', 'Assistance Other', 'FEMA Applied', 'SBA Applied',
            'External Support', 'External Support Other',
            'Belongings Loss', 'Need Level', 'Current Needs', 'Current Needs Other',
            'Impact Statement', 'Consent', 'Signature', 'Signature Date',
            'Admin Notes', 'Submitted', 'Updated', 'Review Context',
        ];
        fputcsv( $output, $headers );

        // this for data rows
        foreach ( $rows as $row ) {
            $race  = json_decode( $row['race_ethnicity'] ?? '[]', true );
            $vuln  = json_decode( $row['vulnerability_factors'] ?? '[]', true );
            $asst  = json_decode( $row['assistance_types'] ?? '[]', true );
            $ext   = json_decode( $row['external_support'] ?? '[]', true );
            $needs = json_decode( $row['current_needs'] ?? '[]', true );

            $hs_labels = [ '1_2' => '1-2', '3_4' => '3-4', '5_plus' => '5+' ];

            fputcsv( $output, [
                $row['application_id'],
                $row['status'],
                $row['priority_score'],
                $row['priority_tier'],
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $row['full_name'],
                $row['address_1'],
                $row['address_2'],
                $row['city'],
                $row['state'],
                $row['zip'],
                $row['phone'],
                $row['email'],
                $row['dob'],
                $row['preferred_language'],
                is_array( $race ) ? implode( '; ', $race ) : ( $row['race_ethnicity'] ?? '' ),
                $row['race_ethnicity_other'] ?? '',
                $row['emergency_contact_name'],
                $row['emergency_contact_phone'],
                $row['website'],
                $row['social_media'],
                $row['cv_link'],
                $hs_labels[ $row['household_size'] ] ?? $row['household_size'],
                $row['adults_count'],
                $row['seniors_count'],
                $row['children_count'],
                $row['household_income'] ?? '',
                $row['age_18_plus'],
                $row['county'],
                $row['is_artist'],
                $row['artistic_discipline'],
                $row['svi_score'],
                $row['disaster_event'] ?? '',
                is_array( $vuln ) ? implode( '; ', $vuln ) : '',
                $row['vulnerability_factors_other'] ?? '',
                $row['housing_status'],
                $row['received_assistance'],
                is_array( $asst ) ? implode( '; ', $asst ) : '',
                $row['assistance_other'],
                $row['fema_applied'],
                $row['sba_applied'],
                is_array( $ext ) ? implode( '; ', $ext ) : '',
                $row['external_support_other'],
                $row['belongings_loss'],
                $row['need_level'],
                is_array( $needs ) ? implode( '; ', $needs ) : '',
                $row['current_needs_other'] ?? '',
                $row['impact_statement'],
                $row['consent_agreed'] ? 'Yes' : 'No',
                $row['signature_name'],
                $row['signature_date'],
                $row['admin_notes'],
                $row['created_at'],
                $row['updated_at'],
                HAA_DRS_Database::should_flag_review( $row ) ? 'High income/low SVI/minimal damage' : '',
            ] );
        }

        fclose( $output );
        exit;
    }
}
