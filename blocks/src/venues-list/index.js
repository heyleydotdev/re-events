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
		order,
		orderby,
		upcoming,
		showDescription,
		excerptLength,
		showImage,
		imageSize,
		showAddress,
		showWebsite,
		showMapLink,
		showNextEvent,
		dateFormat,
		timeFormat,
		titleTag,
		link,
		paginate,
		paginationKey,
	} = attributes;
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
				<PanelBody title={ __( 'Venues', 're-events' ) }>
					<TextControl
						label={ __( 'Number of venues', 're-events' ) }
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
						label={ __( 'Order', 're-events' ) }
						value={ order }
						options={ [
							{
								value: 'asc',
								label: __( 'Ascending', 're-events' ),
							},
							{
								value: 'desc',
								label: __( 'Descending', 're-events' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { order: value } )
						}
					/>
					<SelectControl
						label={ __( 'Order by', 're-events' ) }
						value={ orderby }
						options={ [
							{
								value: 'title',
								label: __( 'Title', 're-events' ),
							},
							{ value: 'date', label: __( 'Date', 're-events' ) },
							{
								value: 'menu_order',
								label: __( 'Menu order', 're-events' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { orderby: value } )
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
					<ToggleControl
						label={ __( 'Show description', 're-events' ) }
						checked={ showDescription }
						onChange={ ( value ) =>
							setAttributes( { showDescription: value } )
						}
					/>
					{ showDescription && (
						<TextControl
							label={ __( 'Description length', 're-events' ) }
							type="number"
							min={ 0 }
							value={ String( excerptLength ) }
							help={ __(
								'Word count. 0 shows the full description.',
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
						label={ __( 'Show address', 're-events' ) }
						checked={ showAddress }
						onChange={ ( value ) =>
							setAttributes( { showAddress: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show website', 're-events' ) }
						checked={ showWebsite }
						onChange={ ( value ) =>
							setAttributes( { showWebsite: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show map link', 're-events' ) }
						checked={ showMapLink }
						onChange={ ( value ) =>
							setAttributes( { showMapLink: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show next event', 're-events' ) }
						checked={ showNextEvent }
						onChange={ ( value ) =>
							setAttributes( { showNextEvent: value } )
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
					<ToggleControl
						label={ __( 'Show pagination', 're-events' ) }
						help={ __(
							'Turn off for featured venue lists.',
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
