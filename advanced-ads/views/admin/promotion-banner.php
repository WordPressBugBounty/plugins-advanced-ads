<?php
/**
 * Dated promotional banner under the admin toolbar.
 *
 * Height matches the design asset (1024×173).
 *
 * @package AdvancedAds
 *
 * @var string $cta_url CTA URL with UTM params.
 */

defined( 'ABSPATH' ) || exit;
?>
<?php // .advads-page scopes Tailwind utilities (required on the WP Dashboard). ?>
<div class="advads-page">
	<div id="advads-promotion-banner" class="mt-5 mr-5 mb-5 ml-0 flex h-[173px] items-stretch bg-[#0e75a4] text-white">
		<div class="flex min-w-0 grow flex-col justify-center gap-2.5 py-4 pr-6 pl-6">
			<p class="m-0 text-[28px] leading-none font-bold tracking-wide text-white uppercase">
				<span><?php echo esc_html__( 'November 12. Live.', 'advanced-ads' ); ?></span>
				<span class="bg-clip-text! text-transparent! bg-gradient-to-r from-[#F2DF6A] to-[#E79864]"><?php echo esc_html__( 'Exclusive.', 'advanced-ads' ); ?></span>
			</p>
			<p class="m-0 text-lg leading-snug font-normal text-white!">
				<?php echo esc_html__( 'Join the Black Friday Early Bird reveal and a Q&A with our team.', 'advanced-ads' ); ?>
			</p>
		</div>

		<div class="flex h-full shrink-0 flex-col items-end justify-between">
			<div class="inline-flex items-center gap-2.5 bg-white px-4 py-2.5">
				<svg class="size-6 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 351.7 352" aria-hidden="true" focusable="false">
					<path d="M252.2 149.6v125.1h-174.9v-174.9H202.4c-5.2-11.8-8-24.7-8-38.5s3-26.7 8-38.5h-37.7H0v267.9l8.8 8.8 -8.8-8.8C0 324.5 27.5 352 61.3 352l0 0h103.4 164.5V149.3c-11.8 5.2-25 8.3-38.8 8.3C276.9 157.6 264 154.6 252.2 149.6z" fill="#1C1B3A"/>
					<circle cx="290.4" cy="61.3" r="61.3" fill="#0E75A4"/>
				</svg>
				<span class="text-[15px] leading-none font-semibold whitespace-nowrap text-[#1c1b3a]!"><?php echo esc_html__( 'Advanced Ads', 'advanced-ads' ); ?></span>
			</div>

			<div class="pr-3.5 pb-3.5">
				<a
					href="<?php echo esc_url( $cta_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					class="inline-flex cursor-pointer items-center justify-center bg-[#CC3101]! px-6 py-2.5 text-[15px] leading-none font-semibold whitespace-nowrap text-white! no-underline! hover:bg-[#9a3412]! hover:text-white! focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#0e75a4]"
				>
					<?php echo esc_html__( 'Count me in', 'advanced-ads' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
