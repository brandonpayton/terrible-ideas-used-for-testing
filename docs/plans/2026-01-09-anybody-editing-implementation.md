# Anybody Editing Plugin - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a WordPress plugin that allows public visitors to edit posts inline using Gutenberg block editing.

**Architecture:** PHP backend with admin meta box, custom REST API endpoints for unauthenticated saves, and React/Gutenberg frontend for inline block editing. Scripts load only on opted-in posts.

**Tech Stack:** PHP 7.4+, WordPress 6.0+, @wordpress/scripts, @wordpress/block-editor, React, REST API

---

## Task 1: Project Scaffolding

**Files:**
- Create: `package.json`
- Create: `anybody-editing.php`
- Create: `.editorconfig`

**Step 1: Create package.json**

```json
{
  "name": "anybody-editing",
  "version": "1.0.0",
  "description": "Allow public visitors to edit WordPress posts",
  "scripts": {
    "build": "wp-scripts build",
    "start": "wp-scripts start",
    "lint:js": "wp-scripts lint-js",
    "lint:css": "wp-scripts lint-style",
    "test:js": "wp-scripts test-unit-js"
  },
  "devDependencies": {
    "@wordpress/scripts": "^27.0.0"
  },
  "dependencies": {
    "@wordpress/api-fetch": "^6.0.0",
    "@wordpress/block-editor": "^12.0.0",
    "@wordpress/blocks": "^12.0.0",
    "@wordpress/components": "^25.0.0",
    "@wordpress/element": "^5.0.0",
    "@wordpress/i18n": "^4.0.0"
  }
}
```

**Step 2: Create main plugin file**

```php
<?php
/**
 * Plugin Name: Anybody Editing
 * Description: Allow public visitors to edit WordPress posts
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: anybody-editing
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'ANYBODY_EDITING_VERSION', '1.0.0' );
define( 'ANYBODY_EDITING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ANYBODY_EDITING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes
require_once ANYBODY_EDITING_PLUGIN_DIR . 'includes/class-admin.php';
require_once ANYBODY_EDITING_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once ANYBODY_EDITING_PLUGIN_DIR . 'includes/class-frontend.php';

/**
 * Initialize the plugin.
 */
function anybody_editing_init() {
    new Anybody_Editing_Admin();
    new Anybody_Editing_REST_API();
    new Anybody_Editing_Frontend();
}
add_action( 'plugins_loaded', 'anybody_editing_init' );
```

**Step 3: Create .editorconfig**

```ini
root = true

[*]
indent_style = tab
indent_size = 4
end_of_line = lf
charset = utf-8
trim_trailing_whitespace = true
insert_final_newline = true

[*.{js,jsx,json,css,scss}]
indent_style = space
indent_size = 2

[*.md]
trim_trailing_whitespace = false
```

**Step 4: Create includes directory**

Run: `mkdir -p includes`

**Step 5: Install dependencies**

Run: `npm install`
Expected: node_modules created, package-lock.json generated

**Step 6: Commit**

```bash
git add -A
git commit -m "feat: scaffold plugin with package.json and main file"
```

---

## Task 2: Admin Meta Box

**Files:**
- Create: `includes/class-admin.php`

**Step 1: Create Admin class with meta box**

