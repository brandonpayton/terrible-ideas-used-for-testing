import { render } from '@wordpress/element';
import { registerCoreBlocks } from '@wordpress/block-library';
import './index.css';
import App from './components/App';

/**
 * Initialize the frontend editor.
 */
function init() {
	// Register WordPress core blocks so they can be parsed and edited
	registerCoreBlocks();
	const container = document.createElement( 'div' );
	container.id = 'anybody-editing-root';
	document.body.appendChild( container );

	render( <App />, container );
}

// Initialize when DOM is ready
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
