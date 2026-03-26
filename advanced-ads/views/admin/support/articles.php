<?php
/**
 * Articles card.
 *
 * @package AdvancedAds
 * @author  Advanced Ads <info@wpadvancedads.com>
 */

$items = $this->get_links( '/bwl_kb?_fields=title,link&orderby=date&order=desc&per_page=4', 'advads_support_articles_links' );
?>
<div class="advads-card flex flex-col">
	<div>
		<div class="header-icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
				<path d="M22 19C22 19.5304 21.7893 20.0391 21.4142 20.4142C21.0391 20.7893 20.5304 21 20 21H4C3.46957 21 2.96086 20.7893 2.58579 20.4142C2.21071 20.0391 2 19.5304 2 19V5C2 4.46957 2.21071 3.96086 2.58579 3.58579C2.96086 3.21071 3.46957 3 4 3H9L11 6H20C20.5304 6 21.0391 6.21071 21.4142 6.58579C21.7893 6.96086 22 7.46957 22 8V19Z" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>
		<h3><?php esc_html_e( 'Articles', 'advanced-ads' ); ?></h3>
		<?php if ( ! empty( $items ) ) : ?>
		<ul>
			<?php foreach ( $items as $item ) : ?>
			<li>
				<a href="<?php echo esc_url( $item['link'] ); ?>?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_articles" target="_blank" rel="noopener noreferrer">
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
		<a class="advads-view-all-link" href="https://wpadvancedads.com/manual/?utm_source=advanced-ads&utm_medium=link&utm_campaign=plugin_support_articles_view_all" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'View all articles', 'advanced-ads' ); ?>
			<?php include 'arrow.php'; ?>
		</a>
	</footer>
</div>
