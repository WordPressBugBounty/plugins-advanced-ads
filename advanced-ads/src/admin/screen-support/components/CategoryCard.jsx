/**
 * External Dependencies
 */
import { CircleArrowRight } from 'lucide-react';

/**
 * Internal Dependencies
 */
import { useFetch } from '@admin/hooks/useFetch';
import { Card } from '@admin/components/Card';
import { CardSkeleton } from '@admin/components/CardSkeleton';

export function CategoryCard( {
	title,
	icon,
	endpoint,
	extraArgs,
	utms,
	viewAll = null,
} ) {
	const { data, isLoading } = useFetch( endpoint, extraArgs );

	return (
		<Card className="advads-card flex flex-col">
			<div>
				<Card.Header>
					<Card.HeaderIcon size="stroke-gray-700 size-6">
						{ icon }
					</Card.HeaderIcon>
					<Card.HeaderTitle>{ title }</Card.HeaderTitle>
				</Card.Header>
				{ isLoading ? (
					<CardSkeleton />
				) : (
					<ul>
						{ data.map( ( item ) => (
							<li
								key={ `${ item.ID }-${ item.title?.rendered }` }
							>
								<a
									href={ item.link + utms }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ item.title?.rendered }
								</a>
							</li>
						) ) }
					</ul>
				) }
			</div>
			{ viewAll && (
				<footer className="mt-auto">
					<a href={ viewAll.link } className="advads-view-all-link">
						{ viewAll.label }
						<CircleArrowRight />
					</a>
				</footer>
			) }
		</Card>
	);
}