```php
<?php
/**
 * Admin functionality for Anybody Editing.
 */

defined( 'ABSPATH' ) || exit;

class Anybody_Editing_Admin {

    const META_KEY = '_anybody_editing_enabled';

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
    }

    /**
     * Register the meta box.
     */
    public function add_meta_box() {
        add_meta_box(
            'anybody-editing-meta-box',
            __( 'Public Editing', 'anybody-editing' ),
            array( $this, 'render_meta_box' ),
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render the meta box content.
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box( $post ) {
        $enabled = get_post_meta( $post->ID, self::META_KEY, true );
        wp_nonce_field( 'anybody_editing_meta_box', 'anybody_editing_nonce' );
        ?>
        <label>
            <input type="checkbox" name="anybody_editing_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
            <?php esc_html_e( 'Allow anyone to edit this post', 'anybody-editing' ); ?>
        </label>
        <?php if ( $enabled ) : ?>
            <p class="description" style="margin-top: 8px;">
                <?php esc_html_e( 'Visitors can edit this post without logging in.', 'anybody-editing' ); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Save the meta box value.
     *
     * @param int     $post_id The post ID.
     * @param WP_Post $post    The post object.
     */
    public function save_meta_box( $post_id, $post ) {
        // Verify nonce
        if ( ! isset( $_POST['anybody_editing_nonce'] ) ||
             ! wp_verify_nonce( $_POST['anybody_editing_nonce'], 'anybody_editing_meta_box' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save or delete meta
        if ( isset( $_POST['anybody_editing_enabled'] ) && $_POST['anybody_editing_enabled'] === '1' ) {
            update_post_meta( $post_id, self::META_KEY, '1' );
        } else {
            delete_post_meta( $post_id, self::META_KEY );
        }
    }

    /**
     * Check if a post has public editing enabled.
     *
     * @param int $post_id The post ID.
     * @return bool
     */
    public static function is_editing_enabled( $post_id ) {
        return get_post_meta( $post_id, self::META_KEY, true ) === '1';
    }
}
```

**Step 2: Verify plugin loads without errors**

Activate plugin in WordPress admin, visit post edit screen.
Expected: "Public Editing" meta box appears in sidebar.

**Step 3: Commit**

```bash
git add includes/class-admin.php
git commit -m "feat: add admin meta box for enabling public editing"
```

---

## Task 3: REST API Endpoints

**Files:**
- Create: `includes/class-rest-api.php`

**Step 1: Create REST API class with post update endpoint**

