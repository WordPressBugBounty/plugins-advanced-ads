/**
 * External Dependencies
 */
import { Check } from 'lucide-react';

/**
 * @param {Object}   props
 * @param {string[]} props.features
 * @param {string}   [props.className]
 */
export function FeatureList( { features, className = '' } ) {
	return (
		<ul className={ `flex flex-col gap-3 ${ className }`.trim() }>
			{ features.map( ( feature ) => (
				<li
					key={ feature }
					className="flex items-start gap-3 text-sm leading-snug text-gray-800"
				>
					<span
						className="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-gray-400 text-gray-800"
						aria-hidden
					>
						<Check className="size-3" strokeWidth={ 2.5 } />
					</span>
					<span>{ feature }</span>
				</li>
			) ) }
		</ul>
	);
}
