<?php
/**
 * Latest articles card.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

$items = $this->get_links( '/posts?category=1&_fields=title,link&orderby=date&order=desc&per_page=4', 'advads_support_tutorials_links' );
?>
<div class="advads-card flex flex-col">
	<div>
		<div class="header-icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
				<path d="M13 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V9M13 2L20 9M13 2L13 9H20" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>
		<h3><?php esc_html_e( 'Latest tutorials', 'advanced-ads' ); ?></h3>
		<?php if ( ! empty( $items ) ) : ?>
		<ul>
			<?php foreach ( $items as $item ) : ?>
			<li>
				<a href="<?php echo esc_url( $item['link'] ); ?>?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_latest_tutorials" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $item['title']['rendered'] ); ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php else : ?>
			<?php include 'error.php'; ?>
		<?php endif; ?>
	</div>

	<footer class="mt-auto">
		<a class="advads-view-all-link" href="https://wpadvancedads.com/category/tutorials/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_latest_tutorials_view_all" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'View all tutorials', 'advanced-ads' ); ?>
			<?php include 'arrow.php'; ?>
		</a>
	</footer>
</div>
