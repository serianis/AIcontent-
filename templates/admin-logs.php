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
<div class="wrap smartcontentai-wrap">
	<h1><?php echo esc_html__( 'Logs & History', 'smartcontentai' ); ?></h1>

	<div class="smartcontentai-grid">
		<div class="smartcontentai-card">
			<h2><?php echo esc_html__( 'API statistics', 'smartcontentai' ); ?></h2>
			<ul>
				<li><strong><?php echo esc_html__( 'Total log entries:', 'smartcontentai' ); ?></strong> <?php echo esc_html( (string) ( $stats->total_logs ?? 0 ) ); ?></li>
				<li><strong><?php echo esc_html__( 'Successful:', 'smartcontentai' ); ?></strong> <?php echo esc_html( (string) ( $stats->successful ?? 0 ) ); ?></li>
				<li><strong><?php echo esc_html__( 'Failed:', 'smartcontentai' ); ?></strong> <?php echo esc_html( (string) ( $stats->failed ?? 0 ) ); ?></li>
				<li><strong><?php echo esc_html__( 'Unique posts:', 'smartcontentai' ); ?></strong> <?php echo esc_html( (string) ( $stats->unique_posts ?? 0 ) ); ?></li>
				<li><strong><?php echo esc_html__( 'Last log time:', 'smartcontentai' ); ?></strong> <?php echo esc_html( (string) ( $stats->last_log_time ?? '-' ) ); ?></li>
			</ul>

			<h3><?php echo esc_html__( 'Rate limiter', 'smartcontentai' ); ?></h3>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: used, 2: limit */
						__( '%1$d used in last minute (limit: %2$d).', 'smartcontentai' ),
						(int) ( $rate['used_last_minute'] ?? 0 ),
						(int) ( $rate['limit'] ?? 0 )
					)
				);
				?>
			</p>
		</div>

		<div class="smartcontentai-card">
			<h2><?php echo esc_html__( 'Generated posts', 'smartcontentai' ); ?></h2>
			<?php if ( empty( $generated ) ) : ?>
				<p class="smartcontentai-muted"><?php echo esc_html__( 'No generated posts found.', 'smartcontentai' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $generated as $post ) : ?>
						<li>
							<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
							<span class="smartcontentai-muted">(<?php echo esc_html( $post->post_date ); ?>)</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="smartcontentai-card">
		<h2><?php echo esc_html__( 'Recent logs', 'smartcontentai' ); ?></h2>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Date', 'smartcontentai' ); ?></th>
					<th><?php echo esc_html__( 'Payload / Topic', 'smartcontentai' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'smartcontentai' ); ?></th>
					<th><?php echo esc_html__( 'Message', 'smartcontentai' ); ?></th>
					<th><?php echo esc_html__( 'Post', 'smartcontentai' ); ?></th>
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
					<tr><td colspan="5"><?php echo esc_html__( 'No logs yet.', 'smartcontentai' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
