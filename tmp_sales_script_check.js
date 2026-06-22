
    (function () {
        const productsRaw = "X";
        const productBranchesRaw = "X";

        const products = Array.isArray(productsRaw) ? productsRaw : Object.values(productsRaw || {});
        const productBranches = Array.isArray(productBranchesRaw) ? productBranchesRaw : Object.values(productBranchesRaw || {});

        const priceByProductId = new Map();
        productBranches.forEach((pb) => {
            const pid = Number(pb.product_id ?? pb.id);
            if (!Number.isNaN(pid)) {
                priceByProductId.set(pid, Number(pb.price ?? 0));
            }
        });
        let selectedCategory = 'General';

        function getProductCategory(prod) {
            const value = (prod && prod.category) ? String(prod.category).trim() : '';
            return value !== '' ? value : 'Sin categoria';
        }

        function getCategories() {
            const unique = new Set();
            products.forEach((prod) => unique.add(getProductCategory(prod)));
            return ['General', ...Array.from(unique).sort((a, b) => a.localeCompare(b))];
        }

        const ACTIVE_SALE_KEY_STORAGE = 'restaurantActiveSaleKey';
        let db = JSON.parse(localStorage.getItem('restaurantDB') || '{}');
        let activeKey = localStorage.getItem(ACTIVE_SALE_KEY_STORAGE);

        if (!activeKey || !db[activeKey] || db[activeKey]?.status === 'completed') {
            activeKey = `sale-${Date.now()}`;
            localStorage.setItem(ACTIVE_SALE_KEY_STORAGE, activeKey);
        }

        let currentSale = db[activeKey] || {
            id: Date.now(),
            clientName: 'Publico General',
            status: 'in_progress',
            items: [],
        };

        db[activeKey] = currentSale;
        localStorage.setItem('restaurantDB', JSON.stringify(db));

        function getImageUrl(imgUrl) {
            if (imgUrl && String(imgUrl).trim() !== '') return imgUrl;
            return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iNDAwIj48cmVjdCBmaWxsPSIjZTdlOWViIiB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTgiIGZpbGw9IiM5Y2EzYWYiPlNpbiBpbWFnZW48L3RleHQ+PC9zdmc+';
        }

        function saveDB() {
            db[activeKey] = currentSale;
            localStorage.setItem('restaurantDB', JSON.stringify(db));
        }

        function renderProducts() {
            const grid = document.getElementById('products-grid');
            if (!grid) return;
            grid.innerHTML = '';

            let rendered = 0;

            products.forEach((prod) => {
                const productId = Number(prod.id);
                const price = priceByProductId.get(productId);
                const category = getProductCategory(prod);

                // Mostrar solo productos asignados a la sucursal actual
                if (typeof price === 'undefined') return;
                if (selectedCategory !== 'General' && category !== selectedCategory) return;

                const el = document.createElement('div');
                el.className = 'group cursor-pointer transition-transform duration-200 hover:scale-105';
                el.addEventListener('click', function () {
                    addToCart(prod, price);
                });

                const safeName = prod.name || 'Sin nombre';
                const safeCategory = category;

                el.innerHTML = `
                    <div class="rounded-lg overflow-hidden p-3 dark:bg-slate-800/40 shadow-md hover:shadow-xl border border-gray-300 dark:border-slate-700/50 hover:border-blue-500 dark:hover:border-blue-500 hover:shadow-blue-500/10 transition-all duration-200 hover:-translate-y-1 backdrop-blur-sm">
                        <div class="relative aspect-square overflow-hidden dark:bg-slate-700/30 rounded-lg border border-gray-300 dark:border-slate-600/30 shadow-sm">
                            <img src="${getImageUrl(prod.img)}" alt="${safeName}" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110" loading="lazy" onerror="this.onerror=null; this.src='${getImageUrl(null)}'">
                            <span class="absolute top-3 right-3 z-10">
                                <span class="px-2.5 py-1 bg-blue-600 dark:bg-blue-500 rounded-lg text-sm font-bold shadow-lg shadow-blue-500/40 dark:shadow-blue-500/20 backdrop-blur-sm border border-blue-400/50 dark:border-blue-400/30 text-white">
                                    $${Number(price).toFixed(2)}
                                </span>
                            </span>
                        </div>
                        <div class="mt-3 flex flex-col gap-1">
                            <h4 class="font-semibold text-gray-900 dark:text-white text-sm line-clamp-2 leading-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">${safeName}</h4>
                            <h6 class="text-xs text-gray-600 dark:text-gray-400">${safeCategory}</h6>
                        </div>
                    </div>
                `;

                grid.appendChild(el);
                rendered++;
            });

            if (rendered === 0) {
                grid.innerHTML = '<div class="col-span-full text-center text-gray-500 py-8">No hay productos disponibles para esta sucursal.</div>';
            }
        }

        function renderCategoryFilters() {
            const container = document.getElementById('category-filters');
            const label = document.getElementById('selected-category-label');
            if (!container) return;

            container.innerHTML = '';
            const categories = getCategories();

            categories.forEach((category) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'px-3 py-1.5 rounded-lg border text-xs font-semibold transition';
                const isActive = category === selectedCategory;

                if (isActive) {
                    button.className += ' bg-blue-600 text-white border-blue-600';
                } else {
                    button.className += ' bg-white text-slate-700 border-gray-300 hover:border-blue-400 hover:text-blue-600';
                }

                button.textContent = category;
                button.addEventListener('click', () => {
                    selectedCategory = category;
                    if (label) label.textContent = selectedCategory;
                    renderCategoryFilters();
                    renderProducts();
                });

                container.appendChild(button);
            });

            if (label) label.textContent = selectedCategory;
        }

        function addToCart(prod, price) {
            const productId = Number(prod.id);
            if (Number.isNaN(productId)) return;

            if (!Array.isArray(currentSale.items)) currentSale.items = [];

            const existing = currentSale.items.find((i) => Number(i.pId) === productId);
            if (existing) {
                existing.qty += 1;
            } else {
                currentSale.items.push({
                    pId: productId,
                    name: prod.name || '',
                    qty: 1,
                    price: Number(price) || 0,
                    note: '',
                });
            }

            saveDB();
            renderTicket();
            showNotification(prod.name || 'Producto');
        }

        function updateQty(index, delta) {
            if (!currentSale.items[index]) return;
            currentSale.items[index].qty += delta;
            if (currentSale.items[index].qty <= 0) currentSale.items.splice(index, 1);
            saveDB();
            renderTicket();
        }

        function toggleNoteInput(index) {
            const box = document.getElementById(`note-box-${index}`);
            if (box) box.classList.toggle('hidden');
        }

        function saveNote(index, value) {
            if (!currentSale.items[index]) return;
            currentSale.items[index].note = value;
            saveDB();
        }

        function renderTicket() {
            const container = document.getElementById('cart-container');
            if (!container) return;

            container.innerHTML = '';
            let subtotal = 0;

            if (!currentSale.items || currentSale.items.length === 0) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-48 text-gray-300 dark:text-gray-600 opacity-70">
                        <div class="w-16 h-16 rounded-full dark:bg-slate-800 flex items-center justify-center mb-3">
                            <i class="fas fa-shopping-cart text-2xl dark:text-gray-600"></i>
                        </div>
                        <p class="font-semibold text-sm text-gray-500 dark:text-gray-500">Sin productos</p>
                        <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">Selecciona productos del menu</p>
                    </div>
                `;
            } else {
                currentSale.items.forEach((item, index) => {
                    const prod = products.find((p) => Number(p.id) === Number(item.pId));
                    if (!prod) return;

                    const itemPrice = Number(item.price) || 0;
                    const itemQty = Number(item.qty) || 0;
                    const itemTotal = itemPrice * itemQty;
                    subtotal += itemTotal;

                    const hasNote = !!(item.note && String(item.note).trim() !== '');

                    const row = document.createElement('div');
                    row.className = 'py-2 px-2 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700/50 rounded-lg p-1.5 shadow-sm mb-2';
                    row.innerHTML = `
                        <div class="flex gap-2 pl-2">
                            <div class="relative shrink-0">
                                <img src="${getImageUrl(prod.img)}" alt="${prod.name}" class="h-12 w-12 rounded-lg object-cover bg-gray-100 dark:bg-slate-700 ring-1 ring-gray-200/50 dark:ring-slate-600/50 shadow-sm">
                            </div>
                            <div class="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                                <div class="flex justify-between items-start gap-2">
                                    <div class="flex-1 min-w-0">
                                        <h5 class="font-semibold text-slate-900 dark:text-white text-xs truncate leading-tight">${prod.name}</h5>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">$${itemPrice.toFixed(2)} c/u</p>
                                    </div>
                                    <div class="shrink-0">
                                        <span class="font-bold text-blue-600 dark:text-blue-400 text-sm">$${itemTotal.toFixed(2)}</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <button onclick="toggleNoteInput(${index})" class="text-[10px] flex items-center gap-1 ${hasNote ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'}">
                                        <i class="fas fa-sticky-note text-[8px]"></i> ${hasNote ? 'Nota' : '+ Nota'}
                                    </button>
                                    <div class="flex items-center gap-0.5 dark:bg-slate-700 rounded border border-gray-200 dark:border-slate-600 shadow-sm">
                                        <button onclick="updateQty(${index}, -1)" class="w-6 h-6 flex items-center justify-center text-gray-700 dark:text-white hover:text-red-600"><i class="ri-subtract-line text-xs"></i></button>
                                        <span class="text-xs font-bold text-slate-900 dark:text-white w-6 text-center">${itemQty}</span>
                                        <button onclick="updateQty(${index}, 1)" class="w-6 h-6 flex items-center justify-center text-gray-700 dark:text-white hover:text-blue-600"><i class="ri-add-line text-xs"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="note-box-${index}" class="${hasNote ? '' : 'hidden'} mt-1.5 ml-2 mr-2">
                            <div class="bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-900/20 dark:to-yellow-900/20 border-l-2 border-amber-400 dark:border-amber-500 rounded p-1.5">
                                <input type="text" value="${(item.note || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}" oninput="saveNote(${index}, this.value)" placeholder="Ej: Sin cebolla, extra queso..." class="w-full text-[10px] bg-transparent border-none text-slate-700 dark:text-gray-200 focus:outline-none placeholder-gray-400 dark:placeholder-gray-500 font-medium">
                            </div>
                        </div>
                    `;
                    container.appendChild(row);
                });
            }

            const tax = subtotal * 0.10;
            document.getElementById('ticket-subtotal').innerText = `$${subtotal.toFixed(2)}`;
            document.getElementById('ticket-tax').innerText = `$${tax.toFixed(2)}`;
            document.getElementById('ticket-total').innerText = `$${(subtotal + tax).toFixed(2)}`;

            const cartCountBadge = document.getElementById('cart-count-badge');
            const totalItems = (currentSale.items || []).reduce((sum, item) => sum + (Number(item.qty) || 0), 0);
            if (cartCountBadge) cartCountBadge.textContent = String(totalItems);
        }

        function goToChargeView() {
            if (!currentSale.items || currentSale.items.length === 0) {
                showEmptyCartNotification();
                return;
            }
            saveDB();
            window.location.href = "X";
        }

        function goBack() {
            if (!currentSale.items || currentSale.items.length === 0) {
                currentSale.items = [];
                currentSale.total = 0;
                saveDB();
                localStorage.removeItem(ACTIVE_SALE_KEY_STORAGE);
                window.location.href = "X";
                return;
            }

            const saleData = {
                items: currentSale.items.map(item => ({
                    pId: item.pId,
                    qty: Number(item.qty),
                    price: Number(item.price),
                    note: item.note || ''
                })),
                notes: 'Venta guardada como borrador - pendiente de pago'
            };

            fetch("X"), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(saleData)
            })
            .finally(() => {
                window.location.href = "X";
            });
        }

        function showNotification(productName) {
            const notification = document.getElementById('add-to-cart-notification');
            const productNameEl = document.getElementById('notification-product-name');
            if (!notification || !productNameEl) return;
            productNameEl.textContent = productName;
            notification.classList.add('notification-show');
            setTimeout(hideNotification, 1600);
        }

        function hideNotification() {
            const notification = document.getElementById('add-to-cart-notification');
            if (!notification) return;
            notification.classList.remove('notification-show');
        }

        init();

        function init() {
            const clientNameEl = document.getElementById('pos-client-name');
            if (clientNameEl) clientNameEl.innerText = currentSale.clientName || 'Publico General';
            renderCategoryFilters();
            renderProducts();
            renderTicket();
        }

        window.goBack = goBack;
        window.getImageUrl = getImageUrl;
        window.updateQty = updateQty;
        window.toggleNoteInput = toggleNoteInput;
        window.saveNote = saveNote;
        window.goToChargeView = goToChargeView;
        window.hideNotification = hideNotification;
    })();

