// src/components/BlockEditor.js
import { useState, useMemo } from '@wordpress/element';
import {
	BlockEditorProvider,
	BlockList,
	WritingFlow,
	ObserveTyping,
} from '@wordpress/block-editor';
import { Popover, Button, Spinner } from '@wordpress/components';
import { parse, serialize } from '@wordpress/blocks';
import { updatePost } from '../api';

export default function BlockEditor( { blockIndex, onSave, onCancel } ) {
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	// Parse the original content to get blocks
	const originalContent = window.anybodyEditingData.post.content;
	const allBlocks = useMemo(
		() => parse( originalContent ),
		[ originalContent ]
	);

	// Get just the block we're editing
	const [ blocks, setBlocks ] = useState( () => {
		// Filter to only non-empty blocks and get the one at our index
		const nonEmptyBlocks = allBlocks.filter( ( b ) => b.name );
		return nonEmptyBlocks[ blockIndex ]
			? [ nonEmptyBlocks[ blockIndex ] ]
			: [];
	} );

	const handleSave = async () => {
		setIsSaving( true );
		setError( null );

		try {
			// Replace the edited block in the full content
			const nonEmptyBlocks = allBlocks.filter( ( b ) => b.name );
			nonEmptyBlocks[ blockIndex ] = blocks[ 0 ];

			// Rebuild full content with empty blocks preserved
			const finalBlocks = [];
			let nonEmptyIndex = 0;
			for ( const block of allBlocks ) {
				if ( block.name ) {
					finalBlocks.push( nonEmptyBlocks[ nonEmptyIndex ] );
					nonEmptyIndex++;
				} else {
					finalBlocks.push( block );
				}
			}

			const newContent = serialize( finalBlocks );

			await updatePost( { content: newContent } );
			onSave();
		} catch ( err ) {
			setError( err.message || 'Failed to save. Please try again.' );
			setIsSaving( false );
		}
	};

	return (
		<div className="anybody-editing-editor-wrapper">
			<BlockEditorProvider
				value={ blocks }
				onInput={ ( newBlocks ) => setBlocks( newBlocks ) }
				onChange={ ( newBlocks ) => setBlocks( newBlocks ) }
			>
				<WritingFlow>
					<ObserveTyping>
						<BlockList />
					</ObserveTyping>
				</WritingFlow>
				<Popover.Slot />
			</BlockEditorProvider>

			{ error && <div className="anybody-editing-error">{ error }</div> }

			<div className="anybody-editing-editor-actions">
				<Button
					variant="tertiary"
					onClick={ onCancel }
					disabled={ isSaving }
				>
					Cancel
				</Button>
				<Button
					variant="primary"
					onClick={ handleSave }
					disabled={ isSaving }
				>
					{ isSaving ? <Spinner /> : 'Save' }
				</Button>
			</div>
		</div>
	);
}
