<?php
/**
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$security = $data['security'];

$has_api_key = (bool) $data['has_api_key'];

?>
<div class="wrap autoblogai-wrap">
	<h1><?php echo esc_html__( 'AutoblogAI Settings', 'autoblogai' ); ?></h1>

	<div class="autoblogai-card">
		<form method="post" action="options.php">
			<?php settings_fields( 'autoblogai_options' ); ?>
			<?php do_settings_sections( 'autoblogai_options' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="autoblogai_api_key"><?php echo esc_html__( 'Gemini API key', 'autoblogai' ); ?></label></th>
					<td>
						<input type="password" id="autoblogai_api_key" name="autoblogai_api_key" value="" class="regular-text" autocomplete="new-password" />
						<p class="description">
							<?php echo esc_html( $has_api_key ? __( 'A key is already saved (encrypted). Leave blank to keep it.', 'autoblogai' ) : __( 'No key saved yet.', 'autoblogai' ) ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="autoblogai_language"><?php echo esc_html__( 'Article language', 'autoblogai' ); ?></label></th>
					<td>
						<select id="autoblogai_language" name="autoblogai_language">
							<option value="Greek" <?php selected( get_option( 'autoblogai_language', 'Greek' ), 'Greek' ); ?>><?php echo esc_html__( 'Greek (EL)', 'autoblogai' ); ?></option>
							<option value="English" <?php selected( get_option( 'autoblogai_language', 'Greek' ), 'English' ); ?>><?php echo esc_html__( 'English (EN)', 'autoblogai' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="autoblogai_tone"><?php echo esc_html__( 'Tone of voice', 'autoblogai' ); ?></label></th>
					<td>
						<select id="autoblogai_tone" name="autoblogai_tone">
							<option value="Professional" <?php selected( get_option( 'autoblogai_tone', 'Professional' ), 'Professional' ); ?>><?php echo esc_html__( 'Professional', 'autoblogai' ); ?></option>
							<option value="Casual" <?php selected( get_option( 'autoblogai_tone', 'Professional' ), 'Casual' ); ?>><?php echo esc_html__( 'Casual', 'autoblogai' ); ?></option>
							<option value="Journalistic" <?php selected( get_option( 'autoblogai_tone', 'Professional' ), 'Journalistic' ); ?>><?php echo esc_html__( 'Journalistic', 'autoblogai' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="autoblogai_rate_limit_per_minute"><?php echo esc_html__( 'Rate limit (requests/min)', 'autoblogai' ); ?></label></th>
					<td>
						<input type="number" min="1" max="300" id="autoblogai_rate_limit_per_minute" name="autoblogai_rate_limit_per_minute" value="<?php echo esc_attr( get_option( 'autoblogai_rate_limit_per_minute', 60 ) ); ?>" />
						<p class="description"><?php echo esc_html__( 'Used to throttle Gemini requests in wp_cron and manual generation.', 'autoblogai' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="autoblogai_banned_words"><?php echo esc_html__( 'Banned words (comma-separated)', 'autoblogai' ); ?></label></th>
					<td>
						<textarea id="autoblogai_banned_words" name="autoblogai_banned_words" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'autoblogai_banned_words', '' ) ); ?></textarea>
						<p class="description"><?php echo esc_html__( 'If detected in title/content, generation will be rejected.', 'autoblogai' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save settings', 'autoblogai' ) ); ?>
		</form>
	</div>
</div>
