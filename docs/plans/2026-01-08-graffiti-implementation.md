# Graffiti Plugin Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a WordPress plugin that lets unauthenticated visitors leave drawings between paragraphs of posts/pages.

**Architecture:** Custom post type stores each graffiti drawing with meta linking it to a parent post and paragraph index. Frontend JavaScript detects paragraphs, shows hover triggers, and opens a canvas modal. REST API handles submissions. Content filter injects saved graffiti server-side.

**Tech Stack:** PHP 7.4+, WordPress 6.0+, vanilla JavaScript, HTML5 Canvas API

---

## Task 1: Plugin Scaffolding

**Files:**
- Create: `graffiti.php` (main plugin file)

**Step 1: Create main plugin file with header**

```php
<?php
/**
 * Plugin Name: Graffiti
 * Description: Let visitors leave drawings between paragraphs of your content.
 * Version: 1.0.0
 * Author: Brandon
 * License: GPL v2 or later
 * Text Domain: graffiti
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GRAFFITI_VERSION', '1.0.0' );
define( 'GRAFFITI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRAFFITI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
```

**Step 2: Verify plugin appears in WordPress admin**

Test: Activate WordPress Playground, install plugin, verify it appears in Plugins list.

**Step 3: Commit**

```bash
git add graffiti.php
git commit -m "feat: add plugin scaffolding"
```

---

## Task 2: Register Custom Post Type

**Files:**
- Modify: `graffiti.php`
- Create: `includes/class-graffiti-post-type.php`

**Step 1: Create the post type class**

Create `includes/class-graffiti-post-type.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Graffiti_Post_Type {

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    public function register_post_type() {
        $labels = array(
            'name'               => 'Graffiti',
            'singular_name'      => 'Graffiti',
            'menu_name'          => 'Graffiti',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Graffiti',
            'edit_item'          => 'View Graffiti',
            'view_item'          => 'View Graffiti',
            'all_items'          => 'All Graffiti',
            'search_items'       => 'Search Graffiti',
            'not_found'          => 'No graffiti found.',
            'not_found_in_trash' => 'No graffiti found in Trash.',
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-art',
            'capability_type'     => 'post',
            'supports'            => array( 'thumbnail' ),
            'has_archive'         => false,
            'rewrite'             => false,
            'show_in_rest'        => false,
        );

        register_post_type( 'graffiti', $args );
    }
}
```

**Step 2: Include and instantiate in main plugin file**

Add to `graffiti.php`:

```php
require_once GRAFFITI_PLUGIN_DIR . 'includes/class-graffiti-post-type.php';

function graffiti_init() {
    new Graffiti_Post_Type();
}
add_action( 'plugins_loaded', 'graffiti_init' );
```

**Step 3: Verify Graffiti menu appears in admin**

Test: Reload WordPress admin, verify "Graffiti" appears in sidebar with art icon.

**Step 4: Commit**

```bash
git add graffiti.php includes/
git commit -m "feat: register graffiti custom post type"
```

---

## Task 3: Register Post Meta

**Files:**
- Modify: `includes/class-graffiti-post-type.php`

**Step 1: Add meta registration to the class**

Add method to `Graffiti_Post_Type` class:

```php
public function __construct() {
    add_action( 'init', array( $this, 'register_post_type' ) );
    add_action( 'init', array( $this, 'register_meta' ) );
}

public function register_meta() {
    register_post_meta( 'graffiti', '_graffiti_post_id', array(
        'type'              => 'integer',
        'single'            => true,
        'sanitize_callback' => 'absint',
        'show_in_rest'      => false,
    ) );

    register_post_meta( 'graffiti', '_graffiti_paragraph_index', array(
        'type'              => 'integer',
        'single'            => true,
        'sanitize_callback' => 'absint',
        'show_in_rest'      => false,
    ) );

    register_post_meta( 'graffiti', '_graffiti_ip_address', array(
        'type'              => 'string',
        'single'            => true,
        'sanitize_callback' => 'sanitize_text_field',
        'show_in_rest'      => false,
    ) );
}
```

**Step 2: Commit**

```bash
git add includes/class-graffiti-post-type.php
git commit -m "feat: register graffiti post meta fields"
```