```php
<?php
/**
 * REST API endpoints for Anybody Editing.
 */

defined( 'ABSPATH' ) || exit;

class Anybody_Editing_REST_API {

    const NAMESPACE = 'anybody-editing/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            self::NAMESPACE,
            '/posts/(?P<id>\d+)',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'update_post' ),
                'permission_callback' => array( $this, 'check_post_permission' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                    'title'          => array( 'type' => 'string' ),
                    'content'        => array( 'type' => 'string' ),
                    'excerpt'        => array( 'type' => 'string' ),
                    'featured_media' => array( 'type' => 'integer' ),
                    'categories'     => array( 'type' => 'array' ),
                    'tags'           => array( 'type' => 'array' ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/upload',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'upload_image' ),
                'permission_callback' => array( $this, 'check_upload_permission' ),
                'args'                => array(
                    'post_id' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                    ),
                ),
            )
        );
    }

    /**
     * Check if post can be edited publicly.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error
     */
    public function check_post_permission( $request ) {
        $post_id = $request->get_param( 'id' );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error(
                'not_found',
                __( 'Post not found.', 'anybody-editing' ),
                array( 'status' => 404 )
            );
        }

        if ( $post->post_status !== 'publish' ) {
            return new WP_Error(
                'not_published',
                __( 'This post is not published.', 'anybody-editing' ),
                array( 'status' => 403 )
            );
        }

        if ( ! Anybody_Editing_Admin::is_editing_enabled( $post_id ) ) {
            return new WP_Error(
                'editing_disabled',
                __( 'This post is no longer editable.', 'anybody-editing' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Check upload permission.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error
     */
    public function check_upload_permission( $request ) {
        $post_id = $request->get_param( 'post_id' );
        return $this->check_post_permission_by_id( $post_id );
    }

    /**
     * Check post permission by ID.
     *
     * @param int $post_id The post ID.
     * @return bool|WP_Error
     */
    private function check_post_permission_by_id( $post_id ) {
        $post = get_post( $post_id );

        if ( ! $post || $post->post_status !== 'publish' ) {
            return new WP_Error(
                'invalid_post',
                __( 'Invalid or unpublished post.', 'anybody-editing' ),
                array( 'status' => 403 )
            );
        }

        if ( ! Anybody_Editing_Admin::is_editing_enabled( $post_id ) ) {
            return new WP_Error(
                'editing_disabled',
                __( 'This post is no longer editable.', 'anybody-editing' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Update a post.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function update_post( $request ) {
        $post_id = $request->get_param( 'id' );
        $post    = get_post( $post_id );

        $update_args = array( 'ID' => $post_id );

        // Sanitize and prepare fields
        if ( $request->has_param( 'title' ) ) {
            $update_args['post_title'] = sanitize_text_field( $request->get_param( 'title' ) );
        }

        if ( $request->has_param( 'content' ) ) {
            $update_args['post_content'] = wp_kses_post( $request->get_param( 'content' ) );
        }

        if ( $request->has_param( 'excerpt' ) ) {
            $update_args['post_excerpt'] = sanitize_textarea_field( $request->get_param( 'excerpt' ) );
        }

        // Update post (creates revision automatically)
        $result = wp_update_post( $update_args, true );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Update featured image
        if ( $request->has_param( 'featured_media' ) ) {
            $attachment_id = absint( $request->get_param( 'featured_media' ) );
            if ( $attachment_id > 0 ) {
                set_post_thumbnail( $post_id, $attachment_id );
            } else {
                delete_post_thumbnail( $post_id );
            }
        }

        // Update categories
        if ( $request->has_param( 'categories' ) ) {
            $categories = array_map( 'absint', $request->get_param( 'categories' ) );
            wp_set_post_categories( $post_id, $categories );
        }

        // Update tags
        if ( $request->has_param( 'tags' ) ) {
            $tags = array_map( 'absint', $request->get_param( 'tags' ) );
            wp_set_post_tags( $post_id, $tags );
        }

        // Return updated post data
        $updated_post = get_post( $post_id );

        return rest_ensure_response( array(
            'id'             => $updated_post->ID,
            'title'          => $updated_post->post_title,
            'content'        => $updated_post->post_content,
            'excerpt'        => $updated_post->post_excerpt,
            'featured_media' => get_post_thumbnail_id( $post_id ),
        ) );
    }

    /**
     * Handle image upload.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function upload_image( $request ) {
        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new WP_Error(
                'no_file',
                __( 'No file uploaded.', 'anybody-editing' ),
                array( 'status' => 400 )
            );
        }

        $file = $files['file'];

        // Validate file type
        $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
        $file_type     = wp_check_filetype( $file['name'] );

        if ( ! in_array( $file['type'], $allowed_types, true ) ) {
            return new WP_Error(
                'invalid_type',
                __( 'Only images (JPG, PNG, GIF, WebP) are allowed.', 'anybody-editing' ),
                array( 'status' => 400 )
            );
        }

        // Validate file size (default 2MB)
        $max_size = apply_filters( 'anybody_editing_max_upload_size', 2 * 1024 * 1024 );
        if ( $file['size'] > $max_size ) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    __( 'Image exceeds the %s limit.', 'anybody-editing' ),
                    size_format( $max_size )
                ),
                array( 'status' => 400 )
            );
        }

        // Include required files for media handling
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Upload the file
        $upload = wp_handle_upload( $file, array( 'test_form' => false ) );

        if ( isset( $upload['error'] ) ) {
            return new WP_Error(
                'upload_failed',
                __( 'Upload failed. Please try again.', 'anybody-editing' ),
                array( 'status' => 500 )
            );
        }

        // Create attachment
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        // Generate attachment metadata
        $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        // Add tracking meta
        $post_id = $request->get_param( 'post_id' );
        update_post_meta( $attachment_id, '_anybody_editing_upload', '1' );
        update_post_meta( $attachment_id, '_anybody_editing_source_post', $post_id );
        update_post_meta( $attachment_id, '_anybody_editing_upload_ip', $this->get_client_ip() );

        return rest_ensure_response( array(
            'id'  => $attachment_id,
            'url' => wp_get_attachment_url( $attachment_id ),
        ) );
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field( $ip );
    }
}
```

