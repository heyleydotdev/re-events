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
		height,
		venue,
		upcoming,
		search,
		center,
		zoom,
		fit,
		link,
		provider,
		showAddress,
		showNextEvent,
	} = attributes;
	const venues = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'postType', 'reevents_venue', {
				per_page: 100,
			} ) || [],
		[]
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Map', 're-events' ) }>
					<TextControl
						label={ __( 'Maximum venues', 're-events' ) }
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
						label={ __( 'Height (px)', 're-events' ) }
						type="number"
						min={ 200 }
						value={ String( height ) }
						onChange={ ( value ) =>
							setAttributes( {
								height: Math.max(
									200,
									parseInt( value, 10 ) || 200
								),
							} )
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
					<ToggleControl
						label={ __(
							'Only venues with upcoming events',
							're-events'
						) }
						checked={ upcoming }
						onChange={ ( value ) =>
							setAttributes( { upcoming: value } )
						}
					/>
					<TextControl
						label={ __( 'Search', 're-events' ) }
						value={ search }
						onChange={ ( value ) =>
							setAttributes( { search: value } )
						}
					/>
					<TextControl
						label={ __( 'Center', 're-events' ) }
						help={ __( 'Latitude,longitude.', 're-events' ) }
						value={ center }
						onChange={ ( value ) =>
							setAttributes( { center: value } )
						}
					/>
					<TextControl
						label={ __( 'Zoom', 're-events' ) }
						type="number"
						min={ 0 }
						max={ 19 }
						value={ String( zoom ) }
						onChange={ ( value ) =>
							setAttributes( {
								zoom: Math.max(
									0,
									Math.min( 19, parseInt( value, 10 ) || 0 )
								),
							} )
						}
					/>
					<ToggleControl
						label={ __( 'Fit all markers', 're-events' ) }
						checked={ fit }
						onChange={ ( value ) =>
							setAttributes( { fit: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Link markers to the venue', 're-events' ) }
						checked={ link }
						onChange={ ( value ) =>
							setAttributes( { link: value } )
						}
					/>
					<SelectControl
						label={ __( 'Provider', 're-events' ) }
						value={ provider }
						options={ [
							{
								value: '',
								label: __( 'Site setting', 're-events' ),
							},
							{ value: 'osm', label: 'OpenStreetMap' },
							{ value: 'google', label: 'Google Maps' },
						] }
						onChange={ ( value ) =>
							setAttributes( { provider: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show address', 're-events' ) }
						checked={ showAddress }
						onChange={ ( value ) =>
							setAttributes( { showAddress: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show next event', 're-events' ) }
						checked={ showNextEvent }
						onChange={ ( value ) =>
							setAttributes( { showNextEvent: value } )
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
