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
<div class="wrap smartcontentai-wrap">
	<h1><?php echo esc_html__( 'Topics & Keywords', 'smartcontentai' ); ?></h1>

	<div class="smartcontentai-grid">
		<div class="smartcontentai-card">
			<h2><?php echo esc_html__( 'Add topic', 'smartcontentai' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $actions_url ); ?>">
				<input type="hidden" name="action" value="smartcontentai_add_topic" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['topics'] ); ?>" />

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="smartcontentai_topic_name"><?php echo esc_html__( 'Topic', 'smartcontentai' ); ?></label></th>
						<td><input id="smartcontentai_topic_name" name="topic" type="text" class="regular-text" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="smartcontentai_topic_keyword"><?php echo esc_html__( 'Keyword (optional)', 'smartcontentai' ); ?></label></th>
						<td><input id="smartcontentai_topic_keyword" name="keyword" type="text" class="regular-text" /></td>
					</tr>
				</table>

				<?php submit_button( __( 'Add', 'smartcontentai' ) ); ?>
			</form>
		</div>

		<div class="smartcontentai-card">
			<h2><?php echo esc_html__( 'Current topics', 'smartcontentai' ); ?></h2>

			<?php if ( empty( $topics ) ) : ?>
				<p class="smartcontentai-muted"><?php echo esc_html__( 'No topics yet.', 'smartcontentai' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Topic', 'smartcontentai' ); ?></th>
							<th><?php echo esc_html__( 'Keyword', 'smartcontentai' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'smartcontentai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $topics as $index => $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['topic'] ); ?></td>
								<td><?php echo esc_html( $row['keyword'] ); ?></td>
								<td class="smartcontentai-table-actions">
									<div class="smartcontentai-inline-actions">
										<form method="post" action="<?php echo esc_url( $actions_url ); ?>">
											<input type="hidden" name="action" value="smartcontentai_enqueue_topic" />
											<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['topics'] ); ?>" />
											<input type="hidden" name="index" value="<?php echo esc_attr( (string) $index ); ?>" />
											<?php submit_button( __( 'Queue', 'smartcontentai' ), 'secondary', 'submit', false ); ?>
										</form>

										<form method="post" action="<?php echo esc_url( $actions_url ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this topic?', 'smartcontentai' ) ); ?>');">
											<input type="hidden" name="action" value="smartcontentai_delete_topic" />
											<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['topics'] ); ?>" />
											<input type="hidden" name="index" value="<?php echo esc_attr( (string) $index ); ?>" />
											<?php submit_button( __( 'Delete', 'smartcontentai' ), 'delete', 'submit', false ); ?>
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