**Step 2: Test REST endpoint exists**

Run: `curl -s -o /dev/null -w "%{http_code}" http://your-site.local/wp-json/anybody-editing/v1/posts/1`
Expected: 403 (permission denied, since editing not enabled yet)

**Step 3: Commit**

```bash
git add includes/class-rest-api.php
git commit -m "feat: add REST API endpoints for post updates and image uploads"
```

---

## Task 4: Frontend Class (PHP)

**Files:**
- Create: `includes/class-frontend.php`

**Step 1: Create Frontend class**

```php
<?php
/**
 * Frontend functionality for Anybody Editing.
 */

defined( 'ABSPATH' ) || exit;

class Anybody_Editing_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_scripts' ) );
        add_filter( 'the_content', array( $this, 'wrap_blocks' ), 20 );
    }

    /**
     * Check if we should load editor scripts on current page.
     *
     * @return bool
     */
    private function should_load_editor() {
        if ( ! is_singular( 'post' ) ) {
            return false;
        }

        return Anybody_Editing_Admin::is_editing_enabled( get_the_ID() );
    }

    /**
     * Enqueue frontend scripts and styles if editing is enabled.
     */
    public function maybe_enqueue_scripts() {
        if ( ! $this->should_load_editor() ) {
            return;
        }

        $asset_file = ANYBODY_EDITING_PLUGIN_DIR . 'build/index.asset.php';

        if ( ! file_exists( $asset_file ) ) {
            return;
        }

        $asset = include $asset_file;

        wp_enqueue_script(
            'anybody-editing-frontend',
            ANYBODY_EDITING_PLUGIN_URL . 'build/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'anybody-editing-frontend',
            ANYBODY_EDITING_PLUGIN_URL . 'build/index.css',
            array( 'wp-components', 'wp-block-editor' ),
            $asset['version']
        );

        // Pass data to JavaScript
        wp_localize_script(
            'anybody-editing-frontend',
            'anybodyEditingData',
            array(
                'postId'    => get_the_ID(),
                'restUrl'   => rest_url( 'anybody-editing/v1' ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'post'      => array(
                    'title'          => get_the_title(),
                    'content'        => get_post_field( 'post_content', get_the_ID() ),
                    'excerpt'        => get_the_excerpt(),
                    'featured_media' => get_post_thumbnail_id(),
                ),
            )
        );
    }

    /**
     * Wrap each block with a container for edit buttons.
     *
     * @param string $content The post content.
     * @return string
     */
    public function wrap_blocks( $content ) {
        if ( ! $this->should_load_editor() ) {
            return $content;
        }

        // Parse blocks from content
        $blocks = parse_blocks( get_post_field( 'post_content', get_the_ID() ) );

        if ( empty( $blocks ) ) {
            return $content;
        }

        $output     = '';
        $block_index = 0;

        foreach ( $blocks as $block ) {
            // Skip empty blocks (usually spacing between blocks)
            if ( empty( $block['blockName'] ) ) {
                $output .= render_block( $block );
                continue;
            }

            $rendered = render_block( $block );

            // Wrap block with editing container
            $output .= sprintf(
                '<div class="anybody-editing-block" data-block-index="%d" data-block-name="%s">%s</div>',
                $block_index,
                esc_attr( $block['blockName'] ),
                $rendered
            );

            $block_index++;
        }

        return $output;
    }
}
```

**Step 2: Commit**

```bash
git add includes/class-frontend.php
git commit -m "feat: add frontend class for script loading and block wrapping"
```

---

## Task 5: JavaScript Entry Point and API Helpers

**Files:**
- Create: `src/index.js`
- Create: `src/api/index.js`
- Create: `src/index.css`

**Step 1: Create API helpers**

```javascript
// src/api/index.js
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
```

**Step 2: Create main entry point**

