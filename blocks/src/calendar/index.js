import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const {
		category,
		tag,
		venue,
		organizer,
		showCancelled,
		showToday,
		weekStart,
		showNav,
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

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Calendar', 're-events' ) }>
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
					<ToggleControl
						label={ __( 'Include cancelled events', 're-events' ) }
						checked={ showCancelled }
						onChange={ ( value ) =>
							setAttributes( { showCancelled: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Mark today', 're-events' ) }
						checked={ showToday }
						onChange={ ( value ) =>
							setAttributes( { showToday: value } )
						}
					/>
					<SelectControl
						label={ __( 'Week starts on', 're-events' ) }
						value={ weekStart }
						options={ [
							{
								value: 'mon',
								label: __( 'Monday', 're-events' ),
							},
							{
								value: 'sun',
								label: __( 'Sunday', 're-events' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { weekStart: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show month navigation', 're-events' ) }
						checked={ showNav }
						onChange={ ( value ) =>
							setAttributes( { showNav: value } )
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
