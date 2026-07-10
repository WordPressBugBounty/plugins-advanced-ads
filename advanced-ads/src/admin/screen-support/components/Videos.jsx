/**
 * External Dependencies
 */
import { __ } from '@wordpress/i18n';
import { CircleArrowRight, PlayCircle } from 'lucide-react';

/**
 * Internal Dependencies
 */
import { Card } from '@admin/components/Card';
const VIDEO_IDS = [
	'RmH8YiswfIk',
	'EL29HYcCcxY',
	'jWZe020bdJc',
	'Q7QQGvCJh2I',
	'wUBa7Ht52fs',
	'Ywtj0Ui_TaI',
];

export function Videos() {
	return (
		<Card className="advads-card">
			<Card.HeaderIcon>
				<PlayCircle />
			</Card.HeaderIcon>
			<Card.HeaderTitle>
				{ __( 'Video tutorials', 'advanced-ads' ) }
			</Card.HeaderTitle>

			<div className="grid sm:grid-cols-2 xl:grid-cols-3 gap-8">
				{ VIDEO_IDS.map( ( videoId ) => (
					<div key={ videoId }>
						<iframe
							title={ `YouTube video ${ videoId }` }
							src={ `https://www.youtube.com/embed/${ videoId }` }
							allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
							referrerPolicy="strict-origin-when-cross-origin"
							allowFullScreen
							className="aspect-video w-full"
						></iframe>
					</div>
				) ) }
			</div>

			<footer className="mt-6">
				<a
					className="advads-view-all-link"
					href="https://www.youtube.com/channel/UCBBcWLiklJ-mbq9LB6TbkVQ"
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __(
						'View all videos tutorials on YouTube',
						'advanced-ads'
					) }
					<CircleArrowRight />
				</a>
			</footer>
		</Card>
	);
}
