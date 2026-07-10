/**
 * External Dependencies
 */
import { Plus, Minus } from 'lucide-react';
import { useId, useState } from '@wordpress/element';

/**
 * Internal Dependencies
 */
import { clsx } from '@admin/utils';

export function Accordion( { className, children } ) {
	return (
		<div
			className={ clsx(
				'bg-white rounded-md divide-y divide-border',
				className
			) }
		>
			{ children }
		</div>
	);
}

Accordion.Item = function Item( { title, content, defaultOpen = false } ) {
	const id = useId();
	const [ open, setOpen ] = useState( defaultOpen );

	return (
		<div className="relative transition-all duration-500">
			<input
				type="checkbox"
				className="absolute opacity-0 z-[-1]"
				id={ id }
				checked={ open }
				onChange={ () => setOpen( ! open ) }
			/>
			<label
				htmlFor={ id }
				className="flex font-medium text-sm justify-between py-4 px-1 cursor-pointer"
			>
				{ title }
				{ open ? (
					<Minus className="size-4" />
				) : (
					<Plus className="size-4" />
				) }
			</label>
			{ open && (
				<div
					className={ clsx(
						'px-1 max-h-0 overflow-hidden [&>:*]:p-0 [&>:*]:m-0 space-y-4',
						open ? 'max-h-[600px] pb-4' : ''
					) }
				>
					{ content }
				</div>
			) }
		</div>
	);
};
