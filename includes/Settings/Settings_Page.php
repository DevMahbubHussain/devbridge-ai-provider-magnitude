<?php
/**
 * Settings page renderer.
 *
 * @package DevBridgeAiProviderForMagnitude
 */

declare( strict_types=1 );

namespace DevBridgeAiProviderForMagnitude\Settings;

use DevBridgeAiProviderForMagnitude\Connection\Connection_Status;
use DevBridgeAiProviderForMagnitude\Contracts\Connection_Inspector;
use DevBridgeAiProviderForMagnitude\Contracts\Host_Provider;
use DevBridgeAiProviderForMagnitude\Host\Host_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Settings > Magnitude screen.
 *
 * The screen shows the connection status, the server address form and the models
 * Magnitude is running. Every value is escaped where it is printed.
 *
 * @since 0.1.0
 */
final class Settings_Page {

	/**
	 * Settings page slug.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public const SLUG = 'devbridge-ai-provider-magnitude';

	/**
	 * Name of the admin-post action that refreshes the connection check.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public const CHECK_ACTION = 'devbridge_ai_provider_magnitude_check';

	/**
	 * Query argument added after the connection was checked.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public const CHECKED_ARG = 'devbridge-ai-provider-magnitude-checked';

	/**
	 * Source of the active server address.
	 *
	 * @since 0.1.0
	 *
	 * @var Host_Provider
	 */
	private $host_provider;

	/**
	 * Source of the connection status.
	 *
	 * @since 0.1.0
	 *
	 * @var Connection_Inspector
	 */
	private $inspector;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Host_Provider        $host_provider Source of the active server address.
	 * @param Connection_Inspector $inspector     Source of the connection status.
	 */
	public function __construct( Host_Provider $host_provider, Connection_Inspector $inspector ) {
		$this->host_provider = $host_provider;
		$this->inspector     = $inspector;
	}

	/**
	 * Gets the URL of the settings screen.
	 *
	 * @since 0.1.0
	 *
	 * @return string The screen URL.
	 */
	public static function get_url(): string {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}

	/**
	 * Renders the screen.
	 *
	 * @since 0.1.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = $this->inspector->inspect();
		?>
		<div class="wrap devbridge-ai-provider-magnitude-settings">
			<header class="devbridge-ai-provider-magnitude-header">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<p class="devbridge-ai-provider-magnitude-intro">
					<?php esc_html_e( 'Connect WordPress to Magnitude, an app that runs open-weight AI models on your own computer. Your prompts stay on your hardware.', 'devbridge-ai-provider-magnitude' ); ?>
				</p>
			</header>

			<?php $this->render_checked_notice(); ?>

			<div class="devbridge-ai-provider-magnitude-columns">
				<div class="devbridge-ai-provider-magnitude-column devbridge-ai-provider-magnitude-column-config">
					<?php
					$this->render_status_card( $status );
					$this->render_address_card();
					$this->render_models_card( $status );
					$this->render_remote_help();
					?>
				</div>
				<div class="devbridge-ai-provider-magnitude-column devbridge-ai-provider-magnitude-column-playground">
					<?php $this->render_playground_card( $status ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Confirms that the connection was checked, after the Check connection button.
	 *
	 * @since 0.1.0
	 */
	private function render_checked_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to show a notice; nothing is changed.
		if ( ! isset( $_GET[ self::CHECKED_ARG ] ) ) {
			return;
		}

