import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

const sources = {
	event: __( 'Event', 're-events' ),
	venue: __( 'Venue', 're-events' ),
	organizer: __( 'Organizer', 're-events' ),
};

const idLabels = {
	event: __( 'Event ID', 're-events' ),
	venue: __( 'Venue ID', 're-events' ),
	organizer: __( 'Organizer ID', 're-events' ),
};

function fieldOptions( source ) {
	const fields = ( window.reeventsFields || {} )[ source ] || {};

	return Object.entries( fields ).map( ( [ value, label ] ) => ( {
		value,
		label,
	} ) );
}

function Edit( { attributes, setAttributes } ) {
	const { source, field, id, label, link, separator, emptyLabel, tag, size } =
		attributes;
	const options = fieldOptions( source );

	if ( field && ! options.some( ( option ) => option.value === field ) ) {
		options.unshift( { value: field, label: field } );
	}

	if ( ! options.length ) {
		options.push( { value: 'title', label: __( 'Title', 're-events' ) } );
	}

	const isImage = 'image' === field || 'image_url' === field;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Value', 're-events' ) }>
					<SelectControl
						label={ __( 'Source', 're-events' ) }
						value={ source }
						options={ Object.entries( sources ).map(
							( [ value, text ] ) => ( { value, label: text } )
						) }
						onChange={ ( value ) =>
							setAttributes( {
								source: value,
								field: 'title',
								id: 0,
							} )
						}
					/>
					<SelectControl
						label={ __( 'Field', 're-events' ) }
						value={ field }
						options={ options }
						onChange={ ( value ) =>
							setAttributes( { field: value } )
						}
					/>
					<TextControl
						label={ idLabels[ source ] }
						type="number"
						min={ 0 }
						value={ String( id ) }
						help={ __(
							'Leave 0 to use the current page.',
							're-events'
						) }
						onChange={ ( value ) =>
							setAttributes( {
								id: Math.max( 0, parseInt( value, 10 ) || 0 ),
							} )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'Output', 're-events' ) }>
					<TextControl
						label={ __( 'Label', 're-events' ) }
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Link the value', 're-events' ) }
						checked={ link }
						onChange={ ( value ) =>
							setAttributes( { link: value } )
						}
					/>
					<SelectControl
						label={ __( 'Wrapper tag', 're-events' ) }
						value={ tag }
						options={ [
							{ value: 'span', label: __( 'Span', 're-events' ) },
							{ value: 'div', label: __( 'Div', 're-events' ) },
							{
								value: 'p',
								label: __( 'Paragraph', 're-events' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { tag: value } )
						}
					/>
					<TextControl
						label={ __( 'Separator', 're-events' ) }
						value={ separator }
						help={ __( 'Used between list values.', 're-events' ) }
						onChange={ ( value ) =>
							setAttributes( { separator: value } )
						}
					/>
					<TextControl
						label={ __( 'Empty text', 're-events' ) }
						value={ emptyLabel }
						onChange={ ( value ) =>
							setAttributes( { emptyLabel: value } )
						}
					/>
					{ isImage && (
						<TextControl
							label={ __( 'Image size', 're-events' ) }
							value={ size }
							onChange={ ( value ) =>
								setAttributes( { size: value } )
							}
						/>
					) }
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
