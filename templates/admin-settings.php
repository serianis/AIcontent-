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
<div class="wrap smartcontentai-wrap">
	<h1><?php echo esc_html__( 'SmartContent AI Settings', 'smartcontentai' ); ?></h1>

	<div class="smartcontentai-tabs">
		<nav class="nav-tab-wrapper">
			<a href="#general" class="nav-tab nav-tab-active"><?php echo esc_html__( 'General', 'smartcontentai' ); ?></a>
			<a href="#providers" class="nav-tab"><?php echo esc_html__( 'Providers', 'smartcontentai' ); ?></a>
			<a href="#routing" class="nav-tab"><?php echo esc_html__( 'Routing', 'smartcontentai' ); ?></a>
			<a href="#models" class="nav-tab"><?php echo esc_html__( 'Models', 'smartcontentai' ); ?></a>
			<a href="#custom-providers" class="nav-tab"><?php echo esc_html__( 'Custom Providers', 'smartcontentai' ); ?></a>
		</nav>

		<!-- General Settings Tab -->
		<div id="general" class="tab-content active">
			<div class="smartcontentai-card">
				<h3 id="general-settings-heading"><?php echo esc_html__( 'General Settings', 'smartcontentai' ); ?></h3>
				<form method="post" action="options.php" id="general-settings-form" role="form" aria-labelledby="general-settings-heading">
					<?php 
					// Generate unique nonce for this form
					$general_nonce = wp_create_nonce('smartcontentai_general_options');
					?>
					<input type="hidden" name="option_page" value="smartcontentai_general_options">
					<input type="hidden" name="action" value="update">
					<input type="hidden" id="smartcontentai_general_nonce_<?php echo uniqid('gen_', true); ?>" name="_wpnonce" value="<?php echo $general_nonce; ?>">
					<?php do_settings_sections( 'smartcontentai_general_options' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="smartcontentai_language"><?php echo esc_html__( 'Article language', 'smartcontentai' ); ?></label></th>
							<td>
								<select id="smartcontentai_language" name="smartcontentai_language">
									<option value="Greek" <?php selected( get_option( 'smartcontentai_language', 'Greek' ), 'Greek' ); ?>><?php echo esc_html__( 'Greek (EL)', 'smartcontentai' ); ?></option>
									<option value="English" <?php selected( get_option( 'smartcontentai_language', 'Greek' ), 'English' ); ?>><?php echo esc_html__( 'English (EN)', 'smartcontentai' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="smartcontentai_tone"><?php echo esc_html__( 'Tone of voice', 'smartcontentai' ); ?></label></th>
							<td>
								<select id="smartcontentai_tone" name="smartcontentai_tone">
									<option value="Professional" <?php selected( get_option( 'smartcontentai_tone', 'Professional' ), 'Professional' ); ?>><?php echo esc_html__( 'Professional', 'smartcontentai' ); ?></option>
									<option value="Casual" <?php selected( get_option( 'smartcontentai_tone', 'Professional' ), 'Casual' ); ?>><?php echo esc_html__( 'Casual', 'smartcontentai' ); ?></option>
									<option value="Journalistic" <?php selected( get_option( 'smartcontentai_tone', 'Professional' ), 'Journalistic' ); ?>><?php echo esc_html__( 'Journalistic', 'smartcontentai' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="smartcontentai_temperature"><?php echo esc_html__( 'Temperature', 'smartcontentai' ); ?></label></th>
							<td>
								<input type="number" step="0.1" min="0" max="1" id="smartcontentai_temperature" name="smartcontentai_temperature" value="<?php echo esc_attr( get_option( 'smartcontentai_temperature', 0.7 ) ); ?>" />
								<p class="description"><?php echo esc_html__( 'Controls randomness: 0 = focused, 1 = creative.', 'smartcontentai' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="smartcontentai_rate_limit_per_minute"><?php echo esc_html__( 'Rate limit (requests/min)', 'smartcontentai' ); ?></label></th>
							<td>
								<input type="number" min="1" max="300" id="smartcontentai_rate_limit_per_minute" name="smartcontentai_rate_limit_per_minute" value="<?php echo esc_attr( get_option( 'smartcontentai_rate_limit_per_minute', 60 ) ); ?>" />
								<p class="description"><?php echo esc_html__( 'Used to throttle API requests.', 'smartcontentai' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="smartcontentai_banned_words"><?php echo esc_html__( 'Banned words (comma-separated)', 'smartcontentai' ); ?></label></th>
							<td>
								<textarea id="smartcontentai_banned_words" name="smartcontentai_banned_words" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'smartcontentai_banned_words', '' ) ); ?></textarea>
								<p class="description"><?php echo esc_html__( 'If detected in title/content, generation will be rejected.', 'smartcontentai' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save General Settings', 'smartcontentai' ), 'primary', 'submit_general' ); ?>
				</form>
			</div>
		</div>

		<!-- Providers Tab -->
		<div id="providers" class="tab-content">
			<div class="smartcontentai-card">
				<h3 id="providers-settings-heading"><?php echo esc_html__( 'API Providers', 'smartcontentai' ); ?></h3>
				<p class="description"><?php echo esc_html__( 'Configure API keys for different providers. OpenRouter is recommended for access to multiple models.', 'smartcontentai' ); ?></p>
				
				<form method="post" action="options.php" id="providers-settings-form" role="form" aria-labelledby="providers-settings-heading">
					<?php 
					// Generate unique nonce for this form
					$providers_nonce = wp_create_nonce('smartcontentai_providers_options');
					?>
					<input type="hidden" name="option_page" value="smartcontentai_providers_options">
					<input type="hidden" name="action" value="update">
					<input type="hidden" id="smartcontentai_providers_nonce_<?php echo uniqid('prov_', true); ?>" name="_wpnonce" value="<?php echo $providers_nonce; ?>">
					<?php do_settings_sections( 'smartcontentai_providers_options' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="smartcontentai_openrouter_api_key"><?php echo esc_html__( 'OpenRouter API Key', 'smartcontentai' ); ?></label></th>
							<td>
								<select id="smartcontentai_openrouter_api_key_model" name="smartcontentai_openrouter_api_key_model" class="regular-text">
							<option value="" <?php selected( get_option( 'smartcontentai_openrouter_api_key_model', '' ), '' ); ?>><?php echo esc_html__( 'Auto-select (Recommended)', 'smartcontentai' ); ?></option>
							<option value="anthropic/claude-4.5-sonnet" <?php selected( get_option( 'smartcontentai_openrouter_api_key_model', '' ), 'anthropic/claude-4.5-sonnet' ); ?>><?php echo esc_html__( 'Claude 4.5 Sonnet (Premium)', 'smartcontentai' ); ?></option>
							<option value="openai/gpt-4o" <?php selected( get_option( 'smartcontentai_openrouter_api_key_model', '' ), 'openai/gpt-4o' ); ?>><?php echo esc_html__( 'GPT-4o (Premium)', 'smartcontentai' ); ?></option>
							<option value="openai/gpt-4o-mini" <?php selected( get_option( 'smartcontentai_openrouter_api_key_model', '' ), 'openai/gpt-4o-mini' ); ?>><?php echo esc_html__( 'GPT-4o Mini (Standard)', 'smartcontentai' ); ?></option>
							<option value="anthropic/claude-3.5-sonnet" <?php selected( get_option( 'smartcontentai_openrouter_api_key_model', '' ), 'anthropic/claude-3.5-sonnet' ); ?>><?php echo esc_html__( 'Claude 3.5 Sonnet (Standard)', 'smartcontentai' ); ?></option>
							<option value="google/gemini-2.0-flash-exp" <?php selected( get_option( 'smartcontentai_openrouter_api_key_model', '' ), 'google/gemini-2.0-flash-exp' ); ?>><?php echo esc_html__( 'Gemini 2.0 Flash (Standard)', 'smartcontentai' ); ?></option>
							<option value="google/gemini-1.5-flash" <?php selected( get_option( 'smartcontentai_openrouter_api_key_model', '' ), 'google/gemini-1.5-flash' ); ?>><?php echo esc_html__( 'Gemini 1.5 Flash (Cheap)', 'smartcontentai' ); ?></option>
						</select>
								<br>
								<input type="password" id="smartcontentai_openrouter_api_key" name="smartcontentai_openrouter_api_key" value="" class="regular-text" autocomplete="new-password" />
								<p class="description"><?php echo esc_html__( 'Recommended: Access to Claude, GPT-4, Gemini, and more.', 'smartcontentai' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="smartcontentai_openai_api_key"><?php echo esc_html__( 'OpenAI API Key', 'smartcontentai' ); ?></label></th>
							<td>
								<select id="smartcontentai_openai_api_key_model" name="smartcontentai_openai_api_key_model" class="regular-text">
							<option value="" <?php selected( get_option( 'smartcontentai_openai_api_key_model', '' ), '' ); ?>><?php echo esc_html__( 'Auto-select (Recommended)', 'smartcontentai' ); ?></option>
							<option value="gpt-4o" <?php selected( get_option( 'smartcontentai_openai_api_key_model', '' ), 'gpt-4o' ); ?>><?php echo esc_html__( 'GPT-4o (Premium)', 'smartcontentai' ); ?></option>
							<option value="gpt-4o-mini" <?php selected( get_option( 'smartcontentai_openai_api_key_model', '' ), 'gpt-4o-mini' ); ?>><?php echo esc_html__( 'GPT-4o Mini (Standard)', 'smartcontentai' ); ?></option>
							<option value="gpt-3.5-turbo" <?php selected( get_option( 'smartcontentai_openai_api_key_model', '' ), 'gpt-3.5-turbo' ); ?>><?php echo esc_html__( 'GPT-3.5 Turbo (Cheap)', 'smartcontentai' ); ?></option>
						</select>
								<br>
								<input type="password" id="smartcontentai_openai_api_key" name="smartcontentai_openai_api_key" value="" class="regular-text" autocomplete="new-password" />
								<p class="description"><?php echo esc_html__( 'For direct access to GPT models.', 'smartcontentai' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="smartcontentai_anthropic_api_key"><?php echo esc_html__( 'Anthropic API Key', 'smartcontentai' ); ?></label></th>
							<td>
								<select id="smartcontentai_anthropic_api_key_model" name="smartcontentai_anthropic_api_key_model" class="regular-text">
							<option value="" <?php selected( get_option( 'smartcontentai_anthropic_api_key_model', '' ), '' ); ?>><?php echo esc_html__( 'Auto-select (Recommended)', 'smartcontentai' ); ?></option>
							<option value="claude-4.5-sonnet" <?php selected( get_option( 'smartcontentai_anthropic_api_key_model', '' ), 'claude-4.5-sonnet' ); ?>><?php echo esc_html__( 'Claude 4.5 Sonnet (Premium)', 'smartcontentai' ); ?></option>
							<option value="claude-4.5-haiku" <?php selected( get_option( 'smartcontentai_anthropic_api_key_model', '' ), 'claude-4.5-haiku' ); ?>><?php echo esc_html__( 'Claude 4.5 Haiku (Premium)', 'smartcontentai' ); ?></option>
							<option value="claude-3.5-sonnet" <?php selected( get_option( 'smartcontentai_anthropic_api_key_model', '' ), 'claude-3.5-sonnet' ); ?>><?php echo esc_html__( 'Claude 3.5 Sonnet (Standard)', 'smartcontentai' ); ?></option>
							<option value="claude-3-haiku" <?php selected( get_option( 'smartcontentai_anthropic_api_key_model', '' ), 'claude-3-haiku' ); ?>><?php echo esc_html__( 'Claude 3 Haiku (Cheap)', 'smartcontentai' ); ?></option>
						</select>
								<br>
								<input type="password" id="smartcontentai_anthropic_api_key" name="smartcontentai_anthropic_api_key" value="" class="regular-text" autocomplete="new-password" />
								<p class="description"><?php echo esc_html__( 'For direct access to Claude models.', 'smartcontentai' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="smartcontentai_gemini_api_key"><?php echo esc_html__( 'Google Gemini API Key', 'smartcontentai' ); ?></label></th>
							<td>
								<select id="smartcontentai_gemini_api_key_model" name="smartcontentai_gemini_api_key_model" class="regular-text">
							<option value="" <?php selected( get_option( 'smartcontentai_gemini_api_key_model', '' ), '' ); ?>><?php echo esc_html__( 'Auto-select (Recommended)', 'smartcontentai' ); ?></option>
							<option value="gemini-2.0-flash-exp" <?php selected( get_option( 'smartcontentai_gemini_api_key_model', '' ), 'gemini-2.0-flash-exp' ); ?>><?php echo esc_html__( 'Gemini 2.0 Flash (Standard)', 'smartcontentai' ); ?></option>
							<option value="gemini-1.5-flash" <?php selected( get_option( 'smartcontentai_gemini_api_key_model', '' ), 'gemini-1.5-flash' ); ?>><?php echo esc_html__( 'Gemini 1.5 Flash (Cheap)', 'smartcontentai' ); ?></option>
							<option value="gemini-1.5-pro" <?php selected( get_option( 'smartcontentai_gemini_api_key_model', '' ), 'gemini-1.5-pro' ); ?>><?php echo esc_html__( 'Gemini 1.5 Pro (Premium)', 'smartcontentai' ); ?></option>
						</select>
								<br>
								<input type="password" id="smartcontentai_gemini_api_key" name="smartcontentai_gemini_api_key" value="" class="regular-text" autocomplete="new-password" />
								<p class="description"><?php echo esc_html__( 'For direct access to Gemini models. Get your key from Google AI Studio.', 'smartcontentai' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Provider Keys', 'smartcontentai' ), 'primary', 'submit_providers' ); ?>
				</form>
			</div>
		</div>

		<!-- Routing Tab -->
		<div id="routing" class="tab-content">
			<div class="smartcontentai-card">
				<h3 id="routing-settings-heading"><?php echo esc_html__( 'Routing Configuration', 'smartcontentai' ); ?></h3>
				<p class="description"><?php echo esc_html__( 'Configure how the system selects models for different types of content.', 'smartcontentai' ); ?></p>
				
				<form method="post" action="options.php" id="routing-settings-form" role="form" aria-labelledby="routing-settings-heading">
					<?php 
					// Generate unique nonce for this form
					$routing_nonce = wp_create_nonce('smartcontentai_routing_options');
					?>
					<input type="hidden" name="option_page" value="smartcontentai_routing_options">
					<input type="hidden" name="action" value="update">
					<input type="hidden" id="smartcontentai_routing_nonce_<?php echo uniqid('rout_', true); ?>" name="_wpnonce" value="<?php echo $routing_nonce; ?>">
					<?php do_settings_sections( 'smartcontentai_routing_options' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="smartcontentai_routing_mode"><?php echo esc_html__( 'Routing Mode', 'smartcontentai' ); ?></label></th>
							<td>
								<select id="smartcontentai_routing_mode" name="smartcontentai_routing_mode">
									<option value="auto" <?php selected( get_option( 'smartcontentai_routing_mode', 'auto' ), 'auto' ); ?>><?php echo esc_html__( 'Auto (Recommended)', 'smartcontentai' ); ?></option>
									<option value="fixed" <?php selected( get_option( 'smartcontentai_routing_mode', 'auto' ), 'fixed' ); ?>><?php echo esc_html__( 'Fixed Model', 'smartcontentai' ); ?></option>
									<option value="manual" <?php selected( get_option( 'smartcontentai_routing_mode', 'auto' ), 'manual' ); ?>><?php echo esc_html__( 'Manual Selection', 'smartcontentai' ); ?></option>
								</select>
								<p class="description"><?php echo esc_html__( 'Auto: Selects model based on complexity. Fixed: Always uses the same model. Manual: Use manually selected models below.', 'smartcontentai' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="smartcontentai_fallback_enabled">
									<?php echo esc_html__( 'Enable Fallback', 'smartcontentai' ); ?>
								</label>
							</th>
							<td>
								<input type="checkbox" id="smartcontentai_fallback_enabled" name="smartcontentai_fallback_enabled" value="1" <?php checked( get_option( 'smartcontentai_fallback_enabled', 1 ) ); ?> />
								<p class="description"><?php echo esc_html__( 'Automatically switch to alternative models if primary fails.', 'smartcontentai' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="smartcontentai_fixed_model"><?php echo esc_html__( 'Fixed Model', 'smartcontentai' ); ?></label></th>
							<td>
								<select id="smartcontentai_fixed_model" name="smartcontentai_fixed_model">
									<option value=""><?php echo esc_html__( 'Select a model...', 'smartcontentai' ); ?></option>
								</select>
								<p class="description"><?php echo esc_html__( 'When using Fixed mode, always use this model.', 'smartcontentai' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Routing Settings', 'smartcontentai' ), 'primary', 'submit_routing' ); ?>
				</form>

				<!-- Manual Model Selection - Separated Form -->
				<div id="manual-model-selection" style="margin-top: 30px; display: none;">
					<div class="smartcontentai-card" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd;">
						<h4 id="manual-models-heading"><?php echo esc_html__( 'Manual Model Selection', 'smartcontentai' ); ?></h4>
						<p class="description"><?php echo esc_html__( 'Select specific models for each tier when using Manual routing mode.', 'smartcontentai' ); ?></p>
						
						<form method="post" action="options.php" id="manual-models-form" role="form" aria-labelledby="manual-models-heading">
							<?php 
							// Generate unique nonce for this form
							$manual_nonce = wp_create_nonce('smartcontentai_manual_options');
							?>
							<input type="hidden" name="option_page" value="smartcontentai_manual_options">
							<input type="hidden" name="action" value="update">
							<input type="hidden" id="smartcontentai_manual_nonce_<?php echo uniqid('man_', true); ?>" name="_wpnonce" value="<?php echo $manual_nonce; ?>">
							<?php do_settings_sections( 'smartcontentai_manual_options' ); ?>

							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="smartcontentai_cheap_model"><?php echo esc_html__( 'Cheap Tier Model', 'smartcontentai' ); ?></label></th>
									<td>
										<select id="smartcontentai_cheap_model" name="smartcontentai_cheap_model">
											<option value=""><?php echo esc_html__( 'Auto-select best cheap model', 'smartcontentai' ); ?></option>
										</select>
										<p class="description"><?php echo esc_html__( 'Model for simple content generation (low cost).', 'smartcontentai' ); ?></p>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="smartcontentai_standard_model"><?php echo esc_html__( 'Standard Tier Model', 'smartcontentai' ); ?></label></th>
									<td>
										<select id="smartcontentai_standard_model" name="smartcontentai_standard_model">
											<option value=""><?php echo esc_html__( 'Auto-select best standard model', 'smartcontentai' ); ?></option>
										</select>
										<p class="description"><?php echo esc_html__( 'Model for standard content generation (balanced cost/quality).', 'smartcontentai' ); ?></p>
									</td>
								</tr>

								<tr>
									<th scope="row"><label for="smartcontentai_premium_model"><?php echo esc_html__( 'Premium Tier Model', 'smartcontentai' ); ?></label></th>
									<td>
										<select id="smartcontentai_premium_model" name="smartcontentai_premium_model">
											<option value=""><?php echo esc_html__( 'Auto-select best premium model', 'smartcontentai' ); ?></option>
										</select>
										<p class="description"><?php echo esc_html__( 'Model for complex content generation (high quality).', 'smartcontentai' ); ?></p>
									</td>
								</tr>
							</table>

							<?php submit_button( __( 'Save Model Selection', 'smartcontentai' ), 'primary', 'submit_manual' ); ?>
						</form>
					</div>
				</div>
			</div>
		</div>

		<!-- Models Tab -->
		<div id="models" class="tab-content">
			<div class="smartcontentai-card">
				<h3><?php echo esc_html__( 'Available Models', 'smartcontentai' ); ?></h3>
				<p class="description"><?php echo esc_html__( 'All available models from configured providers. Configure provider keys above to enable models.', 'smartcontentai' ); ?></p>
				
				<div id="models-list">
					<div class="loading-spinner"></div>
					<p><?php echo esc_html__( 'Loading models...', 'smartcontentai' ); ?></p>
				</div>
			</div>
		</div>

		<!-- Custom Providers Tab -->
		<div id="custom-providers" class="tab-content">
			<div class="smartcontentai-card">
				<h3><?php echo esc_html__( 'Custom Providers', 'smartcontentai' ); ?></h3>
				<p class="description"><?php echo esc_html__( 'Add custom AI providers with your own API endpoints, authentication, and models.', 'smartcontentai' ); ?></p>
				
				<div class="custom-providers-section">
					<!-- Add/Edit Provider Form -->
					<div class="provider-form-wrapper">
						<h4 id="provider-form-title" aria-labelledby="custom-provider-heading"><?php echo esc_html__( 'Add New Provider', 'smartcontentai' ); ?></h4>
						
						<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="custom-provider-form" role="form" aria-labelledby="custom-provider-heading">
							<input type="hidden" name="action" value="smartcontentai_save_custom_provider">
							<?php wp_nonce_field( 'smartcontentai_custom_provider_nonce' ); ?>
							<input type="hidden" name="provider_id" id="provider_id" value="">
							
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="provider_name"><?php echo esc_html__( 'Provider Name', 'smartcontentai' ); ?></label>
									</th>
									<td>
										<input type="text" id="provider_name" name="provider_name" class="regular-text" required>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="provider_slug"><?php echo esc_html__( 'Provider Slug', 'smartcontentai' ); ?></label>
									</th>
									<td>
										<input type="text" id="provider_slug" name="provider_slug" class="regular-text" required>
										<p class="description"><?php echo esc_html__( 'Unique identifier for this provider (lowercase, no spaces)', 'smartcontentai' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="base_url"><?php echo esc_html__( 'Base URL', 'smartcontentai' ); ?></label>
									</th>
									<td>
										<input type="url" id="base_url" name="base_url" class="regular-text" placeholder="https://api.example.com/v1" required>
										<p class="description"><?php echo esc_html__( 'The base URL for your API endpoint', 'smartcontentai' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="auth_type"><?php echo esc_html__( 'Authentication Type', 'smartcontentai' ); ?></label>
									</th>
									<td>
										<select id="auth_type" name="auth_type">
											<option value="api_key"><?php echo esc_html__( 'API Key (Authorization: Bearer)', 'smartcontentai' ); ?></option>
											<option value="bearer"><?php echo esc_html__( 'Bearer Token', 'smartcontentai' ); ?></option>
											<option value="custom_header"><?php echo esc_html__( 'Custom Header', 'smartcontentai' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="username"><?php echo esc_html__( 'Username (Optional)', 'smartcontentai' ); ?></label>
									</th>
									<td>
										<input type="text" id="username" name="username" class="regular-text" style="display: none;" autocomplete="username">
										<p class="description"><?php echo esc_html__( 'Username for authentication (optional)', 'smartcontentai' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="api_key"><?php echo esc_html__( 'API Key', 'smartcontentai' ); ?></label>
									</th>
									<td>
										<input type="password" id="api_key" name="api_key" class="regular-text" autocomplete="current-password">
										<p class="description"><?php echo esc_html__( 'Your API key or token', 'smartcontentai' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="custom_headers"><?php echo esc_html__( 'Custom Headers', 'smartcontentai' ); ?></label>
									</th>
									<td>
										<textarea id="custom_headers" name="custom_headers" rows="4" class="large-text" placeholder="X-Custom-Header: value
Another-Header: value2"></textarea>
										<p class="description"><?php echo esc_html__( 'Additional headers to include with requests (one per line, format: Header-Name: value)', 'smartcontentai' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="enabled"><?php echo esc_html__( 'Enabled', 'smartcontentai' ); ?></label>
									</th>
									<td>
										<input type="checkbox" id="enabled" name="enabled" value="1" checked>
										<span class="description"><?php echo esc_html__( 'Enable this provider', 'smartcontentai' ); ?></span>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="is_default"><?php echo esc_html__( 'Set as Default', 'smartcontentai' ); ?></label>
									</th>
									<td>
										<input type="checkbox" id="is_default" name="is_default" value="1">
										<span class="description"><?php echo esc_html__( 'Use this as the default custom provider when available', 'smartcontentai' ); ?></span>
									</td>
								</tr>
							</table>
							
							<p class="submit">
								<button type="submit" class="button button-primary" id="save-provider-btn"><?php echo esc_html__( 'Save Provider', 'smartcontentai' ); ?></button>
								<button type="button" class="button" id="test-provider-btn" style="margin-left: 10px;"><?php echo esc_html__( 'Test Connection', 'smartcontentai' ); ?></button>
								<button type="button" class="button" id="cancel-edit-btn" style="display: none;"><?php echo esc_html__( 'Cancel', 'smartcontentai' ); ?></button>
							</p>
						</form>
					</div>
					
					<!-- Existing Providers List -->
					<div class="existing-providers-wrapper">
						<h4><?php echo esc_html__( 'Configured Providers', 'smartcontentai' ); ?></h4>
						<div id="custom-providers-list">
							<div class="loading-spinner"></div>
							<p><?php echo esc_html__( 'Loading custom providers...', 'smartcontentai' ); ?></p>
						</div>
					</div>
					
					<!-- Models Management Section -->
					<div class="models-management-wrapper" id="models-management" style="display: none;">
						<h4 id="models-section-title" aria-labelledby="custom-model-heading"><?php echo esc_html__( 'Models for Provider', 'smartcontentai' ); ?></h4>
						
						<!-- Add Model Form -->
						<div class="model-form-wrapper">
							<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="custom-model-form" role="form" aria-labelledby="custom-model-heading">
								<input type="hidden" name="action" value="smartcontentai_save_custom_model">
								<?php wp_nonce_field( 'smartcontentai_custom_model_nonce' ); ?>
								<input type="hidden" name="provider_id" id="model_provider_id" value="">
								<input type="hidden" name="model_id" id="model_id" value="">
								
								<table class="form-table">
									<tr>
										<th scope="row">
											<label for="model_slug"><?php echo esc_html__( 'Model Slug', 'smartcontentai' ); ?></label>
										</th>
										<td>
											<input type="text" id="model_slug" name="model_slug" class="regular-text" required>
											<p class="description"><?php echo esc_html__( 'The model identifier used in API calls', 'smartcontentai' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="model_name"><?php echo esc_html__( 'Model Name', 'smartcontentai' ); ?></label>
										</th>
										<td>
											<input type="text" id="model_name" name="model_name" class="regular-text" required>
											<p class="description"><?php echo esc_html__( 'Display name for the model', 'smartcontentai' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="tier"><?php echo esc_html__( 'Tier', 'smartcontentai' ); ?></label>
										</th>
										<td>
											<select id="tier" name="tier">
												<option value="cheap"><?php echo esc_html__( 'Cheap', 'smartcontentai' ); ?></option>
												<option value="standard" selected><?php echo esc_html__( 'Standard', 'smartcontentai' ); ?></option>
												<option value="premium"><?php echo esc_html__( 'Premium', 'smartcontentai' ); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="max_tokens"><?php echo esc_html__( 'Max Tokens', 'smartcontentai' ); ?></label>
										</th>
										<td>
											<input type="number" id="max_tokens" name="max_tokens" min="1" max="1000000" value="4096">
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="cost_per_1k"><?php echo esc_html__( 'Cost per 1K tokens', 'smartcontentai' ); ?></label>
										</th>
										<td>
											<input type="number" id="cost_per_1k" name="cost_per_1k" step="0.000001" min="0" value="0.000000">
											<p class="description"><?php echo esc_html__( 'Cost per 1,000 tokens (USD)', 'smartcontentai' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="context_window"><?php echo esc_html__( 'Context Window', 'smartcontentai' ); ?></label>
										</th>
										<td>
											<input type="number" id="context_window" name="context_window" min="1" max="1000000" value="4096">
											<p class="description"><?php echo esc_html__( 'Maximum context length', 'smartcontentai' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="model_enabled"><?php echo esc_html__( 'Enabled', 'smartcontentai' ); ?></label>
										</th>
										<td>
											<input type="checkbox" id="model_enabled" name="enabled" value="1" checked>
										</td>
									</tr>
								</table>
								
								<p class="submit">
									<button type="submit" class="button button-primary" id="save-model-btn"><?php echo esc_html__( 'Save Model', 'smartcontentai' ); ?></button>
									<button type="button" class="button" id="cancel-model-btn"><?php echo esc_html__( 'Cancel', 'smartcontentai' ); ?></button>
								</p>
							</form>
						</div>
						
						<!-- Models List -->
						<div class="models-list-wrapper">
							<div id="provider-models-list">
								<p><?php echo esc_html__( 'No models configured for this provider.', 'smartcontentai' ); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.smartcontentai-tabs .nav-tab-wrapper {
    margin-bottom: 20px;
    border-bottom: 1px solid #ccc;
}

.smartcontentai-tabs .nav-tab {
    display: inline-block;
    padding: 10px 15px;
    margin-right: 5px;
    border: 1px solid #ccc;
    border-bottom: none;
    background: #f1f1f1;
    text-decoration: none;
    color: #0073aa;
    border-radius: 3px 3px 0 0;
}

.smartcontentai-tabs .nav-tab-active {
    background: #fff;
    color: #000;
    border-bottom: 1px solid #fff;
    margin-bottom: -1px;
}

.smartcontentai-tabs .tab-content {
    display: none;
}

.smartcontentai-tabs .tab-content.active {
    display: block;
}

.loading-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #0073aa;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Custom Providers Styles */
.custom-providers-section {
    margin-top: 20px;
}

.provider-form-wrapper {
    background: #f9f9f9;
    padding: 20px;
    margin-bottom: 30px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.existing-providers-wrapper {
    margin-bottom: 30px;
}

.models-management-wrapper {
    background: #f0f8ff;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid #b0d4f1;
    border-radius: 4px;
}

.model-form-wrapper {
    background: #fff;
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.custom-providers-grid,
.models-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.provider-card,
.model-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.provider-card.enabled {
    border-left: 4px solid #46b450;
}

.provider-card.disabled {
    border-left: 4px solid #dc3232;
    opacity: 0.7;
}

.provider-card h4,
.model-card h5 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #23282d;
}

.provider-card p,
.model-card p {
    margin: 5px 0;
    font-size: 13px;
    color: #666;
}

.provider-actions,
.model-actions {
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.provider-actions .button,
.model-actions .button {
    margin-right: 5px;
    margin-bottom: 5px;
}

.status.enabled {
    color: #46b450;
    font-weight: 600;
}

.status.disabled {
    color: #dc3232;
    font-weight: 600;
}

.status.available {
    color: #46b450;
    font-weight: 600;
}

.status.unavailable {
    color: #dc3232;
    font-weight: 600;
}

@media (max-width: 768px) {
    .custom-providers-grid,
    .models-grid {
        grid-template-columns: 1fr;
    }
    
    .provider-actions,
    .model-actions {
        text-align: center;
    }
    
    .provider-actions .button,
    .model-actions .button {
        display: block;
        width: 100%;
        margin-bottom: 10px;
    }
}
</style>
