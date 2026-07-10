import { clsx } from '@admin/utils';

export function Card( { className, children } ) {
	return (
		<div
			className={ clsx(
				'border border-border rounded p-6 mb-8 bg-white',
				className
			) }
		>
			{ children }
		</div>
	);
}

Card.Header = function Header( { className, children } ) {
	return <div className={ clsx( className ) }>{ children }</div>;
};

Card.HeaderTitle = function HeaderTitle( { className, children } ) {
	return (
		<h3 className={ clsx( 'text-base font-medium', className ) }>
			{ children }
		</h3>
	);
};

Card.HeaderIcon = function HeaderIcon( {
	className,
	size = 'size-6',
	children,
} ) {
	return (
		<div
			className={ clsx(
				'bg-gray-200 rounded p-3 inline-flex items-center justify-center',
				className
			) }
		>
			<div className={ clsx( size ) }>{ children }</div>
		</div>
	);
};
