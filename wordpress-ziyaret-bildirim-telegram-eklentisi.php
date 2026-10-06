<?php
/**
 * Plugin Name: WordPress Ziyaret Bildirim Telegram Eklentisi
 * Description: Site ziyaretlerini yapılandırılmış yönetici Telegram sohbetlerine bildirir.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: wordpress-ziyaret-bildirim-telegram-eklentisi
 *
 * @package WordPressZiyaretBildirimTelegramEklentisi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WZBTE_Telegram_Visit_Notifications {
	const BOT_TOKEN_META = '_wzbte_telegram_bot_token';
	const CHAT_ID_META   = '_wzbte_telegram_chat_id';

	/**
	 * Register profile settings and front-end visit notifications.
	 */
	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'render_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );
		add_action( 'template_redirect', array( $this, 'notify_administrators' ) );
	}

	/**
	 * Render accessible Telegram settings on an administrator's profile.
	 *
	 * @param WP_User $user User whose profile is being edited.
	 */
	public function render_profile_fields( $user ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user->ID ) || ! user_can( $user, 'manage_options' ) ) {
			return;
		}

		$chat_id = get_user_meta( $user->ID, self::CHAT_ID_META, true );
		?>
		<tr>
			<th scope="row">
				<label for="wzbte-telegram-bot-token"><?php esc_html_e( 'Telegram bot token', 'wordpress-ziyaret-bildirim-telegram-eklentisi' ); ?></label>
			</th>
			<td>
				<input type="password" name="wzbte_telegram_bot_token" id="wzbte-telegram-bot-token" class="regular-text" value="" autocomplete="new-password" aria-describedby="wzbte-telegram-bot-token-description" />
				<p class="description" id="wzbte-telegram-bot-token-description"><?php esc_html_e( 'Tokeni değiştirmek için yenisini girin. Boş bırakırsanız kayıtlı token korunur.', 'wordpress-ziyaret-bildirim-telegram-eklentisi' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wzbte-telegram-chat-id"><?php esc_html_e( 'Telegram chat ID', 'wordpress-ziyaret-bildirim-telegram-eklentisi' ); ?></label>
			</th>
			<td>
				<input type="text" name="wzbte_telegram_chat_id" id="wzbte-telegram-chat-id" class="regular-text" value="<?php echo esc_attr( $chat_id ); ?>" autocomplete="off" aria-describedby="wzbte-telegram-chat-id-description" />
				<p class="description" id="wzbte-telegram-chat-id-description"><?php esc_html_e( 'Ziyaret bildirimlerinin gönderileceği Telegram sohbetinin ID değeri.', 'wordpress-ziyaret-bildirim-telegram-eklentisi' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Telegram bağlantısı', 'wordpress-ziyaret-bildirim-telegram-eklentisi' ); ?></th>
			<td>
				<label for="wzbte-telegram-remove">
					<input type="checkbox" name="wzbte_telegram_remove" id="wzbte-telegram-remove" value="1" />
					<?php esc_html_e( 'Kayıtlı bot tokenini ve chat ID değerini sil', 'wordpress-ziyaret-bildirim-telegram-eklentisi' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<?php wp_nonce_field( 'wzbte_save_telegram_settings_' . $user->ID, 'wzbte_telegram_settings_nonce' ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save Telegram credentials for the administrator whose profile was edited.
	 *
	 * @param int $user_id User whose profile was saved.
	 */
	public function save_profile_fields( $user_id ) {
		$user_id = absint( $user_id );

		if (
			! $user_id
			|| ! current_user_can( 'manage_options' )
			|| ! current_user_can( 'edit_user', $user_id )
			|| ! user_can( $user_id, 'manage_options' )
			|| ! isset( $_POST['wzbte_telegram_settings_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['wzbte_telegram_settings_nonce'] ) ),
				'wzbte_save_telegram_settings_' . $user_id
			)
		) {
			return;
		}

		if ( isset( $_POST['wzbte_telegram_remove'] ) && '1' === $_POST['wzbte_telegram_remove'] ) {
			delete_user_meta( $user_id, self::BOT_TOKEN_META );
			delete_user_meta( $user_id, self::CHAT_ID_META );
			return;
		}

		if ( isset( $_POST['wzbte_telegram_bot_token'] ) ) {
			$bot_token = sanitize_text_field( wp_unslash( $_POST['wzbte_telegram_bot_token'] ) );
			if ( '' !== $bot_token ) {
				update_user_meta( $user_id, self::BOT_TOKEN_META, $bot_token );
			}
		}

		if ( isset( $_POST['wzbte_telegram_chat_id'] ) ) {
			$chat_id = sanitize_text_field( wp_unslash( $_POST['wzbte_telegram_chat_id'] ) );
			if ( '' !== $chat_id ) {
				update_user_meta( $user_id, self::CHAT_ID_META, $chat_id );
			} else {
				delete_user_meta( $user_id, self::CHAT_ID_META );
			}
		}
	}

	/**
	 * Send a visit notification to each administrator with valid credentials.
	 */
	public function notify_administrators() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path = is_string( $request_path ) ? sanitize_text_field( $request_path ) : '/';
		$page_url     = esc_url_raw( home_url( '/' . ltrim( $request_path, '/' ) ) );

		$visitor_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! filter_var( $visitor_ip, FILTER_VALIDATE_IP ) ) {
			$visitor_ip = __( 'Bilinmiyor', 'wordpress-ziyaret-bildirim-telegram-eklentisi' );
		}

		$message = sprintf(
			/* translators: 1: site name, 2: visited page URL, 3: visitor IP address, 4: visit date and time. */
			__( "Yeni site ziyareti\nSite: %1\$s\nSayfa: %2\$s\nIP: %3\$s\nZaman: %4\$s", 'wordpress-ziyaret-bildirim-telegram-eklentisi' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$page_url,
			$visitor_ip,
			wp_date( 'Y-m-d H:i:s T' )
		);

		$administrators = get_users(
			array(
				'role'   => 'administrator',
				'fields' => array( 'ID' ),
				'number' => -1,
			)
		);

		foreach ( $administrators as $administrator ) {
			$bot_token = get_user_meta( $administrator->ID, self::BOT_TOKEN_META, true );
			$chat_id   = get_user_meta( $administrator->ID, self::CHAT_ID_META, true );

			if ( ! is_string( $bot_token ) || ! preg_match( '/\A[0-9]+:[A-Za-z0-9_-]+\z/', $bot_token ) || ! is_string( $chat_id ) || '' === $chat_id ) {
				continue;
			}

			wp_remote_post(
				'https://api.telegram.org/bot' . $bot_token . '/sendMessage',
				array(
					'body'        => array(
						'chat_id' => $chat_id,
						'text'    => $message,
					),
					'timeout'     => 1,
					'blocking'    => false,
					'redirection' => 0,
				)
			);
		}
	}
}

new WZBTE_Telegram_Visit_Notifications();
