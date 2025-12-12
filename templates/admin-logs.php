<?php
/**
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logs        = $data['logs'];
$stats       = $data['stats'];
$rate        = $data['rate'];
$generated   = $data['generated_posts'];

?>
<div class="wrap autoblogai-wrap">
	<h1><?php echo esc_html__( 'Logs & History', 'autoblogai' ); ?></h1>

	<div class="autoblogai-grid">
		<div class="autoblogai-card">
			<h2><?php echo esc_html__( 'API statistics', 'autoblogai' ); ?></h2>
			<ul>
				<li><strong><?php echo esc_html__( 'Total log entries:', 'autoblogai' ); ?></strong> <?php echo esc_html( (string) ( $stats->total_logs ?? 0 ) ); ?></li>
				<li><strong><?php echo esc_html__( 'Successful:', 'autoblogai' ); ?></strong> <?php echo esc_html( (string) ( $stats->successful ?? 0 ) ); ?></li>
				<li><strong><?php echo esc_html__( 'Failed:', 'autoblogai' ); ?></strong> <?php echo esc_html( (string) ( $stats->failed ?? 0 ) ); ?></li>
				<li><strong><?php echo esc_html__( 'Unique posts:', 'autoblogai' ); ?></strong> <?php echo esc_html( (string) ( $stats->unique_posts ?? 0 ) ); ?></li>
				<li><strong><?php echo esc_html__( 'Last log time:', 'autoblogai' ); ?></strong> <?php echo esc_html( (string) ( $stats->last_log_time ?? '-' ) ); ?></li>
			</ul>

			<h3><?php echo esc_html__( 'Rate limiter', 'autoblogai' ); ?></h3>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: used, 2: limit */
						__( '%1$d used in last minute (limit: %2$d).', 'autoblogai' ),
						(int) ( $rate['used_last_minute'] ?? 0 ),
						(int) ( $rate['limit'] ?? 0 )
					)
				);
				?>
			</p>
		</div>

		<div class="autoblogai-card">
			<h2><?php echo esc_html__( 'Generated posts', 'autoblogai' ); ?></h2>
			<?php if ( empty( $generated ) ) : ?>
				<p class="autoblogai-muted"><?php echo esc_html__( 'No generated posts found.', 'autoblogai' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $generated as $post ) : ?>
						<li>
							<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
							<span class="autoblogai-muted">(<?php echo esc_html( $post->post_date ); ?>)</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="autoblogai-card">
		<h2><?php echo esc_html__( 'Recent logs', 'autoblogai' ); ?></h2>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Date', 'autoblogai' ); ?></th>
					<th><?php echo esc_html__( 'Payload / Topic', 'autoblogai' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'autoblogai' ); ?></th>
					<th><?php echo esc_html__( 'Message', 'autoblogai' ); ?></th>
					<th><?php echo esc_html__( 'Post', 'autoblogai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $logs ) : foreach ( $logs as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td><?php echo esc_html( mb_strimwidth( (string) $row->request_payload, 0, 80, '...' ) ); ?></td>
						<td><?php echo esc_html( $row->status ); ?></td>
						<td><?php echo esc_html( mb_strimwidth( (string) $row->response_excerpt, 0, 80, '...' ) ); ?></td>
						<td>
							<?php if ( ! empty( $row->post_id ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $row->post_id ) ); ?>">#<?php echo esc_html( (string) $row->post_id ); ?></a>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; else : ?>
					<tr><td colspan="5"><?php echo esc_html__( 'No logs yet.', 'autoblogai' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
