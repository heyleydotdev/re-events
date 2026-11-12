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
		showKeyword,
		showCategory,
		showTag,
		showVenue,
		showOrganizer,
		showDates,
		showUpcoming,
		showOrder,
		order,
		showResults,
		paginate,
		paginationKey,
		showImage,
		imageSize,
		showPrice,
		showExcerpt,
		showCategories,
		showTags,
		showOrganizers,
		showEnd,
		showSeatsLeft,
		showCancelled,
		titleTag,
		link,
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
				<PanelBody title={ __( 'Search fields', 're-events' ) }>
					<TextControl
						label={ __( 'Number of results', 're-events' ) }
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
					<ToggleControl
						label={ __( 'Show keyword', 're-events' ) }
						checked={ showKeyword }
						onChange={ ( value ) =>
							setAttributes( { showKeyword: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show category', 're-events' ) }
						checked={ showCategory }
						onChange={ ( value ) =>
							setAttributes( { showCategory: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show tag', 're-events' ) }
						checked={ showTag }
						onChange={ ( value ) =>
							setAttributes( { showTag: value } )
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
						label={ __( 'Show organizer', 're-events' ) }
						checked={ showOrganizer }
						onChange={ ( value ) =>
							setAttributes( { showOrganizer: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show dates', 're-events' ) }
						checked={ showDates }
						onChange={ ( value ) =>
							setAttributes( { showDates: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show upcoming toggle', 're-events' ) }
						checked={ showUpcoming }
						onChange={ ( value ) =>
							setAttributes( { showUpcoming: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show order', 're-events' ) }
						checked={ showOrder }
						onChange={ ( value ) =>
							setAttributes( { showOrder: value } )
						}
					/>
					{ showOrder && (
						<SelectControl
							label={ __( 'Default order', 're-events' ) }
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
					) }
					<ToggleControl
						label={ __( 'Show results', 're-events' ) }
						checked={ showResults }
						onChange={ ( value ) =>
							setAttributes( { showResults: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Results', 're-events' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Show pagination', 're-events' ) }
						checked={ paginate }
						onChange={ ( value ) =>
							setAttributes( { paginate: value } )
						}
					/>
					{ paginate && (
						<TextControl
							label={ __( 'Pagination key', 're-events' ) }
							help={ __(
								'Only needed when several searches share a page.',
								're-events'
							) }
							value={ paginationKey }
							onChange={ ( value ) =>
								setAttributes( { paginationKey: value } )
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
						label={ __( 'Show organizers', 're-events' ) }
						checked={ showOrganizers }
						onChange={ ( value ) =>
							setAttributes( { showOrganizers: value } )
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
