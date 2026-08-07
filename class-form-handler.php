<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HAA_DRS_Form_Handler {

    public static function init() {
        add_shortcode( 'haa_disaster_relief', [ __CLASS__, 'render_shortcode' ] );
        add_action( 'wp_ajax_haa_drs_submit', [ __CLASS__, 'handle_submit' ] );
        add_action( 'wp_ajax_nopriv_haa_drs_submit', [ __CLASS__, 'handle_submit' ] );
        add_action( 'wp_ajax_haa_drs_svi_lookup', [ __CLASS__, 'handle_svi_lookup' ] );
        add_action( 'wp_ajax_nopriv_haa_drs_svi_lookup', [ __CLASS__, 'handle_svi_lookup' ] );
        add_action( 'template_redirect', [ __CLASS__, 'maybe_exclude_from_cache' ] );
    }

    public static function maybe_exclude_from_cache() {
        if ( ! is_singular() ) {
            return;
        }
        $post = get_queried_object();
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        if ( ! has_shortcode( $post->post_content, 'haa_disaster_relief' ) ) {
            return;
        }

        if ( ! defined( 'DONOTCACHEPAGE' ) )   define( 'DONOTCACHEPAGE', true );
        if ( ! defined( 'DONOTCACHEOBJECT' ) ) define( 'DONOTCACHEOBJECT', true );

        nocache_headers();

        if ( ! headers_sent() ) {
            header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        }
    }

    /**
     * eligible ZIP codes for 9-county Greater HOU.
     * compiled from HUD USPS ZIP Code Crosswalk (ZIP to County) for county.
     * Source: https://www.huduser.gov/portal/datasets/usps_crosswalk.html
     * @return string[] Array of 5-digit ZIP strings.
     */
    public static function get_eligible_zips() {
        return [
            '77002','77003','77004','77005','77006','77007','77008','77009','77010','77011',
            '77012','77013','77014','77015','77016','77017','77018','77019','77020','77021',
            '77022','77023','77024','77025','77026','77027','77028','77029','77030','77031',
            '77032','77033','77034','77035','77036','77037','77038','77039','77040','77041',
            '77042','77043','77044','77045','77046','77047','77048','77049','77050','77051',
            '77053','77054','77055','77056','77057','77058','77059','77060','77061','77062',
            '77063','77064','77065','77066','77067','77068','77069','77070','77071','77072',
            '77073','77074','77075','77076','77077','77078','77079','77080','77081','77082',
            '77083','77084','77085','77086','77087','77088','77089','77090','77091','77092',
            '77093','77094','77095','77096','77098','77099','77201',
            '77301','77302','77303','77304','77306','77316','77318','77320','77327','77328',
            '77333','77336','77338','77339','77345','77346','77354','77355','77356',
            '77357','77362','77363','77365','77368','77369','77371',
            '77372','77373','77375','77377','77378','77379','77380','77381','77382','77384',
            '77385','77386','77387','77388','77389','77393','77396','77401',
            '77406','77407','77417','77418','77422','77423','77429','77430','77431','77433',
            '77434','77435','77441','77444','77445','77446','77447','77449','77450','77451',
            '77452','77459','77461','77463','77464','77466','77469','77471','77473','77474',
            '77476','77477','77478','77479','77480','77481','77484','77485','77486','77487',
            '77489','77493','77494','77497','77498',
            '77501','77502','77503','77504','77505','77506','77507','77508','77510','77511',
            '77512','77514','77515','77516','77517','77518','77520','77521','77523','77530',
            '77531','77532','77533','77534','77535','77536','77538','77539','77541','77542',
            '77545','77546','77547','77549','77550','77551','77552','77553','77554','77555',
            '77560','77561','77562','77563','77564','77565','77566','77568','77571','77572',
            '77573','77574','77575','77577','77578','77580','77581','77583','77584','77585',
            '77587','77588','77590','77591','77592','77597','77598','77613','77617','77623',
            '77650','77661','77665',
        ];
    }

    private static function enqueue() {
        wp_enqueue_style(
            'haa-drs-form',
            HAA_DRS_PLUGIN_URL . 'assets/css/form.css',
            [],
            HAA_DRS_VERSION
        );
        wp_enqueue_script(
            'haa-drs-form',
            HAA_DRS_PLUGIN_URL . 'assets/js/form.js',
            [],
            HAA_DRS_VERSION,
            true
        );
        wp_localize_script( 'haa-drs-form', 'haaDrs', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'haa_drs_submit' ),
            'eligibleZips'  => self::get_eligible_zips(),
            'countyOem'  => [
                'Austin'      => [ 'name' => 'Austin County Office of Emergency Management',       'url' => 'https://www.austincounty.com/page/austin.Emergency.Management' ],
                'Brazoria'    => [ 'name' => 'Brazoria County Office of Emergency Management',     'url' => 'https://www.brazoriacountytx.gov/departments/emergency-management' ],
                'Chambers'    => [ 'name' => 'Chambers County Office of Emergency Management',     'url' => 'https://www.chamberstx.gov/emergency-management' ],
                'Fort Bend'   => [ 'name' => 'Fort Bend County Office of Emergency Management',    'url' => 'https://www.fortbendcountytx.gov/government/departments/emergency-management-homeland-security' ],
                'Galveston'   => [ 'name' => 'Galveston County Office of Emergency Management',    'url' => 'https://www.galvestoncountytx.gov/government/departments/office-of-emergency-management' ],
                'Harris'      => [ 'name' => 'Harris County Office of Emergency Management',       'url' => 'https://www.readyharris.org/' ],
                'Liberty'     => [ 'name' => 'Liberty County Office of Emergency Management',      'url' => 'https://www.co.liberty.tx.us/page/liberty.Emergency.Management' ],
                'Montgomery'  => [ 'name' => 'Montgomery County Office of Emergency Management',   'url' => 'https://www.mctx.org/departments/departments_q_-_z/recover/' ],
                'Waller'      => [ 'name' => 'Waller County Office of Emergency Management',       'url' => 'https://www.wallercounty.us/EM' ],
            ],
        ] );
    }

    public static function render_shortcode() {
        self::enqueue();
        ob_start();
        self::render_form();
        return ob_get_clean();
    }

    private static function render_form() {
        ?>
        <div class="haa-drs" role="main">

            <header class="haa-drs-header" style="background:transparent !important; padding:24px 0 20px !important; text-align:left !important; border-radius:0 !important; margin:0 0 16px !important; position:relative !important; overflow:hidden !important;">
                <h1 class="haa-drs-title" style="color:#04163F !important; display:flex !important; align-items:center !important; flex-wrap:wrap !important; gap:0.42em !important; font-size:clamp(21px, 4.5vw, 40px) !important; font-weight:600 !important; margin:0 !important; letter-spacing:-0.015em !important; line-height:1.1 !important; font-family:'RoobertPro',-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif !important; padding:0 !important; background:none !important;"><span>SOAR</span><span aria-hidden="true" style="display:inline-block !important; width:2px !important; height:0.7em !important; background:#F0C148 !important; flex-shrink:0 !important;"></span><span>Supporting Our Arts Recovery</span></h1>
                <hr style="width:100% !important; height:2px !important; background:#F0C148 !important; border:none !important; margin:8px 0 !important; padding:0 !important;">
                <p class="haa-drs-fund" style="color:#04163F !important; font-size:14px !important; font-weight:500 !important; margin:0 !important; letter-spacing:-0.005em !important; line-height:1.4 !important; text-transform:none !important; font-family:'RoobertPro',-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif !important; padding:0 !important; background:none !important;">Houston Arts Alliance Emergency Relief Assistance</p>
            </header>

            <div class="haa-drs-progress-wrap">
                <ol class="haa-drs-steps-nav" role="list">
                    <li class="haa-drs-step-indicator is-active" data-step="1">
                        <span class="haa-drs-step-num" aria-hidden="true">1</span>
                        <span class="haa-drs-step-label">Welcome</span>
                    </li>
                    <li class="haa-drs-step-indicator" data-step="2">
                        <span class="haa-drs-step-num" aria-hidden="true">2</span>
                        <span class="haa-drs-step-label">Eligibility</span>
                    </li>
                    <li class="haa-drs-step-indicator" data-step="3">
                        <span class="haa-drs-step-num" aria-hidden="true">3</span>
                        <span class="haa-drs-step-label">Your Info</span>
                    </li>
                    <li class="haa-drs-step-indicator" data-step="4">
                        <span class="haa-drs-step-num" aria-hidden="true">4</span>
                        <span class="haa-drs-step-label">Assessment</span>
                    </li>
                </ol>
            </div>

            <form id="haa-drs-form" class="haa-drs-form" method="post" novalidate>

                <div class="haa-drs-hp" aria-hidden="true" tabindex="-1">
                    <label for="haa_drs_website_url">Leave this empty</label>
                    <input type="text" name="haa_drs_website_url" id="haa_drs_website_url" autocomplete="off" tabindex="-1">
                </div>

                <fieldset class="haa-drs-step is-active" id="haa-drs-step-1">
                    <legend class="haa-drs-step-title">About This Program</legend>

                    <div class="haa-drs-card">
                        <p>The Houston Arts Alliance SOAR program supports individual artists and creatives in Greater Houston recovering from disasters and major disruptions that significantly impact their stability and recovery.</p>
                        <p>The program provides limited, one-time emergency financial assistance to help address immediate needs caused by an emergency or disaster. Award amounts are determined through a needs-based assessment and are not intended to cover the full extent of needs. Because funding is subject to availability, not all eligible applicants may receive assistance.</p>
                        <p>We encourage all applicants to also explore local, state, and federal disaster resources. HAA staff may be able to help connect you with additional support through partner organizations in the region. <a href="#" class="haa-drs-modal-trigger" data-modal="haa-drs-resources-modal">View disaster resources</a></p>

                        <h3 class="haa-drs-h3">Who Can Apply</h3>
                        <ul class="haa-drs-checklist" role="list">
                            <li>Individual artists and creatives aged 18 or older.</li>
                            <li>Residents within the Greater Houston area.</li>
                            <li>Those who have experienced disaster-related hardship.</li>
                        </ul>

                        <div class="haa-drs-callout">
                            <strong>Before you begin:</strong> This application takes approximately 10&ndash;15 minutes. Your progress is saved automatically in your browser, so you can close and return if needed.
                        </div>
                    </div>

                    <div class="haa-drs-actions">
                        <span></span>
                        <button type="button" class="haa-drs-btn haa-drs-btn--primary" data-action="next">
                            Begin Application
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                </fieldset>

                <fieldset class="haa-drs-step" id="haa-drs-step-2" hidden>
                    <legend class="haa-drs-step-title">Eligibility</legend>

                    <div class="haa-drs-card">
                        <div class="haa-drs-field">
                            <label for="haa_county" class="haa-drs-label">Which Greater Houston county do you live in? <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <p class="haa-drs-hint">If temporarily displaced, select your primary residence county. <a href="https://texascountiesdeliver.org/mycounty/" target="_blank" rel="noopener" class="haa-drs-ext-link">Look up your county<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a></p>
                            <select id="haa_county" name="county" required class="haa-drs-select">
                                <option value="">Select a county&hellip;</option>
                                <option value="Austin">Austin</option>
                                <option value="Brazoria">Brazoria</option>
                                <option value="Chambers">Chambers</option>
                                <option value="Fort Bend">Fort Bend</option>
                                <option value="Galveston">Galveston</option>
                                <option value="Harris">Harris</option>
                                <option value="Liberty">Liberty</option>
                                <option value="Montgomery">Montgomery</option>
                                <option value="Waller">Waller</option>
                                <option value="none">My county is not listed</option>
                            </select>
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <fieldset class="haa-drs-group">
                            <legend class="haa-drs-h3">Are you an artist or creative? <abbr title="required" class="haa-drs-req">*</abbr></legend>
                            <div class="haa-drs-options">
                                <label class="haa-drs-option-card">
                                    <input type="radio" name="is_artist" value="yes" required>
                                    <span>Yes</span>
                                </label>
                                <label class="haa-drs-option-card">
                                    <input type="radio" name="is_artist" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                            <span class="haa-drs-error" role="alert"></span>
                        </fieldset>
                    </div>

                    <div class="haa-drs-card">
                        <fieldset class="haa-drs-group">
                            <legend class="haa-drs-h3">Are you at least 18 years old? <abbr title="required" class="haa-drs-req">*</abbr></legend>
                            <div class="haa-drs-options">
                                <label class="haa-drs-option-card">
                                    <input type="radio" name="age_18_plus" value="yes" required>
                                    <span>Yes</span>
                                </label>
                                <label class="haa-drs-option-card">
                                    <input type="radio" name="age_18_plus" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                            <span class="haa-drs-error" role="alert"></span>
                        </fieldset>
                    </div>

                    <div class="haa-drs-actions">
                        <button type="button" class="haa-drs-btn haa-drs-btn--secondary" data-action="prev">Back</button>
                        <button type="button" class="haa-drs-btn haa-drs-btn--primary" data-action="next">Continue</button>
                    </div>
                </fieldset>

                <div class="haa-drs-step haa-drs-ineligible" id="haa-drs-ineligible" hidden>
                    <div class="haa-drs-card" style="border-left:4px solid var(--haa-gold);">
                        <h2 style="color:var(--haa-navy);margin:0 0 22px;font-size:22px;">We're Unable to Confirm Your Eligibility</h2>
                        <p>Based on your responses, we're unable to confirm your eligibility for the Houston Arts Alliance SOAR program. This program serves artists and creatives aged 18 or older who reside within Greater Houston. We understand you may be going through a difficult time, so even if you're not eligible for this fund, the following resources may be able to help.</p>

                        <div class="haa-drs-resource-group">
                            <h3 class="haa-drs-resource-subheading">Apply for Assistance</h3>
                            <ul class="haa-drs-resource-list">
                                <li><a href="https://www.disasterassistance.gov/" target="_blank" rel="noopener" class="haa-drs-ext-link">DisasterAssistance.gov<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Federal disaster assistance and FEMA applications</span></li>
                                <li id="haa-drs-county-oem-item"><a href="https://tdem.texas.gov/" target="_blank" rel="noopener" class="haa-drs-ext-link" id="haa-drs-county-oem-link">Texas Division of Emergency Management<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Local emergency management office</span></li>
                            </ul>
                        </div>

                        <div class="haa-drs-resource-group">
                            <h3 class="haa-drs-resource-subheading">Report Damage</h3>
                            <ul class="haa-drs-resource-list">
                                <li><a href="https://damage.tdem.texas.gov/" target="_blank" rel="noopener" class="haa-drs-ext-link">Texas TDEM Damage Survey<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Self-report property damage to state officials</span></li>
                                <li><a href="https://houstontx.gov/311/" target="_blank" rel="noopener" class="haa-drs-ext-link">Houston 311<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Call 311 to report storm debris, street flooding, or drainage issues in the City of Houston</span></li>
                            </ul>
                        </div>

                        <div class="haa-drs-resource-group">
                            <h3 class="haa-drs-resource-subheading">Additional Resources</h3>
                            <ul class="haa-drs-resource-list">
                                <li><a href="https://stear.texas.gov/" target="_blank" rel="noopener" class="haa-drs-ext-link">State of Texas Emergency Assistance Registry (STEAR)<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">For people with disabilities or medical needs</span></li>
                                <li><a href="https://tracker.centerpointenergy.com/map/texas" target="_blank" rel="noopener" class="haa-drs-ext-link">CenterPoint Energy Outage Tracker<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Check power outages across the region</span></li>
                                <li><a href="https://www.ready.gov/" target="_blank" rel="noopener" class="haa-drs-ext-link">FEMA Ready.gov<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Resources for emergencies and disasters</span></li>
                            </ul>
                        </div>

                        <p style="margin-top:1.25rem;color:var(--haa-text-muted);">If you believe you received this message in error, you may go back and review your answers.</p>
                        <div class="haa-drs-actions" style="margin-top:1.25rem;">
                            <button type="button" class="haa-drs-btn haa-drs-btn--secondary" id="haa-drs-ineligible-back">Review My Answers</button>
                        </div>
                    </div>
                </div>

                <fieldset class="haa-drs-step" id="haa-drs-step-3" hidden>
                    <legend class="haa-drs-step-title">Your Information</legend>

                    <div class="haa-drs-card">
                        <div class="haa-drs-row haa-drs-row--2">
                            <div class="haa-drs-field">
                                <label for="haa_first_name" class="haa-drs-label">First Name <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <input type="text" id="haa_first_name" name="first_name" required autocomplete="given-name" class="haa-drs-input">
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                            <div class="haa-drs-field">
                                <label for="haa_last_name" class="haa-drs-label">Last Name <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <input type="text" id="haa_last_name" name="last_name" required autocomplete="family-name" class="haa-drs-input">
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                        </div>

                        <div class="haa-drs-field">
                            <label for="haa_address_1" class="haa-drs-label">Street Address <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <input type="text" id="haa_address_1" name="address_1" required autocomplete="address-line1" class="haa-drs-input" placeholder="123 Main Street">
                            <span class="haa-drs-error" role="alert"></span>
                        </div>

                        <div class="haa-drs-field">
                            <label for="haa_address_2" class="haa-drs-label">Address Line 2 <span class="haa-drs-optional">(optional)</span></label>
                            <input type="text" id="haa_address_2" name="address_2" autocomplete="address-line2" class="haa-drs-input" placeholder="Apt, Suite, Unit, etc.">
                        </div>

                        <div class="haa-drs-row haa-drs-row--3">
                            <div class="haa-drs-field">
                                <label for="haa_city" class="haa-drs-label">City <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <input type="text" id="haa_city" name="city" required autocomplete="address-level2" class="haa-drs-input">
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                            <div class="haa-drs-field">
                                <label for="haa_state" class="haa-drs-label">State <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <select id="haa_state" name="state" required autocomplete="address-level1" class="haa-drs-select">
                                    <option value="TX" selected>Texas</option>
                                </select>
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                            <div class="haa-drs-field">
                                <label for="haa_zip" class="haa-drs-label">ZIP Code <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <input type="text" id="haa_zip" name="zip" required pattern="[0-9]{5}" maxlength="5" inputmode="numeric" autocomplete="postal-code" class="haa-drs-input">
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                        </div>

                        <div class="haa-drs-row haa-drs-row--2">
                            <div class="haa-drs-field">
                                <label for="haa_phone" class="haa-drs-label">Phone Number <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <input type="tel" id="haa_phone" name="phone" required autocomplete="tel" class="haa-drs-input">
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                            <div class="haa-drs-field">
                                <label for="haa_email" class="haa-drs-label">Email Address <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <input type="email" id="haa_email" name="email" required autocomplete="email" class="haa-drs-input">
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                        </div>

                        <div class="haa-drs-row haa-drs-row--2">
                            <div class="haa-drs-field">
                                <label for="haa_dob" class="haa-drs-label">Date of Birth <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <input type="date" id="haa_dob" name="dob" required class="haa-drs-input">
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                            <div class="haa-drs-field">
                                <label for="haa_preferred_language" class="haa-drs-label">Preferred Language <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <select id="haa_preferred_language" name="preferred_language" required class="haa-drs-select">
                                    <option value="English" selected>English</option>
                                    <option value="Spanish">Spanish</option>
                                    <option value="Vietnamese">Vietnamese</option>
                                    <option value="Chinese">Chinese</option>
                                    <option value="Arabic">Arabic</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="haa-drs-field haa-drs-conditional" id="haa-language-other-wrap" hidden>
                            <label for="haa_preferred_language_other" class="haa-drs-label">Please specify your preferred language <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <input type="text" id="haa_preferred_language_other" name="preferred_language_other" class="haa-drs-input">
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <fieldset class="haa-drs-group">
                            <legend class="haa-drs-h3">Race and Ethnicity <abbr title="required" class="haa-drs-req">*</abbr></legend>
                            <p class="haa-drs-hint">What is your race and/or ethnicity? Select all that apply.</p>
                            <div class="haa-drs-checkbox-grid">
                                <?php
                                $race_options = [
                                    'American Indian or Alaska Native',
                                    'Asian',
                                    'Black or African American',
                                    'Hispanic or Latino',
                                    'Middle Eastern or North African',
                                    'Native Hawaiian or Pacific Islander',
                                    'White',
                                    'A race or ethnicity not listed here',
                                    'Prefer not to answer',
                                ];
                                foreach ( $race_options as $option ) :
                                    $needs_describe = in_array( $option, [
                                        'American Indian or Alaska Native',
                                        'A race or ethnicity not listed here',
                                    ], true );
                                    $is_prefer_not = ( $option === 'Prefer not to answer' );
                                    $classes = 'haa-race-check';
                                    if ( $needs_describe ) $classes .= ' haa-race-describe';
                                ?>
                                <label class="haa-drs-checkbox-card">
                                    <input type="checkbox" name="race_ethnicity[]" value="<?php echo esc_attr( $option ); ?>" class="<?php echo esc_attr( $classes ); ?>"<?php echo $is_prefer_not ? ' id="haa-race-prefer-not"' : ''; ?>>
                                    <span><?php echo esc_html( $option ); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <span class="haa-drs-error" id="haa-race-error" role="alert"></span>
                        </fieldset>
                        <div class="haa-drs-field haa-drs-conditional" id="haa-ethnicity-other-wrap" hidden>
                            <label for="haa_race_ethnicity_other" class="haa-drs-label">Please describe <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <input type="text" id="haa_race_ethnicity_other" name="race_ethnicity_other" class="haa-drs-input">
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <h3 class="haa-drs-h3">Emergency Contact <span class="haa-drs-optional">(optional)</span></h3>
                        <p class="haa-drs-hint">Someone we can reach if we&rsquo;re unable to contact you.</p>
                        <div class="haa-drs-row haa-drs-row--2">
                            <div class="haa-drs-field">
                                <label for="haa_emergency_contact_name" class="haa-drs-label">Contact Name</label>
                                <input type="text" id="haa_emergency_contact_name" name="emergency_contact_name" class="haa-drs-input">
                            </div>
                            <div class="haa-drs-field">
                                <label for="haa_emergency_contact_phone" class="haa-drs-label">Contact Phone</label>
                                <input type="tel" id="haa_emergency_contact_phone" name="emergency_contact_phone" class="haa-drs-input">
                            </div>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <h3 class="haa-drs-h3">Online Presence</h3>
                        <p class="haa-drs-hint">Share at least one link so we can learn about your creative practice.</p>
                        <div class="haa-drs-field">
                            <label for="haa_website" class="haa-drs-label">Website or Portfolio</label>
                            <input type="url" id="haa_website" name="website" autocomplete="url" class="haa-drs-input" placeholder="https://www.yoursite.com">
                        </div>
                        <div class="haa-drs-field">
                            <label for="haa_social_media" class="haa-drs-label">Social Media Profile</label>
                            <input type="url" id="haa_social_media" name="social_media" class="haa-drs-input" placeholder="https://instagram.com/yourhandle">
                        </div>
                        <div class="haa-drs-field">
                            <label for="haa_cv_link" class="haa-drs-label">CV or Resume Link</label>
                            <input type="url" id="haa_cv_link" name="cv_link" class="haa-drs-input" placeholder="https://drive.google.com/your-cv">
                        </div>
                        <span class="haa-drs-error" id="haa-online-presence-error" role="alert"></span>
                    </div>

                    <div class="haa-drs-card haa-drs-conditional" id="haa-discipline-question" hidden>
                        <div class="haa-drs-field">
                            <label for="haa_artistic_discipline" class="haa-drs-label">Primary Artistic Discipline <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <select id="haa_artistic_discipline" name="artistic_discipline" class="haa-drs-select">
                                <option value="">Select a discipline&hellip;</option>
                                <option>Art + Social Practice</option>
                                <option>Craft</option>
                                <option>Dance</option>
                                <option>Design</option>
                                <option>Film</option>
                                <option>Literary Arts</option>
                                <option>Media Arts</option>
                                <option>Multidisciplinary</option>
                                <option>Museum</option>
                                <option>Music</option>
                                <option>Performance Art</option>
                                <option>Performing Arts</option>
                                <option>Preservation</option>
                                <option>Public Art</option>
                                <option>Theater</option>
                                <option>Visual Arts</option>
                            </select>
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <fieldset class="haa-drs-group">
                            <legend class="haa-drs-h3">Household Size <abbr title="required" class="haa-drs-req">*</abbr></legend>
                            <p class="haa-drs-hint">Include only those who share income and expenses with you, plus any dependents (such as children or elderly family members) in your care.</p>
                            <div class="haa-drs-options">
                                <label class="haa-drs-option-card">
                                    <input type="radio" name="household_size" value="1_2" required>
                                    <span>1&ndash;2 people</span>
                                </label>
                                <label class="haa-drs-option-card">
                                    <input type="radio" name="household_size" value="3_4">
                                    <span>3&ndash;4 people</span>
                                </label>
                                <label class="haa-drs-option-card">
                                    <input type="radio" name="household_size" value="5_plus">
                                    <span>5 or more</span>
                                </label>
                            </div>
                            <span class="haa-drs-error" role="alert"></span>
                        </fieldset>

                        <div class="haa-drs-conditional" id="haa-household-breakdown" hidden>
                            <p class="haa-drs-hint" style="margin-top:14px !important;">Breakdown (optional):</p>
                            <div class="haa-drs-row haa-drs-row--3">
                                <div class="haa-drs-field">
                                    <label for="haa_adults" class="haa-drs-label">Adults 18&ndash;64</label>
                                    <input type="number" id="haa_adults" name="adults_count" min="0" value="0" class="haa-drs-input" inputmode="numeric">
                                </div>
                                <div class="haa-drs-field">
                                    <label for="haa_seniors" class="haa-drs-label">Older adults 65+</label>
                                    <input type="number" id="haa_seniors" name="seniors_count" min="0" value="0" class="haa-drs-input" inputmode="numeric">
                                </div>
                                <div class="haa-drs-field">
                                    <label for="haa_children" class="haa-drs-label">Children under 18</label>
                                    <input type="number" id="haa_children" name="children_count" min="0" value="0" class="haa-drs-input" inputmode="numeric">
                                </div>
                            </div>
                            <span class="haa-drs-error" id="haa-breakdown-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <div class="haa-drs-field">
                            <label for="haa_household_income" class="haa-drs-label">Household Income (annual, before taxes) <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <select id="haa_household_income" name="household_income" required class="haa-drs-select">
                                <option value="">Select&hellip;</option>
                                <option value="Less than $25,000">Less than $25,000</option>
                                <option value="$25,000 to $49,999">$25,000 to $49,999</option>
                                <option value="$50,000 to $74,999">$50,000 to $74,999</option>
                                <option value="$75,000 to $99,999">$75,000 to $99,999</option>
                                <option value="$100,000 to $149,999">$100,000 to $149,999</option>
                                <option value="$150,000 to $199,999">$150,000 to $199,999</option>
                                <option value="$200,000 or more">$200,000 or more</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <h3 class="haa-drs-h3">Social Vulnerability Index (SVI) <abbr title="required" class="haa-drs-req">*</abbr></h3>
                        <p class="haa-drs-hint">Enter your ZIP code to look up the CDC Social Vulnerability Index for your area.</p>

                        <div class="haa-drs-svi-lookup">
                            <div class="haa-drs-svi-input-row">
                                <label for="haa_svi_zip" class="haa-drs-label">ZIP Code</label>
                                <div class="haa-drs-svi-input-group">
                                    <input type="text" id="haa_svi_zip" maxlength="5" pattern="\d{5}" placeholder="e.g. 77007" class="haa-drs-input" inputmode="numeric">
                                    <button type="button" id="haa-svi-lookup-btn" class="haa-drs-btn haa-drs-btn--primary haa-drs-svi-btn">
                                        <span class="haa-svi-btn-text">Look Up Score</span>
                                        <span class="haa-svi-btn-loading" style="display:none;">
                                            <svg class="haa-svi-spinner" width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="36 12" /></svg>
                                            Looking up&hellip;
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <span class="haa-drs-error" id="haa-svi-lookup-error" role="alert"></span>

                            <div class="haa-drs-svi-result" id="haa-svi-result" style="display:none;" role="region" aria-live="polite">

                                <div class="haa-drs-svi-result-hero">
                                    <span class="haa-drs-svi-result-eyebrow">Overall SVI Score</span>
                                    <span class="haa-drs-svi-result-score" id="haa-svi-result-score"></span>
                                    <span class="haa-drs-svi-result-level" id="haa-svi-result-level"></span>
                                </div>

                                <div class="haa-drs-svi-themes" id="haa-svi-themes"></div>

                                <div class="haa-drs-svi-result-footer">
                                    <p>Possible scores range from 0 to 1 (lowest to highest vulnerability). Based on the most recent CDC/ATSDR SVI update (May 2024). <a href="https://www.atsdr.cdc.gov/place-health/php/svi/index.html" target="_blank" rel="noopener noreferrer" class="haa-drs-ext-link">Full documentation<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a></p>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="haa_svi_score" name="svi_score" value="">
                        <input type="hidden" id="haa_svi_payload" name="svi_payload" value="">
                        <span class="haa-drs-error" id="haa-svi-score-error" role="alert"></span>
                    </div>

                    <div class="haa-drs-actions">
                        <button type="button" class="haa-drs-btn haa-drs-btn--secondary" data-action="prev">Back</button>
                        <button type="button" class="haa-drs-btn haa-drs-btn--primary" data-action="next">Continue</button>
                    </div>
                </fieldset>

                <fieldset class="haa-drs-step" id="haa-drs-step-4" hidden>
                    <legend class="haa-drs-step-title">Impact Assessment</legend>

                    <div class="haa-drs-card">
                        <fieldset class="haa-drs-group">
                            <legend class="haa-drs-h3">Vulnerability Factors</legend>
                            <p class="haa-drs-hint">Select all that apply. Keep in mind that these factors come from public health research on how disasters affect different households, and they help us understand your circumstances, not assess you or your home.</p>
                            <div class="haa-drs-checkbox-grid">
                                <?php
                                $factors = [
                                    'Person with a disability',
                                    'Age 65 or older',
                                    'Pregnant or nursing',
                                    'Chronic illness',
                                    'Single parent household',
                                    'No income or employment',
                                    'Other vulnerability not listed',
                                ];
                                foreach ( $factors as $factor ) :
                                    $is_vuln_other = ( $factor === 'Other vulnerability not listed' );
                                ?>
                                <label class="haa-drs-checkbox-card">
                                    <input type="checkbox" name="vulnerability_factors[]" value="<?php echo esc_attr( $factor ); ?>"<?php echo $is_vuln_other ? ' class="haa-vuln-describe"' : ''; ?>>
                                    <span><?php echo esc_html( $factor ); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="haa-drs-field haa-drs-conditional" id="haa-vuln-other-wrap" hidden>
                                <label for="haa_vulnerability_factors_other" class="haa-drs-label">Please specify <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <input type="text" id="haa_vulnerability_factors_other" name="vulnerability_factors_other" class="haa-drs-input">
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                        </fieldset>
                    </div>

                    <div class="haa-drs-card">
                        <div class="haa-drs-field">
                            <label for="haa_housing_status" class="haa-drs-label">Current Housing Situation <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <p class="haa-drs-hint">Select the option that best describes your situation right now.</p>
                            <select id="haa_housing_status" name="housing_status" required class="haa-drs-select">
                                <option value="">Select&hellip;</option>
                                <option value="4">Uninhabitable: destroyed or structurally unsafe</option>
                                <option value="3">Not currently habitable: utility outage or quarantine</option>
                                <option value="2">Habitable with major damage: needs significant repairs</option>
                                <option value="1">Habitable with minor damage: needs minor repairs</option>
                                <option value="0">No damage: fully habitable</option>
                            </select>
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <fieldset class="haa-drs-group">
                            <legend class="haa-drs-h3">Assistance Received <abbr title="required" class="haa-drs-req">*</abbr></legend>
                            <p class="haa-drs-hint">Have you received any assistance since this disaster began?</p>
                            <div class="haa-drs-options">
                                <label class="haa-drs-option-card">
                                    <input type="radio" name="received_assistance" value="yes">
                                    <span>Yes</span>
                                </label>
                                <label class="haa-drs-option-card">
                                    <input type="radio" name="received_assistance" value="no">
                                    <span>No</span>
                                </label>
                            </div>
                            <span class="haa-drs-error" role="alert"></span>
                        </fieldset>

                        <div class="haa-drs-conditional" id="haa-assistance-details" hidden>
                            <fieldset class="haa-drs-group" style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid #E3E0DA;">
                                <legend style="font-size:14px !important; font-weight:500 !important; color:#6B6B80 !important; margin-bottom:6px !important;">What types of support? (select all that apply)</legend>
                                <div class="haa-drs-checkbox-grid haa-drs-checkbox-grid--compact">
                                    <label class="haa-drs-checkbox-card"><input type="checkbox" name="assistance_types[]" value="Food and/or water"><span>Food / water</span></label>
                                    <label class="haa-drs-checkbox-card"><input type="checkbox" name="assistance_types[]" value="Housing or shelter"><span>Housing / shelter</span></label>
                                    <label class="haa-drs-checkbox-card"><input type="checkbox" name="assistance_types[]" value="Cash or financial assistance"><span>Cash / financial</span></label>
                                    <label class="haa-drs-checkbox-card"><input type="checkbox" name="assistance_types[]" value="Other"><span>Other</span></label>
                                </div>
                            </fieldset>
                            <div class="haa-drs-field haa-drs-conditional" id="haa-assist-other-wrap" hidden>
                                <label for="haa_assistance_other" class="haa-drs-label">Please specify</label>
                                <input type="text" id="haa_assistance_other" name="assistance_other" class="haa-drs-input">
                            </div>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <h3 class="haa-drs-h3">Federal Disaster Assistance Applications</h3>
                        <p class="haa-drs-hint">These questions will not affect your application. We only ask to better understand what other help our applicants have received. FEMA and SBA disaster programs are only available when a disaster is officially declared, which may not apply to every emergency.</p>
                        <div class="haa-drs-row haa-drs-row--2" style="margin-top:1rem;">
                            <div class="haa-drs-field">
                                <label for="haa_fema" class="haa-drs-label">Applied for FEMA assistance? <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <select id="haa_fema" name="fema_applied" required class="haa-drs-select">
                                    <option value="">Select&hellip;</option>
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                            <div class="haa-drs-field">
                                <label for="haa_sba" class="haa-drs-label">Applied for SBA loan/assistance? <abbr title="required" class="haa-drs-req">*</abbr></label>
                                <select id="haa_sba" name="sba_applied" required class="haa-drs-select">
                                    <option value="">Select&hellip;</option>
                                    <option value="no">No</option>
                                    <option value="yes">Yes</option>
                                </select>
                                <span class="haa-drs-error" role="alert"></span>
                            </div>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <div class="haa-drs-field">
                            <label for="haa_need_level" class="haa-drs-label">How much assistance do you still need? <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <p class="haa-drs-hint">Considering any help you&rsquo;ve already received.</p>
                            <select id="haa_need_level" name="need_level" required class="haa-drs-select">
                                <option value="">Select&hellip;</option>
                                <option value="3">Major: basic needs won't be met within 7 days</option>
                                <option value="2">Moderate: important gaps remain</option>
                                <option value="1">Minor: most needs covered, small gaps remain</option>
                                <option value="0">Covered: needs are met right now</option>
                            </select>
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <div class="haa-drs-field">
                            <label for="haa_belongings_loss" class="haa-drs-label">Household Losses and Disruptions (includes belongings, food, and essentials) <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <select id="haa_belongings_loss" name="belongings_loss" required class="haa-drs-select">
                                <option value="">Select&hellip;</option>
                                <option value="4">Severe loss: most belongings, food, or essential items are lost, destroyed, or unusable</option>
                                <option value="3">Moderate loss: a significant portion of belongings, food, or essentials is lost or damaged</option>
                                <option value="2">Minor loss: some items are lost or damaged, but most essentials remain</option>
                                <option value="0">No loss: belongings, food, and essentials are intact</option>
                                <option value="1">Unable to assess yet: I cannot safely access the area or determine the impact</option>
                            </select>
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <fieldset class="haa-drs-group">
                            <legend class="haa-drs-h3">What do you need right now? <abbr title="required" class="haa-drs-req">*</abbr></legend>
                            <p class="haa-drs-hint">Select all that apply.</p>
                            <div class="haa-drs-checkbox-grid">
                                <?php
                                $needs = [
                                    'Child/infant supplies', 'Clothing', 'Debris removal/cleanup',
                                    'Documentation/ID replacement', 'Essential equipment',
                                    'Food and/or water', 'Housing/safe shelter', 'Home repairs (major)',
                                    'Home repairs (minor)', 'Household essentials', 'Hygiene/sanitation',
                                    'Language support', 'Medical care/medications', 'Mental health support',
                                    'Transportation', 'Utilities support',
                                ];
                                foreach ( $needs as $need ) :
                                ?>
                                <label class="haa-drs-checkbox-card">
                                    <input type="checkbox" name="current_needs[]" value="<?php echo esc_attr( $need ); ?>" class="haa-need-item">
                                    <span><?php echo esc_html( $need ); ?></span>
                                </label>
                                <?php endforeach; ?>
                                <label class="haa-drs-checkbox-card">
                                    <input type="checkbox" name="current_needs[]" value="Other" class="haa-need-item haa-need-describe">
                                    <span>Other</span>
                                </label>
                                <label class="haa-drs-checkbox-card">
                                    <input type="checkbox" name="current_needs[]" value="None of these right now" id="haa-no-needs" class="haa-need-item">
                                    <span>None of these right now</span>
                                </label>
                            </div>
                            <span class="haa-drs-error" role="alert"></span>
                        </fieldset>
                        <div class="haa-drs-field haa-drs-conditional" id="haa-needs-other-wrap" hidden>
                            <label for="haa_current_needs_other" class="haa-drs-label">Please describe <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <input type="text" id="haa_current_needs_other" name="current_needs_other" class="haa-drs-input">
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-card">
                        <div class="haa-drs-field">
                            <label for="haa_impact_statement" class="haa-drs-label">Impact Statement <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <p class="haa-drs-hint">Describe how this disaster has affected you, your household, and your creative practice. Include details about damage, displacement, lost income, or other hardships.</p>
                            <textarea id="haa_impact_statement" name="impact_statement" rows="5" maxlength="1800" required class="haa-drs-textarea" style="resize:vertical !important;" placeholder="Take your time. There are no wrong answers."></textarea>
                            <span class="haa-drs-error" role="alert"></span>
                            <div class="haa-drs-word-count" aria-live="polite"><span id="haa-word-count">0</span> / 250 words</div>
                        </div>
                    </div>

                    <div class="haa-drs-card haa-drs-consent">
                        <h3 class="haa-drs-h3">Consent and Authorization</h3>
                        <p>I confirm that the details provided are accurate to the best of my knowledge. I understand that submission does not guarantee assistance. I authorize Houston Arts Alliance to share information from this submission with aid organizations and government relief agencies if doing so may help connect me with additional assistance. Providing false or misleading information in this application may result in disqualification or other consequences.</p>

                        <label class="haa-drs-checkbox-card haa-drs-consent-check">
                            <input type="checkbox" name="consent_agreed" id="haa_consent" value="1" required>
                            <span>I have read and agree to the above statement <abbr title="required" class="haa-drs-req">*</abbr></span>
                        </label>
                        <span class="haa-drs-error" role="alert"></span>

                        <div class="haa-drs-field" style="margin-top:1.25rem;">
                            <label for="haa_signature" class="haa-drs-label">Electronic Signature <abbr title="required" class="haa-drs-req">*</abbr></label>
                            <p class="haa-drs-hint">Type your full legal name as your signature.</p>
                            <input type="text" id="haa_signature" name="signature_name" required class="haa-drs-input haa-drs-signature-input" placeholder="Your full legal name" autocomplete="off">
                            <span class="haa-drs-error" role="alert"></span>
                        </div>
                    </div>

                    <div class="haa-drs-actions">
                        <button type="button" class="haa-drs-btn haa-drs-btn--secondary" data-action="prev">Back</button>
                        <button type="submit" class="haa-drs-btn haa-drs-btn--submit" id="haa-drs-submit-btn" disabled>
                            <span class="haa-drs-btn-text">Submit Application</span>
                            <span class="haa-drs-btn-loading" hidden>
                                <svg class="haa-drs-spinner" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="40 20"/></svg>
                                Submitting&hellip;
                            </span>
                        </button>
                    </div>
                </fieldset>

                <div class="haa-drs-step haa-drs-success" id="haa-drs-success" hidden>
                    <div class="haa-drs-success-inner">
                        <svg class="haa-drs-success-icon" width="64" height="64" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                            <circle cx="32" cy="32" r="30" stroke="#1B7A4A" stroke-width="3" fill="#E8F5EE"/>
                            <path d="M20 33l8 8 16-16" stroke="#1B7A4A" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h2 class="haa-drs-success-title">Application Submitted</h2>
                        <p class="haa-drs-success-id">Your application ID: <strong id="haa-drs-app-id"></strong></p>
                        <p>Thank you for completing the Houston Arts Alliance disaster relief application. We will review your submission and contact you to discuss next steps and available assistance.</p>
                        <p>Please save your application ID for your records. You will also receive a confirmation email shortly.</p>
                        <div class="haa-drs-callout">
                            <strong>If you are in immediate danger, contact 911.</strong>
                        </div>
                    </div>
                </div>

            </form>

            <div class="haa-drs-modal" id="haa-drs-resources-modal" hidden role="dialog" aria-modal="true" aria-labelledby="haa-drs-resources-modal-title">
                <div class="haa-drs-modal-backdrop" data-modal-close></div>
                <div class="haa-drs-modal-dialog" role="document">
                    <button type="button" class="haa-drs-modal-close" data-modal-close aria-label="Close">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                    <h2 class="haa-drs-modal-title" id="haa-drs-resources-modal-title">Disaster Resources</h2>
                    <div class="haa-drs-modal-body">
                        <div class="haa-drs-resource-group">
                            <h3 class="haa-drs-resource-subheading">Apply for Assistance</h3>
                            <ul class="haa-drs-resource-list">
                                <li><a href="https://www.disasterassistance.gov/" target="_blank" rel="noopener" class="haa-drs-ext-link">DisasterAssistance.gov<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Federal disaster assistance and FEMA applications</span></li>
                            </ul>
                        </div>

                        <div class="haa-drs-resource-group">
                            <h3 class="haa-drs-resource-subheading">Report Damage</h3>
                            <ul class="haa-drs-resource-list">
                                <li><a href="https://damage.tdem.texas.gov/" target="_blank" rel="noopener" class="haa-drs-ext-link">Texas TDEM Damage Survey<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Self-report property damage to state officials</span></li>
                                <li><a href="https://houstontx.gov/311/" target="_blank" rel="noopener" class="haa-drs-ext-link">Houston 311<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Call 311 to report storm debris, street flooding, or drainage issues in the City of Houston</span></li>
                            </ul>
                        </div>

                        <div class="haa-drs-resource-group">
                            <h3 class="haa-drs-resource-subheading">Additional Resources</h3>
                            <ul class="haa-drs-resource-list">
                                <li><a href="https://stear.texas.gov/" target="_blank" rel="noopener" class="haa-drs-ext-link">State of Texas Emergency Assistance Registry (STEAR)<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">For people with disabilities or medical needs</span></li>
                                <li><a href="https://tracker.centerpointenergy.com/map/texas" target="_blank" rel="noopener" class="haa-drs-ext-link">CenterPoint Energy Outage Tracker<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Check power outages across the region</span></li>
                                <li><a href="https://www.ready.gov/" target="_blank" rel="noopener" class="haa-drs-ext-link">FEMA Ready.gov<svg class="haa-drs-ext-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h7v7M10 14L21 3M19 14v6a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a><span class="haa-drs-resource-sep" aria-hidden="true"></span><span class="haa-drs-resource-desc">Resources for emergencies and disasters</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_submit() {
        if ( ! check_ajax_referer( 'haa_drs_submit', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed. Please refresh the page and try again.' ], 403 );
        }

        if ( ! empty( $_POST['haa_drs_website_url'] ) ) {
            // Silently succeed to not tip off bots
            wp_send_json_success( [ 'application_id' => 'HAA-0000-00000' ] );
        }

        $ip = self::get_client_ip();
        if ( HAA_DRS_Database::is_rate_limited( $ip ) ) {
            wp_send_json_error( [
                'message' => 'You have already submitted an application recently. Please wait before submitting again.',
            ], 429 );
        }

        // Sanitize all inputs
        $data = self::sanitize_submission( $_POST );

        // Server-side validation
        $errors = self::validate_submission( $data );
        if ( ! empty( $errors ) ) {
            wp_send_json_error( [ 'message' => 'Please correct the errors below.', 'errors' => $errors ], 422 );
        }

        $data['ip_address']     = $ip;
        $data['signature_date'] = current_time( 'mysql', true );
        $data['admin_notes']    = '';

        foreach ( [ 'race_ethnicity', 'vulnerability_factors', 'assistance_types', 'external_support', 'current_needs' ] as $field ) {
            $data[ $field ] = wp_json_encode( $data[ $field ] ?? [] );
        }

        $result = HAA_DRS_Database::insert( $data );
        if ( ! $result ) {
            wp_send_json_error( [ 'message' => 'Something went wrong saving your application. Please try again.' ], 500 );
        }

        HAA_DRS_Database::set_rate_limit( $ip );

        // send them emails
        self::send_applicant_email( $result );
        self::send_staff_notification( $result );

        wp_send_json_success( [
            'application_id' => $result['application_id'],
            'message'        => 'Application submitted successfully.',
        ] );
    }

    private static function sanitize_submission( $post ) {
        $text_fields = [
            'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'zip',
            'phone', 'preferred_language', 'emergency_contact_name', 'emergency_contact_phone',
            'household_size', 'age_18_plus', 'county',
            'is_artist', 'artistic_discipline', 'race_ethnicity_other',
            'received_assistance',
            'assistance_other', 'fema_applied', 'sba_applied',
            'external_support_other', 'current_needs_other', 'signature_name',
            'vulnerability_factors_other',
        ];

        $data = [];
        foreach ( $text_fields as $field ) {
            $data[ $field ] = sanitize_text_field( $post[ $field ] ?? '' );
        }

        // If preferred language is "Other", use the typed value
        $lang_other = sanitize_text_field( $post['preferred_language_other'] ?? '' );
        if ( $data['preferred_language'] === 'Other' && ! empty( $lang_other ) ) {
            $data['preferred_language'] = $lang_other;
        }

        $data['full_name'] = trim( $data['first_name'] . ' ' . $data['last_name'] );

        $data['email']     = sanitize_email( $post['email'] ?? '' );
        $data['website']   = esc_url_raw( $post['website'] ?? '' );
        $data['social_media'] = esc_url_raw( $post['social_media'] ?? '' );
        $data['cv_link']   = esc_url_raw( $post['cv_link'] ?? '' );
        $data['dob']       = sanitize_text_field( $post['dob'] ?? '' );

        $data['svi_score']      = floatval( $post['svi_score'] ?? 0 );
        $data['housing_status'] = max( 0, min( 4, intval( $post['housing_status'] ?? 0 ) ) );
        $data['need_level']     = intval( $post['need_level'] ?? 0 );
        $data['belongings_loss'] = max( 0, min( 4, intval( $post['belongings_loss'] ?? 0 ) ) );
        $data['adults_count']   = intval( $post['adults_count'] ?? 0 );
        $data['seniors_count']  = intval( $post['seniors_count'] ?? 0 );
        $data['children_count'] = intval( $post['children_count'] ?? 0 );

        $data['consent_agreed'] = ! empty( $post['consent_agreed'] ) ? 1 : 0;

        $income_allowed = [
            'Less than $25,000', '$25,000 to $49,999', '$50,000 to $74,999',
            '$75,000 to $99,999', '$100,000 to $149,999', '$150,000 to $199,999',
            '$200,000 or more', 'Prefer not to say',
        ];
        $income_raw = sanitize_text_field( $post['household_income'] ?? '' );
        $data['household_income'] = in_array( $income_raw, $income_allowed, true ) ? $income_raw : '';

        $data['impact_statement'] = sanitize_textarea_field( $post['impact_statement'] ?? '' );

        $array_fields = [ 'race_ethnicity', 'vulnerability_factors', 'assistance_types', 'external_support', 'current_needs' ];
        foreach ( $array_fields as $field ) {
            $raw = $post[ $field ] ?? [];
            $data[ $field ] = is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : [];
        }

        $data['disaster_event'] = get_option( 'haa_drs_current_event', '' );

        return $data;
    }

    private static function validate_submission( $data ) {
        $errors = [];

        if ( empty( $data['first_name'] ) ) $errors['first_name'] = 'First name is required.';
        if ( empty( $data['last_name'] ) )  $errors['last_name']  = 'Last name is required.';
        if ( empty( $data['address_1'] ) )  $errors['address_1']  = 'Address is required.';
        if ( empty( $data['city'] ) )       $errors['city']       = 'City is required.';
        if ( empty( $data['zip'] ) || ! preg_match( '/^\d{5}$/', $data['zip'] ) ) {
            $errors['zip'] = 'A valid 5-digit ZIP code is required.';
        }
        if ( empty( $data['phone'] ) )      $errors['phone']      = 'Phone number is required.';
        if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
            $errors['email'] = 'A valid email address is required.';
        }
        if ( empty( $data['dob'] ) )        $errors['dob']        = 'Date of birth is required.';

        // Online presence: at least one
        if ( empty( $data['website'] ) && empty( $data['social_media'] ) && empty( $data['cv_link'] ) ) {
            $errors['website'] = 'Please provide at least one link to your website, social media, or CV.';
        }

        if ( empty( $data['preferred_language'] ) ) $errors['preferred_language'] = 'Preferred language is required.';

        if ( empty( $data['race_ethnicity'] ) || ( is_array( $data['race_ethnicity'] ) && count( $data['race_ethnicity'] ) === 0 ) ) {
            $errors['race_ethnicity'] = 'Race and ethnicity is required.';
        }
        $describe_triggers = [ 'American Indian or Alaska Native', 'A race or ethnicity not listed here' ];
        if ( is_array( $data['race_ethnicity'] ) && ! empty( array_intersect( $describe_triggers, $data['race_ethnicity'] ) ) ) {
            if ( empty( $data['race_ethnicity_other'] ) ) {
                $errors['race_ethnicity_other'] = 'Please describe your race or ethnicity.';
            }
        }

        if ( empty( $data['household_size'] ) ) $errors['household_size'] = 'Household size is required.';
        if ( empty( $data['household_income'] ) ) $errors['household_income'] = 'Household income is required.';
        if ( empty( $data['age_18_plus'] ) )    $errors['age_18_plus']    = 'Please confirm your age.';
        if ( empty( $data['county'] ) )         $errors['county']         = 'County is required.';
        if ( empty( $data['is_artist'] ) )      $errors['is_artist']      = 'Please indicate if you are an artist.';

        if ( $data['svi_score'] < 0 || $data['svi_score'] > 1 ) {
            $errors['svi_score'] = 'SVI score must be between 0.00 and 1.00.';
        }

        if ( empty( $data['received_assistance'] ) ) {
            $errors['received_assistance'] = 'Please indicate whether you have received assistance.';
        }
        if ( empty( $data['fema_applied'] ) ) {
            $errors['fema_applied'] = 'Please indicate whether you applied for FEMA assistance.';
        }
        if ( empty( $data['sba_applied'] ) ) {
            $errors['sba_applied'] = 'Please indicate whether you applied for SBA assistance.';
        }
        if ( empty( $data['current_needs'] ) || ( is_array( $data['current_needs'] ) && count( $data['current_needs'] ) === 0 ) ) {
            $errors['current_needs'] = 'Please select at least one current need.';
        }
        if ( is_array( $data['current_needs'] ) && in_array( 'Other', $data['current_needs'], true ) ) {
            if ( empty( $data['current_needs_other'] ) ) {
                $errors['current_needs_other'] = 'Please describe what you need.';
            }
        }
        if ( is_array( $data['vulnerability_factors'] ) && in_array( 'Other vulnerability not listed', $data['vulnerability_factors'], true ) ) {
            if ( empty( $data['vulnerability_factors_other'] ) ) {
                $errors['vulnerability_factors_other'] = 'Please specify your other vulnerability.';
            }
        }
        if ( empty( $data['impact_statement'] ) ) {
            $errors['impact_statement'] = 'Please provide an impact statement.';
        }

        if ( ! $data['consent_agreed'] )         $errors['consent_agreed']  = 'You must agree to the consent statement.';
        if ( empty( $data['signature_name'] ) )  $errors['signature_name']  = 'Your signature is required.';

        return $errors;
    }

    private static function send_applicant_email( $data ) {
        $to      = $data['email'];
        $subject = 'HAA SOAR Application Received: ' . $data['application_id'];

        $body  = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">';
        $body .= '<div style="background:#04163F;color:#f0c148;padding:24px;text-align:center;border-radius:8px 8px 0 0;">';
        $body .= '<h1 style="margin:0;font-size:20px;font-weight:400;">Houston Arts Alliance</h1>';
        $body .= '<p style="margin:4px 0 0;font-size:14px;opacity:0.9;">SOAR Relief Fund</p></div>';
        $body .= '<div style="padding:24px;background:#ffffff;border:1px solid #e2e4e8;border-top:none;border-radius:0 0 8px 8px;">';
        $body .= '<p>Dear ' . esc_html( trim( $data['first_name'] . ' ' . $data['last_name'] ) ) . ',</p>';
        $body .= '<p>We have received your SOAR relief application. Your application ID is:</p>';
        $body .= '<p style="font-size:24px;font-weight:600;color:#04163F;text-align:center;padding:16px;background:#f7f8fa;border-radius:6px;">' . esc_html( $data['application_id'] ) . '</p>';
        $body .= '<p>Please save this ID for your records. Our team will review your application and contact you to discuss next steps and available assistance.</p>';
        $body .= '<p style="color:#666;font-size:13px;margin-top:24px;padding-top:16px;border-top:1px solid #e2e4e8;">If you are in immediate danger, contact 911. For questions about your application, contact us at the email or phone number listed on our website.</p>';
        $body .= '</div></div>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Houston Arts Alliance <' . get_option( 'admin_email' ) . '>',
        ];

        wp_mail( $to, $subject, $body, $headers );
    }

    private static function send_staff_notification( $data ) {
        $to = get_option( 'haa_drs_notify_email', get_option( 'admin_email' ) );
        if ( empty( $to ) ) return;

        $subject = '[DRS] New Application: ' . $data['application_id'] . ' - ' . trim( $data['first_name'] . ' ' . $data['last_name'] );

        $admin_url = admin_url( 'admin.php?page=haa-drs&action=view&id=' . $data['id'] );

        $body  = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Arial,sans-serif;max-width:600px;padding:20px;">';
        $body .= '<h2 style="color:#04163F;">New DRS Application</h2>';
        $body .= '<table style="width:100%;border-collapse:collapse;">';
        $body .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;">Application ID</td><td style="padding:8px;border-bottom:1px solid #eee;">' . esc_html( $data['application_id'] ) . '</td></tr>';
        $body .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;">Name</td><td style="padding:8px;border-bottom:1px solid #eee;">' . esc_html( trim( $data['first_name'] . ' ' . $data['last_name'] ) ) . '</td></tr>';
        $body .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;">Email</td><td style="padding:8px;border-bottom:1px solid #eee;">' . esc_html( $data['email'] ) . '</td></tr>';
        $body .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;">Priority</td><td style="padding:8px;border-bottom:1px solid #eee;">' . esc_html( $data['priority_tier'] ) . ' (Score: ' . intval( $data['priority_score'] ) . ')</td></tr>';
        $body .= '</table>';
        $body .= '<p style="margin-top:16px;"><a href="' . esc_url( $admin_url ) . '" style="display:inline-block;padding:10px 20px;background:#04163F;color:#f0c148;text-decoration:none;border-radius:4px;">View in Dashboard</a></p>';
        $body .= '</div>';

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        wp_mail( $to, $subject, $body, $headers );
    }

    public static function handle_svi_lookup() {
        check_ajax_referer( 'haa_drs_submit', 'nonce' );

        $zip = sanitize_text_field( $_POST['zip'] ?? '' );
        if ( ! preg_match( '/^\d{5}$/', $zip ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a valid 5-digit ZIP code.' ] );
        }

        $hud_token = get_option( 'haa_drs_hud_api_token', '' );
        if ( empty( $hud_token ) ) {
            wp_send_json_error( [ 'message' => 'SVI lookup is not configured. Please contact the site administrator.' ] );
        }

        $hud_url  = 'https://www.huduser.gov/hudapi/public/usps?type=1&query=' . $zip;
        $hud_resp = wp_remote_get( $hud_url, [
            'headers' => [ 'Authorization' => 'Bearer ' . $hud_token ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $hud_resp ) ) {
            wp_send_json_error( [ 'message' => 'Unable to look up ZIP code. Please try again.' ] );
        }

        $hud_code = (int) wp_remote_retrieve_response_code( $hud_resp );
        if ( 200 !== $hud_code ) {
            error_log( sprintf(
                '[HAA DRS] HUD USPS lookup failed (ZIP %s): HTTP %d; %s',
                $zip, $hud_code, substr( (string) wp_remote_retrieve_body( $hud_resp ), 0, 500 )
            ) );
            if ( 401 === $hud_code || 403 === $hud_code ) {
                wp_send_json_error( [ 'message' => 'The ZIP lookup service is temporarily unavailable due to a configuration issue. Please contact the site administrator.' ] );
            }
            wp_send_json_error( [ 'message' => 'The ZIP lookup service is busy right now. Please wait a moment and try again.' ] );
        }

        $hud_body = json_decode( wp_remote_retrieve_body( $hud_resp ), true );
        $tracts   = $hud_body['data']['results'] ?? [];

        if ( empty( $tracts ) ) {
            wp_send_json_error( [ 'message' => 'No census data found for ZIP code ' . $zip . '. Please verify and try again.' ] );
        }

        $fips_list   = [];
        $res_ratios  = [];
        foreach ( $tracts as $t ) {
            $fips = sanitize_text_field( $t['geoid'] ?? '' );
            $ratio = floatval( $t['res_ratio'] ?? 0 );
            if ( $fips && $ratio > 0 ) {
                $fips_list[]          = $fips;
                $res_ratios[ $fips ]  = $ratio;
            }
        }

        if ( empty( $fips_list ) ) {
            wp_send_json_error( [ 'message' => 'No residential census tracts found for this ZIP code.' ] );
        }

        $fips_where = implode( "','", array_map( 'esc_attr', $fips_list ) );
        $cdc_url    = 'https://services3.arcgis.com/ZvidGQkLaDJxRSJ2/arcgis/rest/services/'
                    . 'CDC_ATSDR_Social_Vulnerability_Index_2022_USA/FeatureServer/2/query?'
                    . 'where=' . rawurlencode( "FIPS IN ('" . $fips_where . "')" )
                    . '&outFields=' . rawurlencode( 'FIPS,RPL_THEMES,RPL_THEME1,RPL_THEME2,RPL_THEME3,RPL_THEME4,E_TOTPOP,LOCATION' )
                    . '&returnGeometry=false&f=json';

        $cdc_resp = wp_remote_get( $cdc_url, [ 'timeout' => 15 ] );

        if ( is_wp_error( $cdc_resp ) ) {
            wp_send_json_error( [ 'message' => 'Unable to retrieve SVI data. Please try again.' ] );
        }

        $cdc_code = (int) wp_remote_retrieve_response_code( $cdc_resp );
        if ( 200 !== $cdc_code ) {
            error_log( sprintf(
                '[HAA DRS] CDC ArcGIS SVI query failed (ZIP %s): HTTP %d; %s',
                $zip, $cdc_code, substr( (string) wp_remote_retrieve_body( $cdc_resp ), 0, 500 )
            ) );
            wp_send_json_error( [ 'message' => 'The SVI service is busy right now. Please wait a moment and try again.' ] );
        }

        $cdc_body   = json_decode( wp_remote_retrieve_body( $cdc_resp ), true );
        $features   = $cdc_body['features'] ?? [];

        if ( empty( $features ) ) {
            wp_send_json_error( [ 'message' => 'No SVI data available for the census tracts in this ZIP code.' ] );
        }

        $weighted_sum   = 0;
        $weight_total   = 0;
        $theme_sums     = [ 0, 0, 0, 0 ];

        foreach ( $features as $f ) {
            $attr  = $f['attributes'] ?? [];
            $fips  = (string) ( $attr['FIPS'] ?? '' );
            $rpl   = floatval( $attr['RPL_THEMES'] ?? -1 );

            // Skip tracts with -999 (suppressed data)
            if ( $rpl < 0 ) continue;

            $w = $res_ratios[ $fips ] ?? 0;
            if ( $w <= 0 ) continue;

            $weighted_sum += $rpl * $w;
            $weight_total += $w;

            for ( $i = 1; $i <= 4; $i++ ) {
                $tv = floatval( $attr[ 'RPL_THEME' . $i ] ?? -1 );
                if ( $tv >= 0 ) {
                    $theme_sums[ $i - 1 ] += $tv * $w;
                }
            }
        }

        if ( $weight_total <= 0 ) {
            wp_send_json_error( [ 'message' => 'SVI data for this area is suppressed or unavailable.' ] );
        }

        $overall = round( $weighted_sum / $weight_total, 4 );
        $themes  = [];
        $theme_labels = [
            'Socioeconomic Status',
            'Household Characteristics',
            'Racial and Ethnic Minority Status',
            'Housing Type and Transportation',
        ];
        for ( $i = 0; $i < 4; $i++ ) {
            $themes[] = [
                'label' => $theme_labels[ $i ],
                'score' => round( $theme_sums[ $i ] / $weight_total, 4 ),
            ];
        }

        if ( $overall >= 0.8 )      $level = 'high';
        elseif ( $overall >= 0.6 )  $level = 'moderate-to-high';
        elseif ( $overall >= 0.4 )  $level = 'moderate';
        elseif ( $overall >= 0.2 )  $level = 'low-to-moderate';
        else                        $level = 'low';

        wp_send_json_success( [
            'zip'        => $zip,
            'overall'    => $overall,
            'level'      => $level,
            'themes'     => $themes,
            'tract_count' => count( $features ),
        ] );
    }

    private static function get_client_ip() {
        $keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
                return trim( $ip[0] );
            }
        }
        return '0.0.0.0';
    }
}
