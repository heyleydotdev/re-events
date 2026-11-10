import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { limit, emptyLabel, editLabel, wrapperClass } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'My events', 're-events' ) }>
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
						label={ __( 'Empty message', 're-events' ) }
						value={ emptyLabel }
						onChange={ ( value ) =>
							setAttributes( { emptyLabel: value } )
						}
					/>
					<TextControl
						label={ __( 'Edit label', 're-events' ) }
						value={ editLabel }
						onChange={ ( value ) =>
							setAttributes( { editLabel: value } )
						}
					/>
					<TextControl
						label={ __( 'Wrapper class', 're-events' ) }
						value={ wrapperClass }
						onChange={ ( value ) =>
							setAttributes( { wrapperClass: value } )
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
