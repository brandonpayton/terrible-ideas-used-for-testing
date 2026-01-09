// src/components/FieldEditor.js
import { useState } from '@wordpress/element';
import { Button, TextControl, TextareaControl, Spinner } from '@wordpress/components';
import { edit, check, closeSmall } from '@wordpress/icons';
import { updatePost } from '../api';

export default function FieldEditor({ field, value: initialValue, multiline = false }) {
  const [isEditing, setIsEditing] = useState(false);
  const [value, setValue] = useState(initialValue);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState(null);

  const handleSave = async () => {
    setIsSaving(true);
    setError(null);

    try {
      await updatePost({ [field]: value });
      setIsEditing(false);
      window.location.reload(); // Refresh to show updated content
    } catch (err) {
      setError(err.message || 'Failed to save. Please try again.');
      setIsSaving(false);
    }
  };

  const handleCancel = () => {
    setValue(initialValue);
    setIsEditing(false);
    setError(null);
  };

  if (!isEditing) {
    return (
      <Button
        className="anybody-editing-edit-button"
        icon={edit}
        label={`Edit ${field}`}
        onClick={() => setIsEditing(true)}
        variant="primary"
        size="small"
      />
    );
  }

  const Control = multiline ? TextareaControl : TextControl;

  return (
    <div className="anybody-editing-field-editor">
      <Control
        value={value}
        onChange={setValue}
        className="anybody-editing-field-input"
        disabled={isSaving}
      />
      <Button
        icon={check}
        label="Save"
        onClick={handleSave}
        variant="primary"
        size="small"
        disabled={isSaving}
      />
      <Button
        icon={closeSmall}
        label="Cancel"
        onClick={handleCancel}
        variant="tertiary"
        size="small"
        disabled={isSaving}
      />
      {isSaving && <Spinner />}
      {error && <span className="anybody-editing-error">{error}</span>}
    </div>
  );
}
