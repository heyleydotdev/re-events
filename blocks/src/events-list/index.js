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
	const { limit, category, venue, upcoming, showVenue } = attributes;
	const categories = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords(
				'taxonomy',
				'reevents_event_category',
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
					<SelectControl
						label={ __( 'Category', 're-events' ) }
						value={ String( category ) }
						options={ [
							{
								value: '0',
								label: __( 'All categories', 're-events' ),
							},
							...categories.map( ( term ) => ( {
								value: String( term.id ),
								label: term.name,
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( {
								category: parseInt( value, 10 ) || 0,
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
						label={ __( 'Only upcoming events', 're-events' ) }
						checked={ upcoming }
						onChange={ ( value ) =>
							setAttributes( { upcoming: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show venue', 're-events' ) }
						checked={ showVenue }
						onChange={ ( value ) =>
							setAttributes( { showVenue: value } )
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
