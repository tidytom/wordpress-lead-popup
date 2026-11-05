<?php
/**
 * Plugin Name:       Lead Capture Popup
 * Description:       Web3Forms-powered popup with delayed display, frequency cooldown, accessibility, and an optional PDF reveal after submit.
 * Version:           1.0.0
 * Requires at least: 4.7
 * Requires PHP:      7.0
 * Author:            tidytom
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       lead-popup
 *
 * @package LeadPopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEAD_POPUP_VERSION' ) ) {
	define( 'LEAD_POPUP_VERSION', '1.0.0' );
}
if ( ! defined( 'LEAD_POPUP_FILE' ) ) {
	define( 'LEAD_POPUP_FILE', __FILE__ );
}
if ( ! defined( 'LEAD_POPUP_DIR' ) ) {
	define( 'LEAD_POPUP_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'LEAD_POPUP_URL' ) ) {
	define( 'LEAD_POPUP_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Main plugin class.
 */
class Lead_Popup {

	const NONCE_ACTION = 'lead_popup_pdf';
	const AJAX_ACTION  = 'lead_popup_get_pdf';
	const GROUP        = 'lead_popup_group';

	/* =========================================================================
	 * Defaults. The settings page can override any of them.
	 * The Web3Forms key and PDF URL are empty on purpose: every site owner
	 * must enter their own.
	 * ========================================================================= */

	const DEFAULT_WEB3_KEY       = '';
	const DEFAULT_PDF_URL        = '';
	const DEFAULT_DELAY          = 5;
	const DEFAULT_FREQUENCY      = 30;
	const DEFAULT_BG_COLOR       = '#ffffff';
	const DEFAULT_PRIVACY_TEXT   = 'We respect your privacy. Your information is not shared with others.';
	const DEFAULT_HEADING        = 'Get our free guide';
	const DEFAULT_CHECKBOX_LABEL = 'Yes, send me the free guide (PDF).';
	const DEFAULT_TRIGGER_TEXT   = 'Get our free guide';

