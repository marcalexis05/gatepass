<?php
$system_name = get_setting('system_name', 'Concentrix Gatepass');
?>
    </main>

    <!-- Custom Premium Alert Modal -->
    <div id="custom-alert-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm transition-all duration-300">
        <div class="w-full max-w-[380px] bg-[#01222A] rounded-[28px] border border-brand-teal/30 shadow-2xl p-8 text-center transform scale-95 transition-transform duration-300 relative overflow-hidden">
            <!-- Accent Glow background decoration -->
            <div class="absolute -top-12 -left-12 w-24 h-24 bg-brand-teal/10 rounded-full blur-xl pointer-events-none"></div>
            
            <div class="mx-auto w-14 h-14 rounded-full bg-brand-teal/10 border border-brand-teal/20 flex items-center justify-center text-brand-teal text-xl mb-4">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <h3 class="text-lg font-bold text-white mb-2 font-display" id="custom-alert-title">Verification Alert</h3>
            <p class="text-slate-350 text-xs sm:text-sm mb-6 px-3 leading-relaxed" id="custom-alert-message">Message goes here.</p>
            <button type="button" id="custom-alert-close-btn" class="px-8 py-2.5 bg-brand-teal hover:bg-[#1fd4be] active:scale-[0.98] text-[#000f13] font-bold text-xs rounded-xl transition-all shadow-lg shadow-brand-teal/10">
                <span>OK</span>
            </button>
        </div>
    </div>

    <script>
    (function() {
        const modal = document.getElementById('custom-alert-modal');
        const msgEl = document.getElementById('custom-alert-message');
        const titleEl = document.getElementById('custom-alert-title');
        const closeBtn = document.getElementById('custom-alert-close-btn');

        if (modal && closeBtn) {
            // Store original alert
            const originalAlert = window.alert;

            // Override alert
            window.alert = function(message, title = "Verification Alert") {
                if (msgEl) msgEl.textContent = message;
                if (titleEl) titleEl.textContent = title;
                
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                
                const card = modal.querySelector('.transform');
                if (card) {
                    setTimeout(() => {
                        card.classList.remove('scale-95');
                        card.classList.add('scale-100');
                    }, 10);
                }
            };

            closeBtn.addEventListener('click', () => {
                const card = modal.querySelector('.transform');
                if (card) {
                    card.classList.remove('scale-100');
                    card.classList.add('scale-95');
                }
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }, 150);
            });
        }

        // Full screen signature setup for all canvas signature pads
        document.querySelectorAll('canvas').forEach(canvas => {
            const parent = canvas.parentNode;
            if (parent && parent.classList.contains('relative') && (canvas.id.includes('sig') || canvas.id.includes('signature'))) {
                // Pre-resolve and link the associated hidden input directly to the canvas object
                const parentWrapper = canvas.closest('.space-y-2, .space-y-4, .space-y-1.5') || parent;
                let hiddenInput = parentWrapper ? parentWrapper.querySelector('input[type="hidden"]') : null;
                if (!hiddenInput && parent.parentNode) {
                    hiddenInput = parent.parentNode.querySelector('input[type="hidden"]');
                }
                if (!hiddenInput) {
                    const form = canvas.closest('form');
                    if (form) {
                        hiddenInput = Array.from(form.querySelectorAll('input[type="hidden"]')).find(input => 
                            input.id.includes('sig') || input.id.includes('signature') || 
                            input.name.includes('sig') || input.name.includes('signature')
                        );
                    }
                }
                canvas.associatedHiddenInput = hiddenInput;

                const fsBtn = document.createElement('button');
                fsBtn.type = 'button';
                fsBtn.className = 'absolute bottom-2 left-2 px-3 py-1 bg-slate-850 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-800 shadow transition-all flex items-center space-x-1 z-10';
                fsBtn.innerHTML = '<i class="fa-solid fa-expand mr-1"></i> Full Screen';
                parent.appendChild(fsBtn);
                
                fsBtn.addEventListener('click', () => {
                    openFullscreenSignature(canvas);
                });
            }
        });

        function openFullscreenSignature(targetCanvas) {
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 z-[120] bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 transition-all duration-300';
            
            const card = document.createElement('div');
            card.className = 'w-full max-w-lg bg-[#01222A] border border-brand-teal/30 rounded-[28px] shadow-2xl p-6 relative flex flex-col transform scale-95 transition-transform duration-300';
            
            const header = document.createElement('div');
            header.className = 'flex items-center justify-between mb-4 pb-2 border-b border-slate-800';
            header.innerHTML = `
                <span class="text-xs font-bold text-brand-teal tracking-widest uppercase font-display">Sign Here</span>
                <div class="flex items-center space-x-2">
                    <button type="button" id="fs-clear-btn" class="px-3 py-1.5 bg-slate-800/80 hover:bg-slate-700 text-slate-350 text-xs font-bold rounded-lg border border-slate-700/50 transition-all">
                        Clear
                    </button>
                    <button type="button" id="fs-close-btn" class="px-3 py-1.5 bg-rose-950/20 hover:bg-rose-900/30 text-rose-400 text-xs font-bold rounded-lg border border-rose-900/20 transition-all">
                        Cancel
                    </button>
                </div>
            `;
            
            const canvasContainer = document.createElement('div');
            canvasContainer.className = 'w-full h-64 border border-slate-800 rounded-2xl overflow-hidden bg-slate-950 relative mb-4';
            
            const canvas = document.createElement('canvas');
            canvas.className = 'w-full h-full cursor-crosshair bg-slate-950';
            canvasContainer.appendChild(canvas);
            
            const ctx = canvas.getContext('2d');
            
            const doneBtn = document.createElement('button');
            doneBtn.type = 'button';
            doneBtn.id = 'fs-done-btn';
            doneBtn.className = 'w-full py-2.5 bg-brand-teal hover:bg-[#1fd4be] active:scale-[0.98] text-[#000f13] font-bold text-xs rounded-xl shadow-lg shadow-brand-teal/10 transition-all';
            doneBtn.textContent = 'Save Signature';
            
            card.appendChild(header);
            card.appendChild(canvasContainer);
            card.appendChild(doneBtn);
            overlay.appendChild(card);
            document.body.appendChild(overlay);
            
            // Trigger animation
            setTimeout(() => {
                card.classList.remove('scale-95');
                card.classList.add('scale-100');
            }, 10);
            
            document.body.style.overflow = 'hidden';
            
            function drawCentered(source, ctx, destWidth, destHeight) {
                const sourceWidth = source.naturalWidth || source.width;
                const sourceHeight = source.naturalHeight || source.height;
                if (!sourceWidth || !sourceHeight) return;

                const sourceRatio = sourceWidth / sourceHeight;
                const destRatio = destWidth / destHeight;
                
                let drawWidth, drawHeight, x, y;
                if (sourceRatio > destRatio) {
                    drawWidth = destWidth;
                    drawHeight = destWidth / sourceRatio;
                    x = 0;
                    y = (destHeight - drawHeight) / 2;
                } else {
                    drawHeight = destHeight;
                    drawWidth = destHeight * sourceRatio;
                    x = (destWidth - drawWidth) / 2;
                    y = 0;
                }
                
                ctx.drawImage(source, x, y, drawWidth, drawHeight);
            }

            function resizeFsCanvas() {
                canvas.width = canvasContainer.clientWidth;
                canvas.height = canvasContainer.clientHeight;
                ctx.lineWidth = 4;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#ffffff';
                
                // Copy existing signature from targetCanvas if it exists
                const hiddenInput = targetCanvas.associatedHiddenInput;
                if (hiddenInput && hiddenInput.value && hiddenInput.value.startsWith('data:image/')) {
                    const img = new Image();
                    img.onload = () => {
                        drawCentered(img, ctx, canvas.width, canvas.height);
                    };
                    img.src = hiddenInput.value;
                }
            }
            
            setTimeout(resizeFsCanvas, 100);
            
            let drawing = false;
            
            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            function startDrawing(e) {
                drawing = true;
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
                e.preventDefault();
            }

            // Bind draw movement
            function draw(e) {
                if (!drawing) return;
                const pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                e.preventDefault();
            }

            function stopDrawing() {
                drawing = false;
            }

            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseleave', stopDrawing);

            canvas.addEventListener('touchstart', startDrawing, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', stopDrawing);
            
            overlay.querySelector('#fs-clear-btn').addEventListener('click', () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            });
            
            overlay.querySelector('#fs-close-btn').addEventListener('click', () => {
                closeOverlay();
            });
            
            function trimCanvas(sourceCanvas) {
                const sourceCtx = sourceCanvas.getContext('2d');
                const copy = document.createElement('canvas');
                const pixels = sourceCtx.getImageData(0, 0, sourceCanvas.width, sourceCanvas.height);
                const l = pixels.data.length;
                let bound = {
                    top: null,
                    left: null,
                    right: null,
                    bottom: null
                };
                
                for (let i = 0; i < l; i += 4) {
                    if (pixels.data[i + 3] !== 0) {
                        const x = (i / 4) % sourceCanvas.width;
                        const y = Math.floor((i / 4) / sourceCanvas.width);
                        
                        if (bound.top === null) bound.top = y;
                        if (bound.left === null) bound.left = x;
                        else if (x < bound.left) bound.left = x;
                        
                        if (bound.right === null) bound.right = x;
                        else if (x > bound.right) bound.right = x;
                        
                        if (bound.bottom === null) bound.bottom = y;
                        else if (y > bound.bottom) bound.bottom = y;
                    }
                }
                
                if (bound.top === null) return sourceCanvas;
                
                const trimHeight = bound.bottom - bound.top + 1;
                const trimWidth = bound.right - bound.left + 1;
                const padding = 10;
                
                copy.width = trimWidth + (padding * 2);
                copy.height = trimHeight + (padding * 2);
                
                const copyCtx = copy.getContext('2d');
                copyCtx.drawImage(
                    sourceCanvas,
                    bound.left, bound.top, trimWidth, trimHeight,
                    padding, padding, trimWidth, trimHeight
                );
                
                return copy;
            }

            overlay.querySelector('#fs-done-btn').addEventListener('click', () => {
                const trimmedCanvas = trimCanvas(canvas);
                
                const targetCtx = targetCanvas.getContext('2d');
                targetCtx.clearRect(0, 0, targetCanvas.width, targetCanvas.height);
                drawCentered(trimmedCanvas, targetCtx, targetCanvas.width, targetCanvas.height);
                
                const hiddenInput = targetCanvas.associatedHiddenInput;
                if (hiddenInput) {
                    hiddenInput.value = targetCanvas.toDataURL();
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                
                closeOverlay();
            });
            
            // Allow closing by clicking on backdrop
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    closeOverlay();
                }
            });
            
            function closeOverlay() {
                card.classList.remove('scale-100');
                card.classList.add('scale-95');
                setTimeout(() => {
                    document.body.style.overflow = '';
                    overlay.remove();
                }, 150);
            }
        }
    })();
    </script>
    
    <!-- Custom Scripts -->
    <script src="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../assets/js/main.js' : 'assets/js/main.js'; ?>?v=<?php echo time(); ?>"></script>
</body>
</html>
