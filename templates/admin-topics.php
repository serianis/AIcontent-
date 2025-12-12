<?php
/**
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$topics      = $data['topics'];
$nonces      = $data['nonces'];
$actions_url = admin_url( 'admin-post.php' );

?>
<div class="wrap autoblogai-wrap">
	<h1><?php echo esc_html__( 'Topics & Keywords', 'autoblogai' ); ?></h1>

	<div class="autoblogai-grid">
		<div class="autoblogai-card">
			<h2><?php echo esc_html__( 'Add topic', 'autoblogai' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $actions_url ); ?>">
				<input type="hidden" name="action" value="autoblogai_add_topic" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['topics'] ); ?>" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="autoblogai_topic_name"><?php echo esc_html__( 'Topic', 'autoblogai' ); ?></label></th>
						<td><input id="autoblogai_topic_name" name="topic" type="text" class="regular-text" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="autoblogai_topic_keyword"><?php echo esc_html__( 'Keyword (optional)', 'autoblogai' ); ?></label></th>
						<td><input id="autoblogai_topic_keyword" name="keyword" type="text" class="regular-text" /></td>
					</tr>
				</table>

				<?php submit_button( __( 'Add', 'autoblogai' ) ); ?>
			</form>
		</div>

		<div class="autoblogai-card">
			<h2><?php echo esc_html__( 'Current topics', 'autoblogai' ); ?></h2>

			<?php if ( empty( $topics ) ) : ?>
				<p class="autoblogai-muted"><?php echo esc_html__( 'No topics yet.', 'autoblogai' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Topic', 'autoblogai' ); ?></th>
							<th><?php echo esc_html__( 'Keyword', 'autoblogai' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'autoblogai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $topics as $index => $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['topic'] ); ?></td>
								<td><?php echo esc_html( $row['keyword'] ); ?></td>
								<td class="autoblogai-table-actions">
									<div class="autoblogai-inline-actions">
										<form method="post" action="<?php echo esc_url( $actions_url ); ?>">
											<input type="hidden" name="action" value="autoblogai_enqueue_topic" />
											<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['topics'] ); ?>" />
											<input type="hidden" name="index" value="<?php echo esc_attr( (string) $index ); ?>" />
											<?php submit_button( __( 'Queue', 'autoblogai' ), 'secondary', 'submit', false ); ?>
										</form>

										<form method="post" action="<?php echo esc_url( $actions_url ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this topic?', 'autoblogai' ) ); ?>');">
											<input type="hidden" name="action" value="autoblogai_delete_topic" />
											<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['topics'] ); ?>" />
											<input type="hidden" name="index" value="<?php echo esc_attr( (string) $index ); ?>" />
											<?php submit_button( __( 'Delete', 'autoblogai' ), 'delete', 'submit', false ); ?>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
