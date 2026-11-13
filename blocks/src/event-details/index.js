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
		id,
		showImage,
		imageSize,
		showTitle,
		titleTag,
		showStatus,
		showVenue,
		showVenueAddress,
		showVenueMapLink,
		showVenueMap,
		venueMapHeight,
		showOrganizers,
		showPrice,
		showSeatsLeft,
		showCategories,
		showTags,
		showExcerpt,
		showEnd,
		dateFormat,
		timeFormat,
		showOnlineLinks,
		showCalendarLinks,
		showContent,
	} = attributes;
	const events = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'postType', 'reevents_event', {
				per_page: 100,
			} ) || [],
		[]
	);
	const imageSizes = useSelect( ( select ) => {
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
	const imageSizeOptions = imageSizes.length
		? imageSizes
		: [ 'thumbnail', 'medium', 'large', 'full' ].map( ( size ) => ( {
				value: size,
				label: size,
		  } ) );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Event', 're-events' ) }>
					<SelectControl
						label={ __( 'Event', 're-events' ) }
						value={ String( id ) }
						options={ [
							{
								value: '0',
								label: __( 'Select an event', 're-events' ),
							},
							...events.map( ( event ) => ( {
								value: String( event.id ),
								label: event.title.rendered,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( { id: parseInt( value, 10 ) || 0 } )
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
							options={ imageSizeOptions }
							onChange={ ( value ) =>
								setAttributes( { imageSize: value } )
							}
						/>
					) }
					<ToggleControl
						label={ __( 'Show title', 're-events' ) }
						checked={ showTitle }
						onChange={ ( value ) =>
							setAttributes( { showTitle: value } )
						}
					/>
					{ showTitle && (
						<SelectControl
							label={ __( 'Title markup', 're-events' ) }
							value={ titleTag }
							options={ [
								{ value: 'h1', label: 'H1' },
								{ value: 'h2', label: 'H2' },
								{ value: 'h3', label: 'H3' },
								{ value: 'div', label: 'DIV' },
							] }
							onChange={ ( value ) =>
								setAttributes( { titleTag: value } )
							}
						/>
					) }
					<ToggleControl
						label={ __( 'Show status', 're-events' ) }
						checked={ showStatus }
						onChange={ ( value ) =>
							setAttributes( { showStatus: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show end date', 're-events' ) }
						checked={ showEnd }
						onChange={ ( value ) =>
							setAttributes( { showEnd: value } )
						}
					/>
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
						label={ __( 'Show venue', 're-events' ) }
						checked={ showVenue }
						onChange={ ( value ) =>
							setAttributes( { showVenue: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show venue address', 're-events' ) }
						checked={ showVenueAddress }
						onChange={ ( value ) =>
							setAttributes( { showVenueAddress: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show venue map link', 're-events' ) }
						checked={ showVenueMapLink }
						onChange={ ( value ) =>
							setAttributes( { showVenueMapLink: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Embed venue map', 're-events' ) }
						help={ __(
							'Requires venue coordinates.',
							're-events'
						) }
						checked={ showVenueMap }
						onChange={ ( value ) =>
							setAttributes( { showVenueMap: value } )
						}
					/>
					{ showVenueMap && (
						<TextControl
							label={ __( 'Map height (px)', 're-events' ) }
							type="number"
							min={ 200 }
							value={ String( venueMapHeight ) }
							onChange={ ( value ) =>
								setAttributes( {
									venueMapHeight:
										parseInt( value, 10 ) || 320,
								} )
							}
						/>
					) }
					<ToggleControl
						label={ __( 'Show organizers', 're-events' ) }
						checked={ showOrganizers }
						onChange={ ( value ) =>
							setAttributes( { showOrganizers: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show price', 're-events' ) }
						checked={ showPrice }
						onChange={ ( value ) =>
							setAttributes( { showPrice: value } )
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
						label={ __( 'Show excerpt', 're-events' ) }
						checked={ showExcerpt }
						onChange={ ( value ) =>
							setAttributes( { showExcerpt: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show online links', 're-events' ) }
						checked={ showOnlineLinks }
						onChange={ ( value ) =>
							setAttributes( { showOnlineLinks: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show calendar links', 're-events' ) }
						checked={ showCalendarLinks }
						onChange={ ( value ) =>
							setAttributes( { showCalendarLinks: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show description', 're-events' ) }
						checked={ showContent }
						onChange={ ( value ) =>
							setAttributes( { showContent: value } )
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
