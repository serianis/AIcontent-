<?php
/**
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_status = get_option( 'smartcontentai_post_status', 'draft' );

?>
<div class="wrap smartcontentai-wrap">
	<h1><?php echo esc_html__( 'Manual Generator', 'smartcontentai' ); ?></h1>

	<div class="smartcontentai-card">
		<p class="smartcontentai-muted"><?php echo esc_html__( 'Generate a preview via AJAX (no post is created), then publish when ready.', 'smartcontentai' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="smartcontentai-topic"><?php echo esc_html__( 'Topic', 'smartcontentai' ); ?></label></th>
				<td><input type="text" id="smartcontentai-topic" class="regular-text" placeholder="<?php echo esc_attr__( 'e.g. Benefits of the Mediterranean Diet', 'smartcontentai' ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="smartcontentai-keyword"><?php echo esc_html__( 'Keyword (optional)', 'smartcontentai' ); ?></label></th>
				<td><input type="text" id="smartcontentai-keyword" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="smartcontentai-publish-mode"><?php echo esc_html__( 'Publish mode', 'smartcontentai' ); ?></label></th>
				<td>
					<select id="smartcontentai-publish-mode">
						<option value="draft" <?php selected( $post_status, 'draft' ); ?>><?php echo esc_html__( 'Draft', 'smartcontentai' ); ?></option>
						<option value="publish" <?php selected( $post_status, 'publish' ); ?>><?php echo esc_html__( 'Publish', 'smartcontentai' ); ?></option>
						<option value="scheduled"><?php echo esc_html__( 'Schedule', 'smartcontentai' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="smartcontentai-scheduled-at"><?php echo esc_html__( 'Schedule date/time', 'smartcontentai' ); ?></label></th>
				<td>
					<input type="datetime-local" id="smartcontentai-scheduled-at" />
					<p class="description"><?php echo esc_html__( 'Used only when Publish mode is set to Schedule.', 'smartcontentai' ); ?></p>
				</td>
			</tr>
		</table>

		<p>
			<button id="smartcontentai-preview-btn" class="button button-secondary"><?php echo esc_html__( 'Preview', 'smartcontentai' ); ?></button>
			<button id="smartcontentai-generate-btn" class="button button-primary"><?php echo esc_html__( 'Generate post', 'smartcontentai' ); ?></button>
		</p>

		<div id="smartcontentai-generator-result"></div>

		<h2><?php echo esc_html__( 'Preview', 'smartcontentai' ); ?></h2>
		<div id="smartcontentai-preview" class="smartcontentai-preview"></div>
	</div>
</div>