		wp_admin_notice(
			esc_html__( 'Connection checked.', 'devbridge-ai-provider-magnitude' ),
			array(
				'type'        => 'info',
				'dismissible' => true,
			)
		);
	}

	/**
	 * Renders the connection status card.
	 *
	 * @since 0.1.0
	 *
	 * @param Connection_Status $status The connection status.
	 */
	private function render_status_card( Connection_Status $status ): void {
		$connected = $status->is_connected();
		$count     = count( $status->get_models() );
		?>
		<section class="devbridge-ai-provider-magnitude-card" aria-labelledby="devbridge-ai-provider-magnitude-status-title">
			<div class="devbridge-ai-provider-magnitude-card-header">
				<h2 id="devbridge-ai-provider-magnitude-status-title"><?php esc_html_e( 'Connection', 'devbridge-ai-provider-magnitude' ); ?></h2>
				<span class="devbridge-ai-provider-magnitude-pill <?php echo $connected ? 'devbridge-ai-provider-magnitude-pill-ok' : 'devbridge-ai-provider-magnitude-pill-error'; ?>">
					<?php echo $connected ? esc_html__( 'Connected', 'devbridge-ai-provider-magnitude' ) : esc_html__( 'Not reachable', 'devbridge-ai-provider-magnitude' ); ?>
				</span>
			</div>

			<p>
				<?php
				if ( $connected ) {
					echo esc_html(
						sprintf(
							/* translators: %d: Number of models. */
							_n( 'Magnitude answered and is running %d model.', 'Magnitude answered and is running %d models.', $count, 'devbridge-ai-provider-magnitude' ),
							$count
						)
					);
				} else {
					echo esc_html( $status->get_message() );
				}
				?>
			</p>
			<p class="devbridge-ai-provider-magnitude-meta">
				<?php esc_html_e( 'Active address:', 'devbridge-ai-provider-magnitude' ); ?>
				<code><?php echo esc_html( $this->host_provider->get_host() ); ?></code>
			</p>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CHECK_ACTION ); ?>" />
				<?php wp_nonce_field( self::CHECK_ACTION ); ?>
				<?php submit_button( __( 'Check connection', 'devbridge-ai-provider-magnitude' ), 'secondary', 'submit', false ); ?>
			</form>
		</section>
		<?php
	}

	/**
	 * Renders the server address card.
	 *
	 * @since 0.1.0
	 */
	private function render_address_card(): void {
		$locked = $this->host_provider->is_locked();
		$saved  = get_option( Host_Resolver::OPTION_NAME, '' );
		$value  = $locked ? $this->host_provider->get_host() : ( is_string( $saved ) ? $saved : '' );
		?>
		<section class="devbridge-ai-provider-magnitude-card" aria-labelledby="devbridge-ai-provider-magnitude-address-title">
			<h2 id="devbridge-ai-provider-magnitude-address-title"><?php esc_html_e( 'Server address', 'devbridge-ai-provider-magnitude' ); ?></h2>

			<form action="options.php" method="post">
				<?php settings_fields( self::SLUG ); ?>
				<label class="devbridge-ai-provider-magnitude-label" for="devbridge-ai-provider-magnitude-host">
					<?php esc_html_e( 'Address of Magnitude', 'devbridge-ai-provider-magnitude' ); ?>
				</label>
				<input
					type="url"
					id="devbridge-ai-provider-magnitude-host"
					class="regular-text devbridge-ai-provider-magnitude-input"
					name="<?php echo esc_attr( Host_Resolver::OPTION_NAME ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( Host_Resolver::DEFAULT_HOST ); ?>"
					aria-describedby="devbridge-ai-provider-magnitude-host-help"
					<?php disabled( $locked ); ?>
				/>
				<p class="description" id="devbridge-ai-provider-magnitude-host-help">
					<?php
					if ( $locked ) {
						echo esc_html(
							sprintf(
								/* translators: %s: Name of a PHP constant. */
								__( 'This address is fixed by the %s constant in wp-config.php.', 'devbridge-ai-provider-magnitude' ),
								Host_Resolver::CONSTANT_NAME
							)
						);
					} else {
						esc_html_e( 'Leave this empty when Magnitude runs on the same computer as WordPress. Only http and https addresses are accepted.', 'devbridge-ai-provider-magnitude' );
					}
					?>
				</p>
				<?php
				if ( ! $locked ) {
					submit_button( __( 'Save address', 'devbridge-ai-provider-magnitude' ), 'primary', 'submit', false );
				}
				?>
			</form>
		</section>
		<?php
	}

	/**
	 * Renders the available models card.
	 *
	 * @since 0.1.0
	 *
	 * @param Connection_Status $status The connection status.
	 */
	private function render_models_card( Connection_Status $status ): void {
		?>
		<section class="devbridge-ai-provider-magnitude-card" aria-labelledby="devbridge-ai-provider-magnitude-models-title">
			<h2 id="devbridge-ai-provider-magnitude-models-title"><?php esc_html_e( 'Available models', 'devbridge-ai-provider-magnitude' ); ?></h2>

			<?php if ( array() === $status->get_models() ) : ?>
				<p class="devbridge-ai-provider-magnitude-empty">
					<?php esc_html_e( 'No models are running. Open the Magnitude app, download a model and start it, then choose Check connection.', 'devbridge-ai-provider-magnitude' ); ?>
				</p>
			<?php else : ?>
				<ul class="devbridge-ai-provider-magnitude-models">
					<?php foreach ( $status->get_models() as $model ) : ?>
						<li class="devbridge-ai-provider-magnitude-model">
							<strong><?php echo esc_html( $model['name'] ); ?></strong>
							<code><?php echo esc_html( $model['id'] ); ?></code>
							<span class="devbridge-ai-provider-magnitude-badges">
								<?php foreach ( $model['badges'] as $badge ) : ?>
									<span class="devbridge-ai-provider-magnitude-badge"><?php echo esc_html( $badge ); ?></span>
								<?php endforeach; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Models are read from Magnitude. Only models that are running are listed.', 'devbridge-ai-provider-magnitude' ); ?>
			</p>
		</section>
		<?php
	}

	/**
	 * Renders the test playground card.
	 *
	 * @since 0.2.0
	 *
	 * @param Connection_Status $status The connection status.
	 */
	private function render_playground_card( Connection_Status $status ): void {
		$connected = $status->is_connected();
		$models    = $status->get_models();
		?>
		<section class="devbridge-ai-provider-magnitude-card devbridge-ai-provider-magnitude-playground" aria-labelledby="devbridge-ai-provider-magnitude-playground-title">
			<div class="devbridge-ai-provider-magnitude-card-header">
				<h2 id="devbridge-ai-provider-magnitude-playground-title"><?php esc_html_e( 'Test playground', 'devbridge-ai-provider-magnitude' ); ?></h2>
				<?php if ( $connected && array() !== $models ) : ?>
					<div class="devbridge-ai-provider-magnitude-playground-header-actions">
						<button type="button" class="button button-secondary" id="devbridge-playground-clear"><?php esc_html_e( 'Clear History', 'devbridge-ai-provider-magnitude' ); ?></button>
						<label class="devbridge-playground-cache-toggle">
							<input type="checkbox" id="devbridge-playground-bypass-cache" />
							<?php esc_html_e( 'Bypass Cache', 'devbridge-ai-provider-magnitude' ); ?>
						</label>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! $connected || array() === $models ) : ?>
				<p class="devbridge-ai-provider-magnitude-empty">
					<?php esc_html_e( 'Connect to Magnitude and start at least one model to use the test playground.', 'devbridge-ai-provider-magnitude' ); ?>
				</p>
			<?php else : ?>
				<label class="devbridge-ai-provider-magnitude-label" for="devbridge-playground-model">
					<?php esc_html_e( 'Model', 'devbridge-ai-provider-magnitude' ); ?>
				</label>
				<select id="devbridge-playground-model" class="devbridge-ai-provider-magnitude-select">
					<?php foreach ( $models as $model ) : ?>
						<option value="<?php echo esc_attr( $model['id'] ); ?>">
							<?php echo esc_html( $model['name'] ); ?> (<?php echo esc_html( $model['id'] ); ?>)
						</option>
					<?php endforeach; ?>
				</select>

				<details class="devbridge-playground-parameters">
					<summary><?php esc_html_e( 'Generation Parameters', 'devbridge-ai-provider-magnitude' ); ?></summary>
					<div class="devbridge-playground-controls-grid">
						<label>
							<?php esc_html_e( 'Temperature', 'devbridge-ai-provider-magnitude' ); ?> (<span id="temp-val">0.7</span>)
							<input type="range" id="param-temp" min="0" max="2" step="0.1" value="0.7" />
						</label>
						<label>
							<?php esc_html_e( 'Top P', 'devbridge-ai-provider-magnitude' ); ?> (<span id="topp-val">1.0</span>)
							<input type="range" id="param-topp" min="0" max="1" step="0.05" value="1.0" />
						</label>
						<label>
							<?php esc_html_e( 'Max Tokens', 'devbridge-ai-provider-magnitude' ); ?>
							<input type="number" id="param-max-tokens" min="1" max="8192" value="1024" />
						</label>
					</div>
				</details>

				<label class="devbridge-ai-provider-magnitude-label" for="devbridge-playground-system">
					<?php esc_html_e( 'System Instruction (optional)', 'devbridge-ai-provider-magnitude' ); ?>
				</label>
				<textarea id="devbridge-playground-system" class="devbridge-ai-provider-magnitude-textarea" rows="2" maxlength="5000" placeholder="<?php esc_attr_e( 'Optional behavior instruction...', 'devbridge-ai-provider-magnitude' ); ?>"></textarea>

				<div id="devbridge-playground-history" class="devbridge-playground-history" aria-live="polite"></div>

				<label class="devbridge-ai-provider-magnitude-label" for="devbridge-playground-prompt">
					<?php esc_html_e( 'Your prompt', 'devbridge-ai-provider-magnitude' ); ?>
				</label>
				<textarea id="devbridge-playground-prompt" class="devbridge-ai-provider-magnitude-textarea" rows="3" maxlength="10000" placeholder="<?php esc_attr_e( 'Type your prompt here... (Press Ctrl+Enter or Cmd+Enter to send)', 'devbridge-ai-provider-magnitude' ); ?>" required></textarea>

				<div class="devbridge-ai-provider-magnitude-playground-presets">
					<button type="button" class="button button-secondary" data-preset="<?php esc_attr_e( 'Tell a one-sentence joke.', 'devbridge-ai-provider-magnitude' ); ?>">
						<?php esc_html_e( '🃏 Joke', 'devbridge-ai-provider-magnitude' ); ?>
					</button>
					<button type="button" class="button button-secondary" data-preset="<?php esc_attr_e( 'Summarize WordPress in 3 sentences.', 'devbridge-ai-provider-magnitude' ); ?>">
						<?php esc_html_e( '📝 Summarize WP', 'devbridge-ai-provider-magnitude' ); ?>
					</button>
					<button type="button" class="button button-secondary" data-preset="<?php esc_attr_e( 'Return a JSON object with keys "name" and "version" for WordPress.', 'devbridge-ai-provider-magnitude' ); ?>">
						<?php esc_html_e( '{ } Return JSON', 'devbridge-ai-provider-magnitude' ); ?>
					</button>
				</div>

				<div class="devbridge-playground-actions">
					<button type="button" id="devbridge-playground-send" class="button button-primary">
						<?php esc_html_e( 'Send prompt', 'devbridge-ai-provider-magnitude' ); ?>
					</button>
					<button type="button" id="devbridge-playground-stop" class="button button-secondary" hidden>
						<?php esc_html_e( 'Stop', 'devbridge-ai-provider-magnitude' ); ?>
					</button>
					<span class="spinner" id="devbridge-playground-spinner"></span>
				</div>

				<div id="devbridge-playground-output" class="devbridge-ai-provider-magnitude-playground-output" hidden>
					<div class="devbridge-ai-provider-magnitude-playground-stats">
						<span class="devbridge-ai-provider-magnitude-stat" id="stat-latency"></span>
						<span class="devbridge-ai-provider-magnitude-stat" id="stat-tokens"></span>
						<span class="devbridge-ai-provider-magnitude-stat" id="stat-speed"></span>
						<span class="devbridge-ai-provider-magnitude-stat" id="stat-cached" hidden>
							<?php esc_html_e( 'Cached', 'devbridge-ai-provider-magnitude' ); ?>
						</span>
					</div>
					<pre class="devbridge-ai-provider-magnitude-playground-content" id="devbridge-playground-content"></pre>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Renders the help for connecting from another computer.
	 *
	 * @since 0.1.0
	 */
	private function render_remote_help(): void {
		?>
		<details class="devbridge-ai-provider-magnitude-card devbridge-ai-provider-magnitude-help">
			<summary><?php esc_html_e( 'Use Magnitude on another computer', 'devbridge-ai-provider-magnitude' ); ?></summary>
			<ol>
				<li><?php esc_html_e( 'In the Magnitude app on that computer, turn on Network access.', 'devbridge-ai-provider-magnitude' ); ?></li>
				<li><?php esc_html_e( 'If Require API key is on, copy the key and enter it for Magnitude on Settings > Connectors. On the same computer no key is needed.', 'devbridge-ai-provider-magnitude' ); ?></li>
				<li><?php esc_html_e( 'Copy the address shown under Reachable at, paste it above and save. Use this only on a network you trust.', 'devbridge-ai-provider-magnitude' ); ?></li>
			</ol>
			<p>
				<a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>">
					<?php esc_html_e( 'Open Settings > Connectors', 'devbridge-ai-provider-magnitude' ); ?>
				</a>
			</p>
		</details>
		<?php
	}
}