---

## Task 4: REST API Endpoint

**Files:**
- Create: `includes/class-graffiti-rest-api.php`
- Modify: `graffiti.php`

**Step 1: Create REST API class**

Create `includes/class-graffiti-rest-api.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Graffiti_REST_API {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'graffiti/v1', '/drawings', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_drawing' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'post_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'paragraph_index' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
                'image_data' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    public function create_drawing( $request ) {
        $post_id         = $request->get_param( 'post_id' );
        $paragraph_index = $request->get_param( 'paragraph_index' );
        $image_data      = $request->get_param( 'image_data' );

        // Verify parent post exists
        $parent_post = get_post( $post_id );
        if ( ! $parent_post || ! in_array( $parent_post->post_type, array( 'post', 'page' ), true ) ) {
            return new WP_Error( 'invalid_post', 'Invalid parent post.', array( 'status' => 400 ) );
        }

        // Decode base64 image
        if ( strpos( $image_data, 'data:image/png;base64,' ) !== 0 ) {
            return new WP_Error( 'invalid_image', 'Invalid image data.', array( 'status' => 400 ) );
        }

        $image_data = str_replace( 'data:image/png;base64,', '', $image_data );
        $image_data = base64_decode( $image_data );

        if ( false === $image_data ) {
            return new WP_Error( 'decode_failed', 'Failed to decode image.', array( 'status' => 400 ) );
        }

        // Create graffiti post
        $graffiti_id = wp_insert_post( array(
            'post_type'   => 'graffiti',
            'post_status' => 'publish',
            'post_title'  => 'Graffiti on ' . $parent_post->post_title,
        ) );

        if ( is_wp_error( $graffiti_id ) ) {
            return new WP_Error( 'create_failed', 'Failed to create graffiti.', array( 'status' => 500 ) );
        }

        // Save meta
        update_post_meta( $graffiti_id, '_graffiti_post_id', $post_id );
        update_post_meta( $graffiti_id, '_graffiti_paragraph_index', $paragraph_index );
        update_post_meta( $graffiti_id, '_graffiti_ip_address', $this->get_client_ip() );

        // Upload image to media library
        $upload = wp_upload_bits( 'graffiti-' . $graffiti_id . '.png', null, $image_data );

        if ( $upload['error'] ) {
            wp_delete_post( $graffiti_id, true );
            return new WP_Error( 'upload_failed', $upload['error'], array( 'status' => 500 ) );
        }

        // Create attachment
        $attachment_id = wp_insert_attachment( array(
            'post_mime_type' => 'image/png',
            'post_title'     => 'Graffiti ' . $graffiti_id,
            'post_status'    => 'inherit',
        ), $upload['file'], $graffiti_id );

        if ( is_wp_error( $attachment_id ) ) {
            wp_delete_post( $graffiti_id, true );
            return new WP_Error( 'attachment_failed', 'Failed to create attachment.', array( 'status' => 500 ) );
        }

        // Generate attachment metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $attachment_data );

        // Set as featured image
        set_post_thumbnail( $graffiti_id, $attachment_id );

        return array(
            'success'     => true,
            'graffiti_id' => $graffiti_id,
            'image_url'   => wp_get_attachment_url( $attachment_id ),
        );
    }

    private function get_client_ip() {
        $ip = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return $ip;
    }
}
```

**Step 2: Include in main plugin file**

Add to `graffiti_init()` in `graffiti.php`:

```php
function graffiti_init() {
    new Graffiti_Post_Type();
    new Graffiti_REST_API();
}
```

Add require at top:

```php
require_once GRAFFITI_PLUGIN_DIR . 'includes/class-graffiti-rest-api.php';
```

**Step 3: Test endpoint exists**

Test: Visit `/wp-json/graffiti/v1/drawings` - should return method not allowed (GET not supported).

**Step 4: Commit**

```bash
git add includes/class-graffiti-rest-api.php graffiti.php
git commit -m "feat: add REST API endpoint for creating graffiti"
```

---

## Task 5: Frontend Assets Setup

**Files:**
- Create: `assets/css/graffiti.css`
- Create: `assets/js/graffiti.js`
- Modify: `graffiti.php`

