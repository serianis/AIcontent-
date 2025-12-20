<?php
/**
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status      = $data['status'];
$schedules   = $data['schedules'];
$nonces      = $data['nonces'];
$queue       = $data['queue'];
$actions_url = admin_url( 'admin-post.php' );

$enabled     = (bool) get_option( 'smartcontentai_scheduler_enabled', false );
$frequency   = get_option( 'smartcontentai_schedule_frequency', 'daily' );

?>
<div class="wrap smartcontentai-wrap">
	<h1><?php echo esc_html__( 'Scheduler', 'smartcontentai' ); ?></h1>

	<div class="smartcontentai-card">
		<h2><?php echo esc_html__( 'Status', 'smartcontentai' ); ?></h2>
		<ul>
			<li><strong><?php echo esc_html__( 'Scheduled:', 'smartcontentai' ); ?></strong> <?php echo esc_html( $status['is_scheduled'] ? __( 'Yes', 'smartcontentai' ) : __( 'No', 'smartcontentai' ) ); ?></li>
			<li><strong><?php echo esc_html__( 'Next run:', 'smartcontentai' ); ?></strong> <?php echo esc_html( $status['next_run'] ?: '-' ); ?></li>
			<li><strong><?php echo esc_html__( 'Queue:', 'smartcontentai' ); ?></strong> <?php echo esc_html( (string) $status['queue_count'] ); ?></li>
			<li><strong><?php echo esc_html__( 'Max posts/day:', 'smartcontentai' ); ?></strong> <?php echo esc_html( (string) $status['daily_cap'] ); ?></li>
			<li><strong><?php echo esc_html__( 'Posts today:', 'smartcontentai' ); ?></strong> <?php echo esc_html( (string) $status['posts_today'] ); ?></li>
		</ul>
	</div>

	<div class="smartcontentai-card">
		<h2><?php echo esc_html__( 'Controls', 'smartcontentai' ); ?></h2>
		<form method="post" action="<?php echo esc_url( $actions_url ); ?>">
			<input type="hidden" name="action" value="smartcontentai_save_scheduler" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['scheduler'] ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Enable scheduler', 'smartcontentai' ); ?></th>
					<td>
						<label><input type="checkbox" name="enabled" value="1" <?php checked( $enabled ); ?> /> <?php echo esc_html__( 'Run wp_cron automation', 'smartcontentai' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smartcontentai_schedule_frequency"><?php echo esc_html__( 'Frequency', 'smartcontentai' ); ?></label></th>
					<td>
						<select id="smartcontentai_schedule_frequency" name="frequency">
							<?php foreach ( $schedules as $key => $info ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $frequency, $key ); ?>><?php echo esc_html( $info['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smartcontentai_daily_cap"><?php echo esc_html__( 'Max posts per day', 'smartcontentai' ); ?></label></th>
					<td>
						<input id="smartcontentai_daily_cap" type="number" min="0" max="50" name="daily_cap" value="<?php echo esc_attr( get_option( 'smartcontentai_max_posts_per_day', 10 ) ); ?>" />
						<p class="description"><?php echo esc_html__( 'Default is 10/day.', 'smartcontentai' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save scheduler', 'smartcontentai' ) ); ?>
		</form>
	</div>

	<div class="smartcontentai-card">
		<h2><?php echo esc_html__( 'Queue', 'smartcontentai' ); ?></h2>

		<?php if ( empty( $queue ) ) : ?>
			<p class="smartcontentai-muted"><?php echo esc_html__( 'Queue is empty.', 'smartcontentai' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Topic', 'smartcontentai' ); ?></th>
						<th><?php echo esc_html__( 'Keyword', 'smartcontentai' ); ?></th>
						<th><?php echo esc_html__( 'Queued at', 'smartcontentai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $queue as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item['topic'] ?? '' ); ?></td>
							<td><?php echo esc_html( $item['keyword'] ?? '' ); ?></td>
							<td><?php echo esc_html( $item['queued_at'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( $actions_url ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Clear the whole queue?', 'smartcontentai' ) ); ?>');">
				<input type="hidden" name="action" value="smartcontentai_clear_queue" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonces['scheduler'] ); ?>" />
				<?php submit_button( __( 'Clear queue', 'smartcontentai' ), 'delete' ); ?>
			</form>
		<?php endif; ?>
	</div>
</div>
