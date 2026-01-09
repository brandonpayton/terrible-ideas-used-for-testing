// src/components/ImageUploader.js
import { useState, useRef } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { edit, upload } from '@wordpress/icons';
import { uploadImage, updatePost } from '../api';

export default function ImageUploader({ attachmentId, onUpdate }) {
  const [isEditing, setIsEditing] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [isDragging, setIsDragging] = useState(false);
  const [error, setError] = useState(null);
  const fileInputRef = useRef(null);

  const handleFileSelect = async (file) => {
    if (!file) return;

    // Validate file type client-side
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
      setError('Only images (JPG, PNG, GIF, WebP) are allowed.');
      return;
    }

    // Validate file size client-side (2MB)
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
      setError('Image exceeds the 2MB limit.');
      return;
    }

    setIsUploading(true);
    setError(null);

    try {
      const response = await uploadImage(file);
      await updatePost({ featured_media: response.id });
      setIsEditing(false);
      window.location.reload();
    } catch (err) {
      setError(err.message || 'Upload failed. Please try again.');
      setIsUploading(false);
    }
  };

  const handleDrop = (e) => {
    e.preventDefault();
    setIsDragging(false);
    const file = e.dataTransfer.files[0];
    handleFileSelect(file);
  };

  const handleDragOver = (e) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  if (!isEditing) {
    return (
      <Button
        className="anybody-editing-edit-button"
        icon={edit}
        label="Change image"
        onClick={() => setIsEditing(true)}
        variant="primary"
        size="small"
      />
    );
  }

  return (
    <div className="anybody-editing-editor-wrapper">
      <div
        className={`anybody-editing-dropzone ${isDragging ? 'is-dragging' : ''}`}
        onDrop={handleDrop}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onClick={() => fileInputRef.current?.click()}
      >
        <input
          type="file"
          ref={fileInputRef}
          accept="image/jpeg,image/png,image/gif,image/webp"
          onChange={(e) => handleFileSelect(e.target.files[0])}
        />
        {isUploading ? (
          <Spinner />
        ) : (
          <>
            <p>Drag and drop an image here, or click to select</p>
            <p className="description">JPG, PNG, GIF, or WebP (max 2MB)</p>
          </>
        )}
      </div>

      {error && <div className="anybody-editing-error">{error}</div>}

      <div className="anybody-editing-editor-actions">
        <Button
          variant="tertiary"
          onClick={() => setIsEditing(false)}
          disabled={isUploading}
        >
          Cancel
        </Button>
      </div>
    </div>
  );
}
