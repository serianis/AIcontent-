<?php
/**
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nonces      = $data['nonces'];
$actions_url = admin_url( 'admin-post.php' );

$prompt_template   = get_option( 'smartcontentai_prompt_template_custom', '' );
$article_template  = get_option( 'smartcontentai_article_template', '' );

?>
<div class="wrap smartcontentai-wrap">
	<h1><?php echo esc_html__( 'Templates', 'smartcontentai' ); ?></h1>

	<div class="smartcontentai-card">
		<form method="post" action="<?php echo esc_url( $actions_url ); ?>">
			<input type="hidden" name="action" value="smartcontentai_save_templates" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['templates'] ); ?>" />

			<h2><?php echo esc_html__( 'Prompt template (Gemini)', 'smartcontentai' ); ?></h2>
			<p class="smartcontentai-muted"><?php echo esc_html__( 'Optional. If set, it overrides the default prompt. Supported placeholders: {{topic}}, {{keyword}}, {{locale}}, {{tone}}.', 'smartcontentai' ); ?></p>
			<textarea name="prompt_template" rows="10" class="large-text code"><?php echo esc_textarea( $prompt_template ); ?></textarea>

			<hr />

			<h2><?php echo esc_html__( 'Article template (WordPress content)', 'smartcontentai' ); ?></h2>
			<p class="smartcontentai-muted"><?php echo esc_html__( 'Optional. If set, it controls the final post content. Supported placeholders: {{title}}, {{lede}}, {{content_html}}, {{cta}}, {{faq_html}}, {{meta_title}}, {{meta_description}}, {{keywords}}.', 'smartcontentai' ); ?></p>
			<textarea name="article_template" rows="10" class="large-text code"><?php echo esc_textarea( $article_template ); ?></textarea>

			<?php submit_button( __( 'Save templates', 'smartcontentai' ) ); ?>
		</form>
	</div>
</div>
