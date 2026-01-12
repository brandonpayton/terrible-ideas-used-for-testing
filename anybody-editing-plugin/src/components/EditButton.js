// src/components/EditButton.js
import { Button } from '@wordpress/components';
import { pencil } from '@wordpress/icons';

export default function EditButton( { onClick } ) {
	return (
		<Button
			className="anybody-editing-edit-button"
			icon={ pencil }
			label="Edit this block"
			onClick={ onClick }
			variant="primary"
			size="small"
		/>
	);
}
