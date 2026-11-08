import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { id, showVenue, showOrganizers, showOnlineLinks, showContent } =
		attributes;
	const events = useSelect(
		( select ) =>
			select( 'core' ).getEntityRecords( 'postType', 'reevents_event', {
				per_page: 100,
			} ) || [],
		[]
	);

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
						label={ __( 'Show online links', 're-events' ) }
						checked={ showOnlineLinks }
						onChange={ ( value ) =>
							setAttributes( { showOnlineLinks: value } )
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