```javascript
// src/index.js
import { render } from '@wordpress/element';
import './index.css';
import App from './components/App';

/**
 * Initialize the frontend editor.
 */
function init() {
  const container = document.createElement('div');
  container.id = 'anybody-editing-root';
  document.body.appendChild(container);

  render(<App />, container);
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
```

**Step 3: Create base styles**

```css
/* src/index.css */
.anybody-editing-block {
  position: relative;
}

.anybody-editing-block:hover .anybody-editing-edit-button,
.anybody-editing-block:focus-within .anybody-editing-edit-button {
  opacity: 1;
}

.anybody-editing-edit-button {
  position: absolute;
  top: 4px;
  right: 4px;
  opacity: 0;
  transition: opacity 0.2s ease;
  z-index: 100;
}

.anybody-editing-edit-button:focus {
  opacity: 1;
}

.anybody-editing-editor-wrapper {
  border: 2px solid #007cba;
  border-radius: 4px;
  padding: 16px;
  margin: 8px 0;
  background: #fff;
}

.anybody-editing-editor-actions {
  display: flex;
  gap: 8px;
  margin-top: 16px;
  justify-content: flex-end;
}

.anybody-editing-field-editor {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.anybody-editing-field-input {
  font-size: inherit;
  font-family: inherit;
  padding: 4px 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

.anybody-editing-error {
  color: #cc0000;
  padding: 8px;
  background: #fff0f0;
  border-radius: 4px;
  margin-top: 8px;
}

.anybody-editing-dropzone {
  border: 2px dashed #ccc;
  border-radius: 8px;
  padding: 32px;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.anybody-editing-dropzone:hover,
.anybody-editing-dropzone.is-dragging {
  border-color: #007cba;
  background: #f0f7fc;
}

.anybody-editing-dropzone input[type="file"] {
  display: none;
}
```

**Step 4: Commit**

```bash
git add src/index.js src/api/index.js src/index.css
git commit -m "feat: add JavaScript entry point and API helpers"
```

---

## Task 6: App Component

**Files:**
- Create: `src/components/App.js`

**Step 1: Create App component**

```javascript
// src/components/App.js
import { useState, useEffect } from '@wordpress/element';
import EditButton from './EditButton';
import BlockEditor from './BlockEditor';

export default function App() {
  const [editingBlock, setEditingBlock] = useState(null);
  const [blocks, setBlocks] = useState([]);

  useEffect(() => {
    // Find all editable blocks
    const blockElements = document.querySelectorAll('.anybody-editing-block');
    const blockData = Array.from(blockElements).map((el, index) => ({
      element: el,
      index,
      name: el.dataset.blockName,
    }));
    setBlocks(blockData);

    // Add edit buttons to each block
    blockData.forEach((block) => {
      const buttonContainer = document.createElement('div');
      buttonContainer.className = 'anybody-editing-button-container';
      block.element.appendChild(buttonContainer);
    });
  }, []);

  // Render edit buttons into each block
  useEffect(() => {
    blocks.forEach((block) => {
      const container = block.element.querySelector('.anybody-editing-button-container');
      if (container && editingBlock !== block.index) {
        import('@wordpress/element').then(({ render }) => {
          render(
            <EditButton onClick={() => setEditingBlock(block.index)} />,
            container
          );
        });
      }
    });
  }, [blocks, editingBlock]);

  // Handle block editing
  useEffect(() => {
    if (editingBlock === null) return;

    const block = blocks[editingBlock];
    if (!block) return;

    // Hide original content, show editor
    const originalContent = block.element.querySelector(':not(.anybody-editing-button-container):not(.anybody-editing-editor-container)');

    // Create editor container if it doesn't exist
    let editorContainer = block.element.querySelector('.anybody-editing-editor-container');
    if (!editorContainer) {
      editorContainer = document.createElement('div');
      editorContainer.className = 'anybody-editing-editor-container';
      block.element.appendChild(editorContainer);
    }

    import('@wordpress/element').then(({ render }) => {
      render(
        <BlockEditor
          blockIndex={editingBlock}
          blockName={block.name}
          onSave={() => {
            setEditingBlock(null);
            window.location.reload(); // Refresh to show updated content
          }}
          onCancel={() => setEditingBlock(null)}
        />,
        editorContainer
      );
    });

    return () => {
      // Cleanup when editing ends
      if (editorContainer) {
        editorContainer.innerHTML = '';
      }
    };
  }, [editingBlock, blocks]);

  return null; // This component manages DOM directly
}
```

