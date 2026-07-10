/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { clsx } from '@admin/utils';
import { FeatureList } from './FeatureList';

/**
 * @param {Object}     props
 * @param {string}     props.title
 * @param {string}     props.subtitle
 * @param {string}     props.price
 * @param {string}     [props.priceSuffix]
 * @param {string}     props.description
 * @param {string}     props.ctaLabel
 * @param {string[]}   props.features
 * @param {boolean}    [props.popular]
 * @param {boolean}    [props.ctaDisabled]
 * @param {() => void} [props.onCtaClick]
 */
export function PricingTableItem( {
	title,
	subtitle,
	price,
	priceSuffix = __( '/ year', 'advanced-ads' ),
	description,
	ctaLabel,
	features,
	popular = false,
	ctaDisabled = false,
	onCtaClick,
} ) {
	return (
		<article className="relative flex h-full flex-col rounded-xl border border-gray-200 bg-white px-8 py-6 shadow-sm md:grid md:h-auto md:grid-rows-subgrid md:row-span-5">
			{ popular ? (
				<span className="absolute right-8 top-8 rounded-md bg-gray-900 px-2.5 py-1 text-xs font-semibold text-white">
					{ __( 'Popular', 'advanced-ads' ) }
				</span>
			) : null }

			<div className={ popular ? 'pr-20' : '' }>
				<h3 className="text-xl font-bold text-black m-0">{ title }</h3>
				<p className="mt-1 text-sm text-gray-500">{ subtitle }</p>
			</div>

			<div className="mt-6 flex flex-wrap items-baseline gap-1.5 md:mt-0">
				<span className="text-4xl font-bold tracking-tight text-black md:text-5xl">
					{ price }
				</span>
				<span className="text-sm text-gray-500">{ priceSuffix }</span>
			</div>

			<p className="mt-4 text-sm leading-relaxed text-gray-600 md:mt-0">
				{ description }
			</p>

			<button
				type="button"
				className={ clsx(
					'mt-8 w-full shrink-0 rounded-lg px-4 py-3 text-center text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 md:mt-0',
					ctaDisabled || ! onCtaClick
						? 'cursor-not-allowed bg-gray-200 text-gray-500'
						: 'bg-zinc-800 text-white hover:bg-zinc-900 focus-visible:outline-zinc-900'
				) }
				onClick={ onCtaClick }
				disabled={ ctaDisabled || ! onCtaClick }
			>
				{ ctaLabel }
			</button>

			<div className="mt-8 border-t border-gray-100 pt-8 md:mt-0">
				<FeatureList features={ features } />
			</div>
		</article>
	);
}
