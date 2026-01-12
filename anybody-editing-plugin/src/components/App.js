// src/components/App.js
import { useState, useEffect } from '@wordpress/element';
import EditButton from './EditButton';
import BlockEditor from './BlockEditor';

export default function App() {
	const [ editingBlock, setEditingBlock ] = useState( null );
	const [ blocks, setBlocks ] = useState( [] );

	useEffect( () => {
		// Find all editable blocks
		const blockElements = document.querySelectorAll(
			'.anybody-editing-block'
		);
		const blockData = Array.from( blockElements ).map( ( el, index ) => ( {
			element: el,
			index,
			name: el.dataset.blockName,
		} ) );
		setBlocks( blockData );

		// Add edit buttons to each block
		blockData.forEach( ( block ) => {
			const buttonContainer = document.createElement( 'div' );
			buttonContainer.className = 'anybody-editing-button-container';
			block.element.appendChild( buttonContainer );
		} );
	}, [] );

	// Render edit buttons into each block
	useEffect( () => {
		blocks.forEach( ( block ) => {
			const container = block.element.querySelector(
				'.anybody-editing-button-container'
			);
			if ( container && editingBlock !== block.index ) {
				import( '@wordpress/element' ).then( ( { render } ) => {
					render(
						<EditButton
							onClick={ () => setEditingBlock( block.index ) }
						/>,
						container
					);
				} );
			}
		} );
	}, [ blocks, editingBlock ] );

	// Handle block editing
	useEffect( () => {
		if ( editingBlock === null ) {
			return;
		}

		const block = blocks[ editingBlock ];
		if ( ! block ) {
			return;
		}

		// Create editor container if it doesn't exist
		let editorContainer = block.element.querySelector(
			'.anybody-editing-editor-container'
		);
		if ( ! editorContainer ) {
			editorContainer = document.createElement( 'div' );
			editorContainer.className = 'anybody-editing-editor-container';
			block.element.appendChild( editorContainer );
		}

		import( '@wordpress/element' ).then( ( { render } ) => {
			render(
				<BlockEditor
					blockIndex={ editingBlock }
					onSave={ () => {
						setEditingBlock( null );
						window.location.reload(); // Refresh to show updated content
					} }
					onCancel={ () => setEditingBlock( null ) }
				/>,
				editorContainer
			);
		} );

		return () => {
			// Cleanup when editing ends
			if ( editorContainer ) {
				editorContainer.innerHTML = '';
			}
		};
	}, [ editingBlock, blocks ] );

	return null; // This component manages DOM directly
}
