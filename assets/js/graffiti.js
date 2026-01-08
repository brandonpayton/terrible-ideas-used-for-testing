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
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = 'Visitor graffiti';
        img.loading = 'lazy';
        item.appendChild(img);
        cluster.appendChild(item);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