**Step 2: Commit**

```bash
git add src/components/App.js
git commit -m "feat: add App component for managing block editing state"
```

---

## Task 7: EditButton Component

**Files:**
- Create: `src/components/EditButton.js`

**Step 1: Create EditButton component**

```javascript
// src/components/EditButton.js
import { Button } from '@wordpress/components';
import { edit } from '@wordpress/icons';

export default function EditButton({ onClick }) {
  return (
    <Button
      className="anybody-editing-edit-button"
      icon={edit}
      label="Edit this block"
      onClick={onClick}
      variant="primary"
      size="small"
    />
  );
}
```

**Step 2: Commit**

```bash
git add src/components/EditButton.js
git commit -m "feat: add EditButton component"
```

---

## Task 8: BlockEditor Component

**Files:**
- Create: `src/components/BlockEditor.js`

**Step 1: Create BlockEditor component**

```javascript
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

export default function BlockEditor({ blockIndex, blockName, onSave, onCancel }) {
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState(null);

  // Parse the original content to get blocks
  const originalContent = window.anybodyEditingData.post.content;
  const allBlocks = useMemo(() => parse(originalContent), [originalContent]);

  // Get just the block we're editing
  const [blocks, setBlocks] = useState(() => {
    // Filter to only non-empty blocks and get the one at our index
    const nonEmptyBlocks = allBlocks.filter((b) => b.name);
    return nonEmptyBlocks[blockIndex] ? [nonEmptyBlocks[blockIndex]] : [];
  });

  const handleSave = async () => {
    setIsSaving(true);
    setError(null);

    try {
      // Replace the edited block in the full content
      const nonEmptyBlocks = allBlocks.filter((b) => b.name);
      nonEmptyBlocks[blockIndex] = blocks[0];

      // Rebuild full content with empty blocks preserved
      let finalBlocks = [];
      let nonEmptyIndex = 0;
      for (const block of allBlocks) {
        if (block.name) {
          finalBlocks.push(nonEmptyBlocks[nonEmptyIndex]);
          nonEmptyIndex++;
        } else {
          finalBlocks.push(block);
        }
      }

      const newContent = serialize(finalBlocks);

      await updatePost({ content: newContent });
      onSave();
    } catch (err) {
      setError(err.message || 'Failed to save. Please try again.');
      setIsSaving(false);
    }
  };

  return (
    <div className="anybody-editing-editor-wrapper">
      <BlockEditorProvider
        value={blocks}
        onInput={(newBlocks) => setBlocks(newBlocks)}
        onChange={(newBlocks) => setBlocks(newBlocks)}
      >
        <WritingFlow>
          <ObserveTyping>
            <BlockList />
          </ObserveTyping>
        </WritingFlow>
        <Popover.Slot />
      </BlockEditorProvider>

      {error && <div className="anybody-editing-error">{error}</div>}

      <div className="anybody-editing-editor-actions">
        <Button variant="tertiary" onClick={onCancel} disabled={isSaving}>
          Cancel
        </Button>
        <Button variant="primary" onClick={handleSave} disabled={isSaving}>
          {isSaving ? <Spinner /> : 'Save'}
        </Button>
      </div>
    </div>
  );
}
```

**Step 2: Commit**

```bash
git add src/components/BlockEditor.js
git commit -m "feat: add BlockEditor component for inline Gutenberg editing"
```

---

## Task 9: FieldEditor Component (Title, Excerpt)

