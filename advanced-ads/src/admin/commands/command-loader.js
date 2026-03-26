import { addQueryArgs } from '@wordpress/url';
import { useCommandLoader } from '@wordpress/commands';
import { useSelect } from '@wordpress/data';
import { Dashicon } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
// eslint-disable-next-line import/no-extraneous-dependencies
import { useMemo, createElement } from '@wordpress/element';

const taxonomies = [ 'advanced_ads_groups' ];
const postTypes = [ 'advanced_ads', 'advanced_ads_plcmnt' ];
const labelsHash = {
	advanced_ads: 'Ads',
	advanced_ads_plcmnt: 'Placement',
	advanced_ads_groups: 'Group',
};
const iconsHash = {
	advanced_ads: 'format-video',
	advanced_ads_plcmnt: 'welcome-widgets-menus',
	advanced_ads_groups: 'category',
};

const getEditUrl = ( type, item ) => {
	if ( 'advanced_ads_plcmnt' === type ) {
		return addQueryArgs( `edit.php#modal-placement-edit-${ item.id }`, {
			post_status: 'all',
			s: item.title?.rendered,
			post_type: type,
		} );
	}

	return addQueryArgs( 'post.php', {
		action: 'edit',
		post: item.id,
	} );
};

function useAdvancedAdsSearchCommandLoader( { search } ) {
	const { records, isLoading } = useSelect(
		( select ) => {
			const { getEntityRecords, hasFinishedResolution } =
				select( coreStore );
			const query = {
				search,
				per_page: 10,
			};

			const postResults = postTypes.flatMap( ( type ) => {
				const r = getEntityRecords( 'postType', type, query );
				return r
					? r.map( ( item ) => ( {
							name: `edit-${ type }-${ item.id }`,
							label: `${ labelsHash[ type ] }: ${ item.title?.rendered }`,
							category: 'edit',
							icon: createElement( Dashicon, {
								icon: iconsHash[ type ],
							} ),
							editUrl: getEditUrl( type, item ),
					  } ) )
					: [];
			} );

			const taxResults = taxonomies.flatMap( ( type ) => {
				const r = getEntityRecords( 'taxonomy', type, query );
				return r
					? r.map( ( item ) => ( {
							name: `edit-${ type }-${ item.id }`,
							label: `${ labelsHash[ type ] }: ${ item.name }`,
							category: 'edit',
							icon: createElement( Dashicon, {
								icon: iconsHash[ type ],
							} ),
							editUrl: addQueryArgs(
								`admin.php#modal-group-edit-${ item.id }`,
								{
									page: 'advanced-ads-groups',
									s: item.name,
								}
							),
					  } ) )
					: [];
			} );

			const finishedPosts = postTypes.every( ( type ) =>
				hasFinishedResolution( 'getEntityRecords', [
					'postType',
					type,
					query,
				] )
			);

			const finishedTax = taxonomies.every( ( type ) =>
				hasFinishedResolution( 'getEntityRecords', [
					'taxonomy',
					type,
					query,
				] )
			);

			return {
				records: [ ...postResults, ...taxResults ],
				isLoading: ! finishedPosts || ! finishedTax,
			};
		},
		[ search ]
	);

	// Create the commands.
	const commands = useMemo( () => {
		return ( records ?? [] ).slice( 0, 10 ).map( ( record ) => {
			return {
				...record,
				callback: ( { close } ) => {
					document.location = record.editUrl;
					close();
				},
			};
		} );
	}, [ records ] );

	return {
		commands,
		isLoading,
	};
}

function AdvancedAdsSearch() {
	useCommandLoader( {
		name: 'advanced-ads/commands/search',
		hook: useAdvancedAdsSearchCommandLoader,
	} );

	return null;
}

export function commandLoader() {
	const mountPoint = document.createElement( 'div' );
	mountPoint.id = 'advanced-ads-commands-root';
	document.body.appendChild( mountPoint );
	const root = wp.element.createRoot( mountPoint );
	root.render( createElement( AdvancedAdsSearch ) );
}
