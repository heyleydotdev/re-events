import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const {
		submitLabel,
		saveLabel,
		loginMessage,
		successMessage,
		wrapperClass,
	} = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Submission form', 're-events' ) }>
					<TextControl
						label={ __( 'Submit label', 're-events' ) }
						value={ submitLabel }
						onChange={ ( value ) =>
							setAttributes( { submitLabel: value } )
						}
					/>
					<TextControl
						label={ __( 'Save label', 're-events' ) }
						value={ saveLabel }
						onChange={ ( value ) =>
							setAttributes( { saveLabel: value } )
						}
					/>
					<TextControl
						label={ __( 'Login message', 're-events' ) }
						value={ loginMessage }
						onChange={ ( value ) =>
							setAttributes( { loginMessage: value } )
						}
					/>
					<TextControl
						label={ __( 'Success message', 're-events' ) }
						value={ successMessage }
						onChange={ ( value ) =>
							setAttributes( { successMessage: value } )
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
