import apiFetch from '@wordpress/api-fetch';

const { restUrl, postId } = window.anybodyEditingData;

/**
 * Update post fields.
 *
 * @param {Object} data Fields to update.
 * @return {Promise} API response.
 */
export async function updatePost(data) {
  return apiFetch({
    path: `anybody-editing/v1/posts/${postId}`,
    method: 'POST',
    data,
  });
}

/**
 * Upload an image.
 *
 * @param {File} file The image file.
 * @return {Promise} API response with attachment ID and URL.
 */
export async function uploadImage(file) {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('post_id', postId);

  return apiFetch({
    path: 'anybody-editing/v1/upload',
    method: 'POST',
    body: formData,
  });
}