	public function __construct() {
		// Admin
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'output_modal_html' ) );

		// Shortcode
		add_shortcode( 'lead_popup_trigger', array( $this, 'render_shortcode' ) );

		// AJAX (logged-in + anonymous)
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_get_pdf_url' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_get_pdf_url' ) );
	}

	/* ---------------- Option helpers ---------------- */

	/**
	 * Read a text option, falling back to the default when it was never saved.
	 * (register_setting() defaults only apply in the admin, so the front end
	 * always passes the default explicitly.)
	 */
	private function opt( $name, $default ) {
		$value = get_option( 'lead_popup_' . $name, $default );
		return ( false === $value || null === $value ) ? $default : $value;
	}

	/* =========================================================================
	 * Admin
	 * ========================================================================= */

	public function add_admin_menu() {
		add_options_page(
			__( 'Lead Capture Popup Settings', 'lead-popup' ),
			__( 'Lead Popup', 'lead-popup' ),
			'manage_options',
			'lead-popup',
			array( $this, 'options_page' )
		);
	}

	public function register_settings() {
		$settings = array(
			'lead_popup_web3_key'       => array( 'string', array( $this, 'sanitize_web3_key' ), self::DEFAULT_WEB3_KEY ),
			'lead_popup_pdf_url'        => array( 'string', array( $this, 'sanitize_pdf_url' ), self::DEFAULT_PDF_URL ),
			'lead_popup_frequency'      => array( 'integer', array( $this, 'sanitize_frequency' ), self::DEFAULT_FREQUENCY ),
			'lead_popup_bg_color'       => array( 'string', array( $this, 'sanitize_hex_color' ), self::DEFAULT_BG_COLOR ),
			'lead_popup_delay_seconds'  => array( 'integer', array( $this, 'sanitize_delay' ), self::DEFAULT_DELAY ),
			'lead_popup_privacy_text'   => array( 'string', 'sanitize_text_field', self::DEFAULT_PRIVACY_TEXT ),
			'lead_popup_heading'        => array( 'string', 'sanitize_text_field', self::DEFAULT_HEADING ),
			'lead_popup_checkbox_label' => array( 'string', 'sanitize_text_field', self::DEFAULT_CHECKBOX_LABEL ),
			'lead_popup_trigger_text'   => array( 'string', 'sanitize_text_field', self::DEFAULT_TRIGGER_TEXT ),
		);

		foreach ( $settings as $name => $s ) {
			register_setting(
				self::GROUP,
				$name,
				array(
					'type'              => $s[0],
					'sanitize_callback' => $s[1],
					'default'           => $s[2],
				)
			);
		}
	}

	/* ---------------- Sanitizers ---------------- */

	public function sanitize_web3_key( $value ) {
		// Web3Forms keys are UUID-like.
		$value = trim( (string) $value );
		return preg_match( '/^[A-Za-z0-9\-]{16,64}$/', $value ) ? $value : '';
	}

	public function sanitize_pdf_url( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		// Allow site-relative paths starting with /
		if ( '/' === $value[0] ) {
			return esc_url_raw( $value );
		}
		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	public function sanitize_frequency( $value ) {
		$value = absint( $value );
		return max( 0, min( 365, $value ) );
	}

	public function sanitize_delay( $value ) {
		$value = absint( $value );
		return max( 0, min( 120, $value ) );
	}

	public function sanitize_hex_color( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value ) ) {
			return $value;
		}
		return self::DEFAULT_BG_COLOR;
	}

	/* ---------------- Options page ---------------- */

	/**
	 * Print one text input row on the settings page.
	 */
	private function text_row( $name, $label, $default, $description = '', $placeholder = '' ) {
		$id = 'lead_popup_' . $name;
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $this->opt( $name, $default ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	public function options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'lead-popup' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Lead Capture Popup Settings', 'lead-popup' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="lead_popup_web3_key"><?php esc_html_e( 'Web3Forms Access Key', 'lead-popup' ); ?></label>
						</th>
						<td>
							<input type="text" id="lead_popup_web3_key" name="lead_popup_web3_key" value="<?php echo esc_attr( $this->opt( 'web3_key', self::DEFAULT_WEB3_KEY ) ); ?>" class="regular-text" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Get a free key at web3forms.com. Required. This key is visible in your page source; Web3Forms keys are public by design.', 'lead-popup' ); ?></p>
						</td>
					</tr>
					<?php
					$this->text_row(
						'pdf_url',
						__( 'PDF URL (revealed after submit)', 'lead-popup' ),
						self::DEFAULT_PDF_URL,
						__( 'Site-relative ( /wp-content/... ) or full https:// URL. Only sent to the browser after the form is submitted. Leave empty to hide the PDF checkbox.', 'lead-popup' ),
						'/wp-content/uploads/your-guide.pdf'
					);
					?>
					<tr>
						<th scope="row">
							<label for="lead_popup_delay_seconds"><?php esc_html_e( 'Auto-open delay (seconds)', 'lead-popup' ); ?></label>
						</th>
						<td>
							<input type="number" id="lead_popup_delay_seconds" name="lead_popup_delay_seconds" min="0" max="120" value="<?php echo esc_attr( (int) $this->opt( 'delay_seconds', self::DEFAULT_DELAY ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Set to 0 to disable auto-open (manual triggers only).', 'lead-popup' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lead_popup_frequency"><?php esc_html_e( 'Cooldown (days)', 'lead-popup' ); ?></label>
						</th>
						<td>
							<input type="number" id="lead_popup_frequency" name="lead_popup_frequency" min="0" max="365" value="<?php echo esc_attr( (int) $this->opt( 'frequency', self::DEFAULT_FREQUENCY ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Days until the popup will auto-open again for the same visitor, after they click the X button or submit the form. 0 = no cooldown.', 'lead-popup' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lead_popup_bg_color"><?php esc_html_e( 'Background Color', 'lead-popup' ); ?></label>
						</th>
						<td>
							<input type="color" id="lead_popup_bg_color" name="lead_popup_bg_color" value="<?php echo esc_attr( $this->sanitize_hex_color( $this->opt( 'bg_color', self::DEFAULT_BG_COLOR ) ) ); ?>" />
						</td>
					</tr>
					<?php
					$this->text_row( 'heading', __( 'Popup heading', 'lead-popup' ), self::DEFAULT_HEADING );
					$this->text_row( 'checkbox_label', __( 'PDF checkbox label', 'lead-popup' ), self::DEFAULT_CHECKBOX_LABEL, __( 'Only shown when a PDF URL is set.', 'lead-popup' ) );
					$this->text_row( 'trigger_text', __( 'Shortcode link text', 'lead-popup' ), self::DEFAULT_TRIGGER_TEXT, __( 'The text of the link that the shortcode prints.', 'lead-popup' ) );
					$this->text_row( 'privacy_text', __( 'Privacy notice', 'lead-popup' ), self::DEFAULT_PRIVACY_TEXT, __( 'Shown beneath the form. Leave empty to hide.', 'lead-popup' ) );
					?>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />

			<div class="card" style="max-width:800px; padding:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'How to trigger the popup manually', 'lead-popup' ); ?></h2>
				<ol>
					<li><strong><?php esc_html_e( 'Shortcode:', 'lead-popup' ); ?></strong> <?php
						printf(
							/* translators: %s shortcode tag */
							esc_html__( 'Paste %s into any page, post, or widget. It outputs a link with the "Shortcode link text" above.', 'lead-popup' ),
							'<code>[lead_popup_trigger]</code>'
						);
					?></li>
					<li><strong><?php esc_html_e( 'Custom button:', 'lead-popup' ); ?></strong> <?php
						printf(
							/* translators: 1: link URL, 2: CSS class name */
							esc_html__( 'Set the button URL to %1$s and add the CSS class %2$s.', 'lead-popup' ),
							'<code>#</code>',
							'<code>lead-popup-manual-trigger</code>'
						);
					?></li>
				</ol>
			</div>
		</div>
		<?php
	}

	/* =========================================================================
	 * Frontend
	 * ========================================================================= */

	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		$css = LEAD_POPUP_DIR . 'popup.css';
		$js  = LEAD_POPUP_DIR . 'popup.js';

		wp_enqueue_style(
			'lead-popup-css',
			LEAD_POPUP_URL . 'popup.css',
			array(),
			file_exists( $css ) ? filemtime( $css ) : LEAD_POPUP_VERSION
		);

		wp_enqueue_script(
			'lead-popup-js',
			LEAD_POPUP_URL . 'popup.js',
			array(),
			file_exists( $js ) ? filemtime( $js ) : LEAD_POPUP_VERSION,
			true
		);

		wp_localize_script(
			'lead-popup-js',
			'leadPopupObj',
			array(
				// Public by design: Web3Forms expects the key in the browser.
				'web3Key'      => $this->opt( 'web3_key', self::DEFAULT_WEB3_KEY ),
				'cooldownDays' => (int) $this->opt( 'frequency', self::DEFAULT_FREQUENCY ),
				'delaySecs'    => (int) $this->opt( 'delay_seconds', self::DEFAULT_DELAY ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'ajaxAction'   => self::AJAX_ACTION,
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'i18n'         => array(
					'sending'       => __( 'Sending…', 'lead-popup' ),
					'success'       => __( 'Thank you! Your information has been received.', 'lead-popup' ),
					'success_pdf'   => __( 'Thank you! Your download will start shortly.', 'lead-popup' ),
					'pdf_error'     => __( 'Thank you! Your information has been received, but the download could not start.', 'lead-popup' ),
					'invalid_email' => __( 'Please enter a valid email address.', 'lead-popup' ),
					'system_error'  => __( 'The form is unavailable right now. Please try again later.', 'lead-popup' ),
					'network_error' => __( 'Network error. Please check your connection and try again.', 'lead-popup' ),
					'generic_error' => __( 'Something went wrong. Please try again.', 'lead-popup' ),
				),
			)
		);

		// Re-validate on output so only a hex color can reach the CSS.
		$bg = $this->sanitize_hex_color( $this->opt( 'bg_color', self::DEFAULT_BG_COLOR ) );
		wp_add_inline_style(
			'lead-popup-css',
			':root { --lead-popup-bg: ' . $bg . '; }'
		);
	}

	public function render_shortcode() {
		$text = $this->opt( 'trigger_text', self::DEFAULT_TRIGGER_TEXT );
		if ( '' === trim( (string) $text ) ) {
			$text = self::DEFAULT_TRIGGER_TEXT;
		}
		return '<a href="#" class="lead-popup-manual-trigger">' . esc_html( $text ) . '</a>';
	}

	public function output_modal_html() {
		if ( is_admin() ) {
			return;
		}
		$privacy  = (string) $this->opt( 'privacy_text', self::DEFAULT_PRIVACY_TEXT );
		$heading  = (string) $this->opt( 'heading', self::DEFAULT_HEADING );
		$checkbox = (string) $this->opt( 'checkbox_label', self::DEFAULT_CHECKBOX_LABEL );
		$has_pdf  = '' !== (string) $this->opt( 'pdf_url', self::DEFAULT_PDF_URL );
		if ( '' === trim( $heading ) ) {
			$heading = self::DEFAULT_HEADING;
		}
		?>
		<div
			id="lead-popup-overlay"
			class="lead-popup-hidden"
			role="dialog"
			aria-modal="true"
			aria-labelledby="lead-popup-title"
			aria-hidden="true"
		>
			<div id="lead-popup-content" tabindex="-1">
				<button type="button" id="lead-popup-close" aria-label="<?php esc_attr_e( 'Close', 'lead-popup' ); ?>">&times;</button>
				<h2 id="lead-popup-title"><?php echo esc_html( $heading ); ?></h2>
				<form id="lead-popup-form" novalidate>
					<input type="hidden" name="subject" value="<?php
						/* translators: %s site name */
						echo esc_attr( sprintf( __( 'New lead from %s', 'lead-popup' ), get_bloginfo( 'name' ) ) );
					?>" />
					<input type="hidden" name="from_name" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />

					<?php /* Honeypot - off-screen, screen-reader hidden */ ?>
					<div class="lead-popup-hp" aria-hidden="true">
						<label><?php esc_html_e( 'Leave this field empty', 'lead-popup' ); ?>
							<input type="text" name="botcheck" tabindex="-1" autocomplete="off" />
						</label>
					</div>

					<p>
						<label for="lead-popup-name"><?php esc_html_e( 'Name', 'lead-popup' ); ?></label>
						<input type="text" id="lead-popup-name" name="name" autocomplete="name" />
					</p>
					<p>
						<label for="lead-popup-email"><?php esc_html_e( 'Email Address', 'lead-popup' ); ?> <span class="lead-popup-required" aria-hidden="true">*</span></label>
						<input type="email" id="lead-popup-email" name="email" autocomplete="email" required aria-required="true" aria-describedby="lead-popup-message" />
					</p>
					<?php if ( $has_pdf && '' !== trim( $checkbox ) ) : ?>
						<p class="lead-popup-checkbox-row">
							<label>
								<input type="checkbox" id="lead-popup-pdf-check" name="wants_pdf" value="Yes" />
								<?php echo esc_html( $checkbox ); ?>
							</label>
						</p>
					<?php endif; ?>
					<button type="submit" id="lead-popup-submit-btn"><?php esc_html_e( 'Submit', 'lead-popup' ); ?></button>
					<p id="lead-popup-message" role="status" aria-live="polite"></p>

					<?php if ( '' !== $privacy ) : ?>
						<p class="lead-popup-privacy"><?php echo esc_html( $privacy ); ?></p>
					<?php endif; ?>
				</form>
			</div>
		</div>
		<?php
	}

	/* =========================================================================
	 * AJAX - return PDF URL after nonce check (light obscurity, not protection)
	 * ========================================================================= */

	public function ajax_get_pdf_url() {
		// Dies with HTTP 403 when the nonce is missing or invalid.
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$url = (string) $this->opt( 'pdf_url', self::DEFAULT_PDF_URL );
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'PDF not configured', 'lead-popup' ) ), 404 );
		}

		// Convert site-relative to absolute for cleaner client handling.
		if ( '/' === $url[0] ) {
			$url = home_url( $url );
		}

		wp_send_json_success( array( 'url' => esc_url_raw( $url, array( 'http', 'https' ) ) ) );
	}
}

new Lead_Popup();