**Files:**
- Create: `src/components/FieldEditor.js`

**Step 1: Create FieldEditor component**

```javascript
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
```

**Step 2: Commit**

```bash
git add src/components/FieldEditor.js
git commit -m "feat: add FieldEditor component for title and excerpt editing"
```

---

## Task 10: ImageUploader Component

**Files:**
- Create: `src/components/ImageUploader.js`

**Step 1: Create ImageUploader component**

```javascript
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
```

**Step 2: Commit**

```bash
git add src/components/ImageUploader.js
git commit -m "feat: add ImageUploader component with drag-and-drop"
```

---

## Task 11: Build and Test

**Step 1: Build the JavaScript**

Run: `npm run build`
Expected: `build/` directory created with `index.js`, `index.css`, `index.asset.php`

**Step 2: Verify build output**

Run: `ls -la build/`
Expected: Files present, non-zero sizes

**Step 3: Manual testing checklist**

1. Activate plugin in WordPress
2. Create a post and enable "Allow anyone to edit this post"
3. View post on frontend (logged out)
4. Verify edit buttons appear on hover
5. Click edit button, verify Gutenberg editor opens inline
6. Make a change and save
7. Verify content updates

**Step 4: Commit build**

```bash
git add build/
git commit -m "chore: add production build"
```

---

## Task 12: Settings Page (Optional Enhancement)

**Files:**
- Modify: `includes/class-admin.php`

**Step 1: Add settings page**

Add to `Anybody_Editing_Admin::__construct()`:

```php
add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
add_action( 'admin_init', array( $this, 'register_settings' ) );
```

**Step 2: Add settings methods**

```php
/**
 * Add settings page to admin menu.
 */
public function add_settings_page() {
    add_options_page(
        __( 'Anybody Editing', 'anybody-editing' ),
        __( 'Anybody Editing', 'anybody-editing' ),
        'manage_options',
        'anybody-editing',
        array( $this, 'render_settings_page' )
    );
}

/**
 * Register plugin settings.
 */
public function register_settings() {
    register_setting( 'anybody_editing_settings', 'anybody_editing_max_upload_size' );
    register_setting( 'anybody_editing_settings', 'anybody_editing_allowed_types' );
}

/**
 * Render settings page.
 */
public function render_settings_page() {
    $max_size = get_option( 'anybody_editing_max_upload_size', 2 );
    $allowed_types = get_option( 'anybody_editing_allowed_types', array( 'jpg', 'png', 'gif', 'webp' ) );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Anybody Editing Settings', 'anybody-editing' ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'anybody_editing_settings' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="max_upload_size"><?php esc_html_e( 'Max Upload Size (MB)', 'anybody-editing' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max_upload_size" name="anybody_editing_max_upload_size" value="<?php echo esc_attr( $max_size ); ?>" min="1" max="10">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Allowed Image Types', 'anybody-editing' ); ?></th>
                    <td>
                        <?php foreach ( array( 'jpg', 'png', 'gif', 'webp' ) as $type ) : ?>
                            <label>
                                <input type="checkbox" name="anybody_editing_allowed_types[]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, (array) $allowed_types, true ) ); ?>>
                                <?php echo esc_html( strtoupper( $type ) ); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
```

**Step 3: Commit**

```bash
git add includes/class-admin.php
git commit -m "feat: add settings page for upload configuration"
```

---

## Task 13: Media Library Filter (Optional Enhancement)

**Files:**
- Modify: `includes/class-admin.php`

**Step 1: Add media library filter hooks**

Add to `Anybody_Editing_Admin::__construct()`:

```php
add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_library' ) );
add_action( 'restrict_manage_posts', array( $this, 'add_media_filter_dropdown' ) );
add_filter( 'parse_query', array( $this, 'filter_media_query' ) );
```

**Step 2: Add filter methods**