**Step 1: Create CSS file**

Create `assets/css/graffiti.css`:

```css
/* Graffiti trigger between paragraphs */
.graffiti-trigger {
    position: relative;
    height: 20px;
    margin: 0;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.graffiti-trigger:hover {
    opacity: 1;
}

.graffiti-trigger::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #ccc;
}

.graffiti-trigger::after {
    content: '+';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 24px;
    height: 24px;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #666;
    line-height: 1;
}

/* Graffiti modal */
.graffiti-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100000;
}

.graffiti-modal {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.graffiti-canvas {
    border: 1px solid #ccc;
    cursor: crosshair;
    display: block;
    touch-action: none;
}

.graffiti-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.graffiti-colors {
    display: flex;
    gap: 5px;
}

.graffiti-color {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    padding: 0;
}

.graffiti-color.active {
    border-color: #333;
}

.graffiti-color[data-color="black"] { background: #000; }
.graffiti-color[data-color="red"] { background: #e53935; }
.graffiti-color[data-color="blue"] { background: #1e88e5; }
.graffiti-color[data-color="green"] { background: #43a047; }
.graffiti-color[data-color="yellow"] { background: #fdd835; }
.graffiti-color[data-color="white"] { background: #fff; border-color: #ccc; }

.graffiti-eraser {
    padding: 5px 10px;
    cursor: pointer;
    background: #f5f5f5;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.graffiti-eraser.active {
    background: #e0e0e0;
    border-color: #999;
}

.graffiti-actions {
    display: flex;
    gap: 10px;
    margin-left: auto;
}

.graffiti-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.graffiti-btn-clear {
    background: #f5f5f5;
    border: 1px solid #ccc;
}

.graffiti-btn-cancel {
    background: #f5f5f5;
    border: 1px solid #ccc;
}

.graffiti-btn-submit {
    background: #1e88e5;
    color: #fff;
}

.graffiti-btn-submit:hover {
    background: #1565c0;
}

/* Displayed graffiti */
.graffiti-cluster {
    margin: 20px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.graffiti-item {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.graffiti-item img {
    display: block;
    max-width: 100%;
    height: auto;
}
```

**Step 2: Create JavaScript file**

Create `assets/js/graffiti.js`:

