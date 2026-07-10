function SkeletonRow() {
	return (
		<div className="flex items-center gap-2.5 py-2 border-b border-gray-100 last:border-0">
			<div className="size-4 rounded-full bg-gray-100 animate-pulse shrink-0" />
			<div className="h-3 bg-gray-100 animate-pulse rounded w-full" />
		</div>
	);
}

export function CardSkeleton( { rows = 3 } ) {
	return (
		<div>
			<div className="flex items-center gap-2.5 mb-4">
				<div className="size-6 rounded bg-gray-100 animate-pulse shrink-0" />
				<div className="h-3.5 bg-gray-100 animate-pulse rounded w-24" />
			</div>
			{ Array.from( { length: rows } ).map( ( _, i ) => (
				<SkeletonRow key={ i } />
			) ) }
		</div>
	);
}