```php
/**
 * Add filter dropdown to media library.
 */
public function add_media_filter_dropdown() {
    $screen = get_current_screen();
    if ( $screen->id !== 'upload' ) {
        return;
    }

    $selected = isset( $_GET['anybody_editing_filter'] ) ? $_GET['anybody_editing_filter'] : '';
    ?>
    <select name="anybody_editing_filter">
        <option value=""><?php esc_html_e( 'All uploads', 'anybody-editing' ); ?></option>
        <option value="visitor" <?php selected( $selected, 'visitor' ); ?>><?php esc_html_e( 'Visitor uploads', 'anybody-editing' ); ?></option>
        <option value="admin" <?php selected( $selected, 'admin' ); ?>><?php esc_html_e( 'Admin uploads', 'anybody-editing' ); ?></option>
    </select>
    <?php
}

/**
 * Filter media query based on dropdown selection.
 *
 * @param WP_Query $query The query object.
 */
public function filter_media_query( $query ) {
    global $pagenow;

    if ( $pagenow !== 'upload.php' || ! isset( $_GET['anybody_editing_filter'] ) ) {
        return;
    }

    $filter = $_GET['anybody_editing_filter'];

    if ( $filter === 'visitor' ) {
        $query->query_vars['meta_key']   = '_anybody_editing_upload';
        $query->query_vars['meta_value'] = '1';
    } elseif ( $filter === 'admin' ) {
        $query->query_vars['meta_query'] = array(
            array(
                'key'     => '_anybody_editing_upload',
                'compare' => 'NOT EXISTS',
            ),
        );
    }
}

/**
 * Filter media library AJAX query.
 *
 * @param array $query Query arguments.
 * @return array
 */
public function filter_media_library( $query ) {
    if ( isset( $_REQUEST['query']['anybody_editing_filter'] ) ) {
        $filter = $_REQUEST['query']['anybody_editing_filter'];

        if ( $filter === 'visitor' ) {
            $query['meta_key']   = '_anybody_editing_upload';
            $query['meta_value'] = '1';
        } elseif ( $filter === 'admin' ) {
            $query['meta_query'] = array(
                array(
                    'key'     => '_anybody_editing_upload',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }
    }

    return $query;
}
```

**Step 3: Commit**

```bash
git add includes/class-admin.php
git commit -m "feat: add media library filter for visitor uploads"
```

---

## Task 14: Final Polish

**Files:**
- Create: `readme.txt`

**Step 1: Create readme.txt**

```
=== Anybody Editing ===
Contributors: yourname
Tags: editing, wiki, collaboration, gutenberg, blocks
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allow public visitors to edit WordPress posts using inline Gutenberg block editing.

== Description ==

Anybody Editing transforms your WordPress posts into wiki-style editable pages. Enable public editing on specific posts, and visitors can edit content directly using the familiar Gutenberg block editor.

**Features:**

* Opt-in public editing per post
* Inline Gutenberg block editing
* Edit title, content, excerpt, featured image, categories, and tags
* Custom image upload for visitors
* Full revision history for rollback
* Media library filter for visitor uploads

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/anybody-editing`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Edit a post and check "Allow anyone to edit this post" in the sidebar
4. View the post on the frontend to see edit buttons on hover

== Changelog ==

= 1.0.0 =
* Initial release
```

**Step 2: Final commit**

```bash
git add readme.txt
git commit -m "docs: add plugin readme"
```

**Step 3: Merge to main (when ready)**

```bash
git checkout main
git merge feature/initial-implementation
```

---

## Summary

This plan covers:

1. **Project scaffolding** - package.json, main plugin file
2. **Admin meta box** - Enable editing per post
3. **REST API** - Post updates and image uploads
4. **Frontend PHP** - Script loading and block wrapping
5. **JavaScript foundation** - Entry point, API helpers
6. **React components** - App, EditButton, BlockEditor, FieldEditor, ImageUploader
7. **Build and test** - Production build, manual testing
8. **Optional enhancements** - Settings page, media library filter
9. **Documentation** - readme.txt

Each task is self-contained with exact file paths, complete code, and commit instructions.
