<?php
/**
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_status = get_option( 'autoblogai_post_status', 'draft' );

?>
<div class="wrap autoblogai-wrap">
	<h1><?php echo esc_html__( 'Manual Generator', 'autoblogai' ); ?></h1>

	<div class="autoblogai-card">
		<p class="autoblogai-muted"><?php echo esc_html__( 'Generate a preview via AJAX (no post is created), then publish when ready.', 'autoblogai' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="autoblogai-topic"><?php echo esc_html__( 'Topic', 'autoblogai' ); ?></label></th>
				<td><input type="text" id="autoblogai-topic" class="regular-text" placeholder="<?php echo esc_attr__( 'e.g. Benefits of the Mediterranean Diet', 'autoblogai' ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="autoblogai-keyword"><?php echo esc_html__( 'Keyword (optional)', 'autoblogai' ); ?></label></th>
				<td><input type="text" id="autoblogai-keyword" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="autoblogai-publish-mode"><?php echo esc_html__( 'Publish mode', 'autoblogai' ); ?></label></th>
				<td>
					<select id="autoblogai-publish-mode">
						<option value="draft" <?php selected( $post_status, 'draft' ); ?>><?php echo esc_html__( 'Draft', 'autoblogai' ); ?></option>
						<option value="publish" <?php selected( $post_status, 'publish' ); ?>><?php echo esc_html__( 'Publish', 'autoblogai' ); ?></option>
						<option value="scheduled"><?php echo esc_html__( 'Schedule', 'autoblogai' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="autoblogai-scheduled-at"><?php echo esc_html__( 'Schedule date/time', 'autoblogai' ); ?></label></th>
				<td>
					<input type="datetime-local" id="autoblogai-scheduled-at" />
					<p class="description"><?php echo esc_html__( 'Used only when Publish mode is set to Schedule.', 'autoblogai' ); ?></p>
				</td>
			</tr>
		</table>

		<p>
			<button id="autoblogai-preview-btn" class="button button-secondary"><?php echo esc_html__( 'Preview', 'autoblogai' ); ?></button>
			<button id="autoblogai-generate-btn" class="button button-primary"><?php echo esc_html__( 'Generate post', 'autoblogai' ); ?></button>
		</p>

		<div id="autoblogai-generator-result"></div>

		<h2><?php echo esc_html__( 'Preview', 'autoblogai' ); ?></h2>
		<div id="autoblogai-preview" class="autoblogai-preview"></div>
	</div>
</div>
