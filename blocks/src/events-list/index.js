import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const {
		limit,
		search,
		category,
		tag,
		venue,
		organizer,
		from,
		to,
		order,
		upcoming,
		useQuery,
		dateFormat,
		timeFormat,
		showImage,
		imageSize,
		showPrice,
		showExcerpt,
		excerptLength,
		showVenue,
		showOrganizers,
		showCategories,
		showTags,
		showEnd,
		showSeatsLeft,
		showCancelled,
		titleTag,
		link,
		paginate,
		paginationKey,
	} = attributes;
	const categories = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords(
				'taxonomy',
				'reevents_event_category',
				{ per_page: 100 }
			) || [],
		[]
	);
	const tags = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords(
				'taxonomy',
				'reevents_event_tag',
				{ per_page: 100 }
			) || [],
		[]
	);
	const venues = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'postType', 'reevents_venue', {
				per_page: 100,
			} ) || [],
		[]
	);
	const organizers = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords(
				'postType',
				'reevents_organizer',
				{ per_page: 100 }
			) || [],
		[]
	);
	const imageSizeOptions = useSelect( ( select ) => {
		const store = select( 'core/block-editor' );
		const sizes =
			store && store.getSettings
				? store.getSettings()?.imageSizes || []
				: [];

		return sizes.map( ( size ) => ( {
			value: size.slug,
			label: size.name,
		} ) );
	}, [] );
	const availableImageSizes = imageSizeOptions.length
		? imageSizeOptions
		: [ 'thumbnail', 'medium', 'medium_large', 'large', 'full' ].map(
				( size ) => ( { value: size, label: size } )
		  );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Events', 're-events' ) }>
					<TextControl
						label={ __( 'Number of events', 're-events' ) }
						type="number"
						min={ 1 }
						value={ String( limit ) }
						onChange={ ( value ) =>
							setAttributes( {
								limit: Math.max(
									1,
									parseInt( value, 10 ) || 1
								),
							} )
						}
					/>
					<TextControl
						label={ __( 'Search', 're-events' ) }
						value={ search }
						onChange={ ( value ) =>
							setAttributes( { search: value } )
						}
					/>
					<SelectControl
						label={ __( 'Category', 're-events' ) }
						value={ String( category ) }
						options={ [
							{
								value: '0',
								label: __( 'All categories', 're-events' ),
							},
							{
								value: 'current',
								label: __( 'Current category', 're-events' ),
							},
							...categories.map( ( term ) => ( {
								value: String( term.id ),
								label: term.name,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( { category: value } )
						}
					/>
					<SelectControl
						label={ __( 'Tag', 're-events' ) }
						value={ String( tag ) }
						options={ [
							{
								value: '',
								label: __( 'All tags', 're-events' ),
							},
							...tags.map( ( term ) => ( {
								value: String( term.id ),
								label: term.name,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( { tag: value } )
						}
					/>
					<SelectControl
						label={ __( 'Venue', 're-events' ) }
						value={ String( venue ) }
						options={ [
							{
								value: '0',
								label: __( 'All venues', 're-events' ),
							},
							...venues.map( ( item ) => ( {
								value: String( item.id ),
								label: item.title.rendered,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( {
								venue: parseInt( value, 10 ) || 0,
							} )
						}
					/>
					<SelectControl
						label={ __( 'Organizer', 're-events' ) }
						value={ String( organizer ) }
						options={ [
							{
								value: '0',
								label: __( 'All organizers', 're-events' ),
							},
							...organizers.map( ( item ) => ( {
								value: String( item.id ),
								label: item.title.rendered,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( {
								organizer: parseInt( value, 10 ) || 0,
							} )
						}
					/>
					<TextControl
						label={ __( 'From date', 're-events' ) }
						type="date"
						value={ from }
						onChange={ ( value ) =>
							setAttributes( { from: value } )
						}
					/>
					<TextControl
						label={ __( 'To date', 're-events' ) }
						type="date"
						value={ to }
						onChange={ ( value ) => setAttributes( { to: value } ) }
					/>
					<SelectControl
						label={ __( 'Order', 're-events' ) }
						value={ order }
						options={ [
							{
								value: 'asc',
								label: __( 'Soonest first', 're-events' ),
							},
							{
								value: 'desc',
								label: __( 'Latest first', 're-events' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { order: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Only upcoming events', 're-events' ) }
						checked={ upcoming }
						onChange={ ( value ) =>
							setAttributes( { upcoming: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Use search query', 're-events' ) }
						help={ __(
							'Use the values from a search form on the same page.',
							're-events'
						) }
						checked={ useQuery }
						onChange={ ( value ) =>
							setAttributes( { useQuery: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show pagination', 're-events' ) }
						help={ __(
							'Turn off for sidebar and teaser lists.',
							're-events'
						) }
						checked={ paginate }
						onChange={ ( value ) =>
							setAttributes( { paginate: value } )
						}
					/>
					<TextControl
						label={ __( 'Pagination key', 're-events' ) }
						help={ __(
							'Only needed when several paginated lists share a page.',
							're-events'
						) }
						value={ paginationKey }
						onChange={ ( value ) =>
							setAttributes( { paginationKey: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Card', 're-events' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Date format', 're-events' ) }
						value={ dateFormat }
						placeholder="F j, Y"
						help={ __(
							'Leave empty to use the site format.',
							're-events'
						) }
						onChange={ ( value ) =>
							setAttributes( { dateFormat: value } )
						}
					/>
					<TextControl
						label={ __( 'Time format', 're-events' ) }
						value={ timeFormat }
						placeholder="g:i a"
						help={ __(
							'Leave empty to use the site format.',
							're-events'
						) }
						onChange={ ( value ) =>
							setAttributes( { timeFormat: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show image', 're-events' ) }
						checked={ showImage }
						onChange={ ( value ) =>
							setAttributes( { showImage: value } )
						}
					/>
					{ showImage && (
						<SelectControl
							label={ __( 'Image size', 're-events' ) }
							value={ imageSize }
							options={ availableImageSizes }
							onChange={ ( value ) =>
								setAttributes( { imageSize: value } )
							}
							help={ __(
								'Registered WordPress image size used on event cards.',
								're-events'
							) }
						/>
					) }
					<ToggleControl
						label={ __( 'Show price', 're-events' ) }
						checked={ showPrice }
						onChange={ ( value ) =>
							setAttributes( { showPrice: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show excerpt', 're-events' ) }
						checked={ showExcerpt }
						onChange={ ( value ) =>
							setAttributes( { showExcerpt: value } )
						}
					/>
					{ showExcerpt && (
						<TextControl
							label={ __( 'Excerpt length', 're-events' ) }
							type="number"
							min={ 0 }
							value={ String( excerptLength ) }
							help={ __(
								'Word count. 0 uses the theme length.',
								're-events'
							) }
							onChange={ ( value ) =>
								setAttributes( {
									excerptLength: Math.max(
										0,
										parseInt( value, 10 ) || 0
									),
								} )
							}
						/>
					) }
					<ToggleControl
						label={ __( 'Show venue', 're-events' ) }
						checked={ showVenue }
						onChange={ ( value ) =>
							setAttributes( { showVenue: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show organizers', 're-events' ) }
						checked={ showOrganizers }
						onChange={ ( value ) =>
							setAttributes( { showOrganizers: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show categories', 're-events' ) }
						checked={ showCategories }
						onChange={ ( value ) =>
							setAttributes( { showCategories: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show tags', 're-events' ) }
						checked={ showTags }
						onChange={ ( value ) =>
							setAttributes( { showTags: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show end date', 're-events' ) }
						checked={ showEnd }
						onChange={ ( value ) =>
							setAttributes( { showEnd: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show seats left', 're-events' ) }
						checked={ showSeatsLeft }
						onChange={ ( value ) =>
							setAttributes( { showSeatsLeft: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Include cancelled events', 're-events' ) }
						help={ __(
							'Cancelled events are hidden unless enabled.',
							're-events'
						) }
						checked={ showCancelled }
						onChange={ ( value ) =>
							setAttributes( { showCancelled: value } )
						}
					/>
					<SelectControl
						label={ __( 'Title markup', 're-events' ) }
						value={ titleTag }
						options={ [
							{
								value: 'a',
								label: __( 'Plain / inline', 're-events' ),
							},
							{ value: 'h2', label: 'H2' },
							{ value: 'h3', label: 'H3' },
							{ value: 'div', label: 'DIV' },
						] }
						onChange={ ( value ) =>
							setAttributes( { titleTag: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Link title and image', 're-events' ) }
						checked={ link }
						onChange={ ( value ) =>
							setAttributes( { link: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
