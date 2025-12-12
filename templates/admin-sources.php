<?php
/**
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nonces      = $data['nonces'];
$actions_url = admin_url( 'admin-post.php' );

$source_type = get_option( 'autoblogai_source_type', 'prompt' );
$rss_url     = get_option( 'autoblogai_rss_url', '' );

?>
<div class="wrap autoblogai-wrap">
	<h1><?php echo esc_html__( 'Content Sources', 'autoblogai' ); ?></h1>

	<div class="autoblogai-card">
		<form method="post" action="<?php echo esc_url( $actions_url ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="autoblogai_save_sources" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['sources'] ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Source type', 'autoblogai' ); ?></th>
					<td>
						<label><input type="radio" name="source_type" value="prompt" <?php checked( $source_type, 'prompt' ); ?> /> <?php echo esc_html__( 'Prompt (manual topics)', 'autoblogai' ); ?></label><br />
						<label><input type="radio" name="source_type" value="rss" <?php checked( $source_type, 'rss' ); ?> /> <?php echo esc_html__( 'RSS feed', 'autoblogai' ); ?></label><br />
						<label><input type="radio" name="source_type" value="csv" <?php checked( $source_type, 'csv' ); ?> /> <?php echo esc_html__( 'CSV upload', 'autoblogai' ); ?></label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="autoblogai_rss_url"><?php echo esc_html__( 'RSS URL', 'autoblogai' ); ?></label></th>
					<td>
						<input type="url" id="autoblogai_rss_url" name="rss_url" class="regular-text" value="<?php echo esc_attr( $rss_url ); ?>" placeholder="https://example.com/feed" />
						<p class="description"><?php echo esc_html__( 'If enabled, you can later build a feeder that converts RSS items into topics.', 'autoblogai' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="autoblogai_csv_file"><?php echo esc_html__( 'CSV file', 'autoblogai' ); ?></label></th>
					<td>
						<input type="file" id="autoblogai_csv_file" name="csv_file" accept=".csv,text/csv" />
						<p class="description"><?php echo esc_html__( 'Expected columns: topic, keyword (optional). Rows will be added to Topics.', 'autoblogai' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save sources', 'autoblogai' ) ); ?>
		</form>
	</div>
</div>