```js
(function() {
    'use strict';

    const CANVAS_WIDTH = 400;
    const CANVAS_HEIGHT = 200;
    const BRUSH_SIZE = 4;
    const COLORS = ['black', 'red', 'blue', 'green', 'yellow', 'white'];
    const COLOR_MAP = {
        black: '#000000',
        red: '#e53935',
        blue: '#1e88e5',
        green: '#43a047',
        yellow: '#fdd835',
        white: '#ffffff'
    };

    let currentColor = 'black';
    let isErasing = false;
    let isDrawing = false;
    let canvas, ctx;
    let currentParagraphIndex = null;

    function init() {
        if (!window.graffitiData) return;

        const container = document.querySelector('.entry-content, .post-content, article');
        if (!container) return;

        injectTriggers(container);
    }

    function injectTriggers(container) {
        const blocks = container.querySelectorAll('p, h1, h2, h3, h4, h5, h6, ul, ol, blockquote, figure');

        blocks.forEach((block, index) => {
            // Create trigger before each block (except first)
            if (index > 0) {
                const trigger = document.createElement('div');
                trigger.className = 'graffiti-trigger';
                trigger.dataset.paragraphIndex = index;
                trigger.addEventListener('click', () => openModal(index));
                block.parentNode.insertBefore(trigger, block);
            }
        });

        // Add trigger after last block
        if (blocks.length > 0) {
            const lastBlock = blocks[blocks.length - 1];
            const trigger = document.createElement('div');
            trigger.className = 'graffiti-trigger';
            trigger.dataset.paragraphIndex = blocks.length;
            trigger.addEventListener('click', () => openModal(blocks.length));
            lastBlock.parentNode.insertBefore(trigger, lastBlock.nextSibling);
        }
    }

    function openModal(paragraphIndex) {
        currentParagraphIndex = paragraphIndex;

        const overlay = document.createElement('div');
        overlay.className = 'graffiti-modal-overlay';
        overlay.innerHTML = `
            <div class="graffiti-modal">
                <canvas class="graffiti-canvas" width="${CANVAS_WIDTH}" height="${CANVAS_HEIGHT}"></canvas>
                <div class="graffiti-toolbar">
                    <div class="graffiti-colors">
                        ${COLORS.map(c => `<button class="graffiti-color${c === 'black' ? ' active' : ''}" data-color="${c}"></button>`).join('')}
                    </div>
                    <button class="graffiti-eraser">Eraser</button>
                    <div class="graffiti-actions">
                        <button class="graffiti-btn graffiti-btn-clear">Clear</button>
                        <button class="graffiti-btn graffiti-btn-cancel">Cancel</button>
                        <button class="graffiti-btn graffiti-btn-submit">Submit</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        canvas = overlay.querySelector('.graffiti-canvas');
        ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        // Event listeners
        canvas.addEventListener('pointerdown', startDrawing);
        canvas.addEventListener('pointermove', draw);
        canvas.addEventListener('pointerup', stopDrawing);
        canvas.addEventListener('pointerleave', stopDrawing);

        overlay.querySelectorAll('.graffiti-color').forEach(btn => {
            btn.addEventListener('click', () => selectColor(btn.dataset.color, overlay));
        });

        overlay.querySelector('.graffiti-eraser').addEventListener('click', (e) => toggleEraser(e.target));
        overlay.querySelector('.graffiti-btn-clear').addEventListener('click', clearCanvas);
        overlay.querySelector('.graffiti-btn-cancel').addEventListener('click', () => closeModal(overlay));
        overlay.querySelector('.graffiti-btn-submit').addEventListener('click', () => submitDrawing(overlay));

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal(overlay);
        });
    }

    function startDrawing(e) {
        isDrawing = true;
        ctx.beginPath();
        ctx.moveTo(e.offsetX, e.offsetY);
    }

    function draw(e) {
        if (!isDrawing) return;

        ctx.lineWidth = BRUSH_SIZE;

        if (isErasing) {
            ctx.strokeStyle = '#ffffff';
        } else {
            ctx.strokeStyle = COLOR_MAP[currentColor];
        }

        ctx.lineTo(e.offsetX, e.offsetY);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(e.offsetX, e.offsetY);
    }

    function stopDrawing() {
        isDrawing = false;
        ctx.beginPath();
    }

    function selectColor(color, overlay) {
        currentColor = color;
        isErasing = false;

        overlay.querySelectorAll('.graffiti-color').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.color === color);
        });
        overlay.querySelector('.graffiti-eraser').classList.remove('active');
    }

    function toggleEraser(btn) {
        isErasing = !isErasing;
        btn.classList.toggle('active', isErasing);

        if (isErasing) {
            document.querySelectorAll('.graffiti-color').forEach(b => b.classList.remove('active'));
        } else {
            document.querySelector(`.graffiti-color[data-color="${currentColor}"]`).classList.add('active');
        }
    }

    function clearCanvas() {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
    }

    function closeModal(overlay) {
        overlay.remove();
        canvas = null;
        ctx = null;
        currentParagraphIndex = null;
        isDrawing = false;
    }

    function submitDrawing(overlay) {
        const imageData = canvas.toDataURL('image/png');

        fetch(window.graffitiData.restUrl + 'graffiti/v1/drawings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                post_id: window.graffitiData.postId,
                paragraph_index: currentParagraphIndex,
                image_data: imageData
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Insert the new graffiti into the page
                insertGraffiti(data.image_url, currentParagraphIndex);
                closeModal(overlay);
            } else {
                alert('Something went wrong. Please try again.');
            }
        })
        .catch(() => {
            alert('Something went wrong. Please try again.');
        });
    }

    function insertGraffiti(imageUrl, paragraphIndex) {
        const trigger = document.querySelector(`.graffiti-trigger[data-paragraph-index="${paragraphIndex}"]`);
        if (!trigger) return;

        let cluster = trigger.previousElementSibling;
        if (!cluster || !cluster.classList.contains('graffiti-cluster')) {
            cluster = document.createElement('div');
            cluster.className = 'graffiti-cluster';
            cluster.dataset.paragraph = paragraphIndex;
            trigger.parentNode.insertBefore(cluster, trigger);
        }

        const item = document.createElement('div');
        item.className = 'graffiti-item';
        item.innerHTML = `<img src="${imageUrl}" alt="Visitor graffiti" loading="lazy" />`;
        cluster.appendChild(item);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

**Step 3: Enqueue assets in main plugin file**

Add to `graffiti.php`:

```php
function graffiti_enqueue_assets() {
    if ( ! is_singular( array( 'post', 'page' ) ) ) {
        return;
    }

    wp_enqueue_style(
        'graffiti-style',
        GRAFFITI_PLUGIN_URL . 'assets/css/graffiti.css',
        array(),
        GRAFFITI_VERSION
    );

    wp_enqueue_script(
        'graffiti-script',
        GRAFFITI_PLUGIN_URL . 'assets/js/graffiti.js',
        array(),
        GRAFFITI_VERSION,
        true
    );

    wp_localize_script( 'graffiti-script', 'graffitiData', array(
        'restUrl' => esc_url_raw( rest_url() ),
        'postId'  => get_the_ID(),
    ) );
}
add_action( 'wp_enqueue_scripts', 'graffiti_enqueue_assets' );
```

**Step 4: Test modal opens on frontend**

Test: Visit a post on frontend, hover between paragraphs, click trigger, verify modal opens.

**Step 5: Commit**

```bash
git add assets/ graffiti.php
git commit -m "feat: add frontend assets for drawing canvas"
```

---

## Task 6: Content Filter for Rendering Graffiti

**Files:**
- Create: `includes/class-graffiti-renderer.php`
- Modify: `graffiti.php`

**Step 1: Create renderer class**

Create `includes/class-graffiti-renderer.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Graffiti_Renderer {

    public function __construct() {
        add_filter( 'the_content', array( $this, 'inject_graffiti' ), 20 );
    }

    public function inject_graffiti( $content ) {
        if ( ! is_singular( array( 'post', 'page' ) ) ) {
            return $content;
        }

        $post_id = get_the_ID();

        // Query graffiti for this post
        $graffiti_posts = get_posts( array(
            'post_type'      => 'graffiti',
            'posts_per_page' => -1,
            'meta_key'       => '_graffiti_post_id',
            'meta_value'     => $post_id,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ) );

        if ( empty( $graffiti_posts ) ) {
            return $content;
        }

        // Group graffiti by paragraph index
        $graffiti_by_paragraph = array();
        foreach ( $graffiti_posts as $graffiti ) {
            $paragraph_index = get_post_meta( $graffiti->ID, '_graffiti_paragraph_index', true );
            if ( ! isset( $graffiti_by_paragraph[ $paragraph_index ] ) ) {
                $graffiti_by_paragraph[ $paragraph_index ] = array();
            }
            $graffiti_by_paragraph[ $paragraph_index ][] = $graffiti;
        }

        // Split content by block elements
        $pattern = '/(<(?:p|h[1-6]|ul|ol|blockquote|figure)[^>]*>.*?<\/(?:p|h[1-6]|ul|ol|blockquote|figure)>)/is';
        $blocks = preg_split( $pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

        $output = '';
        $block_index = 0;

        foreach ( $blocks as $block ) {
            // Check if this is a block element
            if ( preg_match( '/^<(?:p|h[1-6]|ul|ol|blockquote|figure)/i', trim( $block ) ) ) {
                $block_index++;

                // Insert graffiti before this block if any exist
                if ( isset( $graffiti_by_paragraph[ $block_index ] ) ) {
                    $output .= $this->render_graffiti_cluster( $graffiti_by_paragraph[ $block_index ], $block_index );
                }
            }

            $output .= $block;
        }

        // Insert graffiti after last block
        $last_index = $block_index + 1;
        if ( isset( $graffiti_by_paragraph[ $last_index ] ) ) {
            $output .= $this->render_graffiti_cluster( $graffiti_by_paragraph[ $last_index ], $last_index );
        }

        return $output;
    }

    private function render_graffiti_cluster( $graffiti_posts, $paragraph_index ) {
        $html = '<div class="graffiti-cluster" data-paragraph="' . esc_attr( $paragraph_index ) . '">';

        foreach ( $graffiti_posts as $graffiti ) {
            $image_url = get_the_post_thumbnail_url( $graffiti->ID, 'full' );
            if ( $image_url ) {
                $html .= '<div class="graffiti-item">';
                $html .= '<img src="' . esc_url( $image_url ) . '" alt="Visitor graffiti" loading="lazy" />';
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        return $html;
    }
}
```

**Step 2: Include in main plugin file**

Add require to `graffiti.php`:

```php
require_once GRAFFITI_PLUGIN_DIR . 'includes/class-graffiti-renderer.php';
```

Add to `graffiti_init()`:

```php
function graffiti_init() {
    new Graffiti_Post_Type();
    new Graffiti_REST_API();
    new Graffiti_Renderer();
}
```

**Step 3: Test end-to-end**

Test: Draw and submit graffiti, reload page, verify graffiti appears in the correct position.

**Step 4: Commit**

```bash
git add includes/class-graffiti-renderer.php graffiti.php
git commit -m "feat: add content filter to render saved graffiti"
```

---

## Task 7: Admin List Table Customization

**Files:**
- Create: `includes/class-graffiti-admin.php`
- Modify: `graffiti.php`

**Step 1: Create admin class**

Create `includes/class-graffiti-admin.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Graffiti_Admin {

    public function __construct() {
        add_filter( 'manage_graffiti_posts_columns', array( $this, 'set_columns' ) );
        add_action( 'manage_graffiti_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
        add_filter( 'manage_edit-graffiti_sortable_columns', array( $this, 'sortable_columns' ) );
    }

    public function set_columns( $columns ) {
        $new_columns = array(
            'cb'              => $columns['cb'],
            'thumbnail'       => 'Preview',
            'parent_post'     => 'Parent Post',
            'paragraph'       => 'Paragraph',
            'ip_address'      => 'IP Address',
            'date'            => 'Date',
        );
        return $new_columns;
    }

    public function render_column( $column, $post_id ) {
        switch ( $column ) {
            case 'thumbnail':
                $image_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
                if ( $image_url ) {
                    echo '<img src="' . esc_url( $image_url ) . '" style="max-width: 100px; height: auto;" />';
                } else {
                    echo '—';
                }
                break;

            case 'parent_post':
                $parent_id = get_post_meta( $post_id, '_graffiti_post_id', true );
                if ( $parent_id ) {
                    $parent = get_post( $parent_id );
                    if ( $parent ) {
                        echo '<a href="' . esc_url( get_edit_post_link( $parent_id ) ) . '">' . esc_html( $parent->post_title ) . '</a>';
                    } else {
                        echo 'Deleted post';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'paragraph':
                $index = get_post_meta( $post_id, '_graffiti_paragraph_index', true );
                echo esc_html( $index !== '' ? $index : '—' );
                break;

            case 'ip_address':
                $ip = get_post_meta( $post_id, '_graffiti_ip_address', true );
                echo esc_html( $ip ? $ip : '—' );
                break;
        }
    }

    public function sortable_columns( $columns ) {
        $columns['date'] = 'date';
        return $columns;
    }
}
```

**Step 2: Include in main plugin file**

Add require to `graffiti.php`:

```php
require_once GRAFFITI_PLUGIN_DIR . 'includes/class-graffiti-admin.php';
```

Add to `graffiti_init()`:

```php
function graffiti_init() {
    new Graffiti_Post_Type();
    new Graffiti_REST_API();
    new Graffiti_Renderer();

    if ( is_admin() ) {
        new Graffiti_Admin();
    }
}
```

**Step 3: Test admin list table**

Test: Go to Graffiti in admin, verify columns show thumbnail, parent post, paragraph, IP, date.

**Step 4: Commit**

```bash
git add includes/class-graffiti-admin.php graffiti.php
git commit -m "feat: customize admin list table columns"
```

---

## Task 8: Settings Page

**Files:**
- Create: `includes/class-graffiti-settings.php`
- Modify: `graffiti.php`

**Step 1: Create settings class**

Create `includes/class-graffiti-settings.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Graffiti_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=graffiti',
            'Graffiti Settings',
            'Settings',
            'manage_options',
            'graffiti-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'graffiti_settings', 'graffiti_enabled', array(
            'type'              => 'boolean',
            'default'           => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ) );

        add_settings_section(
            'graffiti_general',
            'General Settings',
            null,
            'graffiti-settings'
        );

        add_settings_field(
            'graffiti_enabled',
            'Enable Graffiti',
            array( $this, 'render_enabled_field' ),
            'graffiti-settings',
            'graffiti_general'
        );
    }

    public function render_enabled_field() {
        $enabled = get_option( 'graffiti_enabled', true );
        ?>
        <label>
            <input type="checkbox" name="graffiti_enabled" value="1" <?php checked( $enabled ); ?> />
            Allow visitors to leave graffiti on posts and pages
        </label>
        <?php
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Graffiti Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'graffiti_settings' );
                do_settings_sections( 'graffiti-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
```

**Step 2: Include in main plugin file**

Add require to `graffiti.php`:

```php
require_once GRAFFITI_PLUGIN_DIR . 'includes/class-graffiti-settings.php';
```

Add to `graffiti_init()`:

```php
function graffiti_init() {
    new Graffiti_Post_Type();
    new Graffiti_REST_API();
    new Graffiti_Renderer();

    if ( is_admin() ) {
        new Graffiti_Admin();
        new Graffiti_Settings();
    }
}
```

**Step 3: Check enabled setting before rendering**

Modify `graffiti_enqueue_assets()` in `graffiti.php`:

```php
function graffiti_enqueue_assets() {
    if ( ! is_singular( array( 'post', 'page' ) ) ) {
        return;
    }

    if ( ! get_option( 'graffiti_enabled', true ) ) {
        return;
    }

    // ... rest of function
}
```

Also modify `Graffiti_Renderer::inject_graffiti()` in `includes/class-graffiti-renderer.php` to check the setting:

```php
public function inject_graffiti( $content ) {
    if ( ! is_singular( array( 'post', 'page' ) ) ) {
        return $content;
    }

    if ( ! get_option( 'graffiti_enabled', true ) ) {
        return $content;
    }

    // ... rest of method
}
```

**Step 4: Test settings page**

Test: Go to Graffiti > Settings, toggle setting off, verify graffiti doesn't appear on frontend.

**Step 5: Commit**

```bash
git add includes/class-graffiti-settings.php includes/class-graffiti-renderer.php graffiti.php
git commit -m "feat: add settings page with enable/disable toggle"
```

---

## Task 9: Final Testing and Cleanup

**Step 1: Full end-to-end test**

Test checklist:
- [ ] Plugin activates without errors
- [ ] Graffiti menu appears in admin with art icon
- [ ] Hover triggers appear between paragraphs on posts/pages
- [ ] Drawing modal opens with canvas, colors, eraser
- [ ] Drawing saves and appears on page immediately
- [ ] Page reload shows saved graffiti
- [ ] Multiple graffiti in same gap stack vertically
- [ ] Admin list table shows all graffiti with metadata
- [ ] Graffiti can be deleted from admin
- [ ] Settings toggle disables graffiti site-wide

**Step 2: Code review**

- Verify all files have proper PHP opening tags and ABSPATH checks
- Verify all output is properly escaped
- Verify all input is sanitized

**Step 3: Final commit**

```bash
git status
git add -A
git commit -m "chore: final cleanup and testing"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Plugin scaffolding | `graffiti.php` |
| 2 | Custom post type | `includes/class-graffiti-post-type.php` |
| 3 | Post meta | `includes/class-graffiti-post-type.php` |
| 4 | REST API | `includes/class-graffiti-rest-api.php` |
| 5 | Frontend assets | `assets/css/graffiti.css`, `assets/js/graffiti.js` |
| 6 | Content renderer | `includes/class-graffiti-renderer.php` |
| 7 | Admin columns | `includes/class-graffiti-admin.php` |
| 8 | Settings page | `includes/class-graffiti-settings.php` |
| 9 | Testing | All files |
