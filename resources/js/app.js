import './bootstrap';

const cartKey = 'techpulse-cart';

function readCart() {
	try {
		const cart = JSON.parse(localStorage.getItem(cartKey) || '[]');
		return Array.isArray(cart) ? cart.filter((item) => item && typeof item.id === 'string' && Number.isFinite(Number(item.price)) && Number(item.quantity) > 0) : [];
	} catch {
		return [];
	}
}

function formatPrice(value) {
	return `Rs. ${Number(value).toLocaleString('en-PK')}`;
}

function updateCartCount(cart = readCart()) {
	const count = cart.reduce((sum, item) => sum + Number(item.quantity), 0);
	document.querySelectorAll('[data-cart-count]').forEach((node) => {
		node.textContent = count;
		node.hidden = count === 0;
	});
}

function renderCart() {
	const container = document.querySelector('[data-cart-items]');
	const cart = readCart();
	if (!container) {
		updateCartCount(cart);
		return;
	}

	const subtotal = cart.reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0);
	const shipping = subtotal === 0 || subtotal >= 2999 ? 0 : 250;
	const count = cart.reduce((sum, item) => sum + Number(item.quantity), 0);

	if (cart.length === 0) {
		const empty = document.createElement('div');
		empty.className = 'empty-cart';
		const message = document.createElement('p');
		message.textContent = 'Your cart is waiting for something good.';
		const link = document.createElement('a');
		link.className = 'button button-primary';
		link.href = '/shop';
		link.textContent = 'Browse the catalog';
		empty.append(message, link);
		container.replaceChildren(empty);
	} else {
		const rows = cart.map((item) => {
			const row = document.createElement('article');
			row.className = 'cart-item';
			const art = document.createElement('div');
			art.className = 'cart-item-art';
			const icon = document.createElement('span');
			icon.className = 'material-symbols-outlined';
			icon.textContent = item.icon || 'devices_other';
			art.append(icon);

			const details = document.createElement('div');
			const title = document.createElement('h3');
			title.textContent = item.title || 'TechPulse product';
			const note = document.createElement('small');
			note.textContent = 'Official warranty - In stock';
			details.append(title, note);

			const end = document.createElement('div');
			end.className = 'cart-item-end';
			const price = document.createElement('strong');
			price.textContent = formatPrice(Number(item.price) * Number(item.quantity));
			const tools = document.createElement('div');
			tools.className = 'cart-item-tools';
			for (const [action, label] of [['decrease', '-'], ['increase', '+']]) {
				const button = document.createElement('button');
				button.type = 'button';
				button.dataset.cartAction = action;
				button.dataset.id = item.id;
				button.setAttribute('aria-label', `${action} quantity`);
				button.textContent = label;
				tools.append(button);
				if (action === 'decrease') {
					const quantity = document.createElement('span');
					quantity.textContent = item.quantity;
					tools.append(quantity);
				}
			}
			const remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'remove-item';
			remove.dataset.cartAction = 'remove';
			remove.dataset.id = item.id;
			remove.setAttribute('aria-label', `Remove ${item.title || 'product'}`);
			remove.textContent = 'Remove';
			tools.append(remove);
			end.append(price, tools);
			row.append(art, details, end);
			return row;
		});
		container.replaceChildren(...rows);
	}

	document.querySelectorAll('[data-subtotal]').forEach((node) => node.textContent = formatPrice(subtotal));
	document.querySelectorAll('[data-shipping]').forEach((node) => node.textContent = shipping ? formatPrice(shipping) : 'Free');
	document.querySelectorAll('[data-total]').forEach((node) => node.textContent = formatPrice(subtotal + shipping));
	document.querySelectorAll('[data-cart-summary-count]').forEach((node) => node.textContent = `${count} item${count === 1 ? '' : 's'}`);
	document.querySelectorAll('[data-checkout-submit]').forEach((node) => node.disabled = cart.length === 0);
	updateCartCount(cart);
}

function saveCart(cart) {
	localStorage.setItem(cartKey, JSON.stringify(cart));
	renderCart();
}

function showToast(message) {
	const toast = document.querySelector('[data-toast]');
	if (!toast) return;
	toast.querySelector('[data-toast-message]').textContent = message;
	toast.classList.add('visible');
	window.clearTimeout(showToast.timeout);
	showToast.timeout = window.setTimeout(() => toast.classList.remove('visible'), 2500);
}

function applyProductFilters() {
	const category = document.querySelector('[data-category-filter].selected')?.dataset.categoryFilter || 'all';
	const searchTerm = (new URLSearchParams(window.location.search).get('q') || '').trim().toLowerCase();
	const requireStock = document.querySelector('[data-stock-filter]')?.checked || false;
	const requireSale = document.querySelector('[data-sale-filter]')?.checked || false;
	const priceLimit = document.querySelector('[data-price-filter]:checked')?.value;

	document.querySelectorAll('[data-product-title]').forEach((card) => {
		const matchesSearch = `${card.dataset.productTitle} ${card.dataset.productCategory}`.toLowerCase().includes(searchTerm);
		const matchesCategory = category === 'all' || card.dataset.productCategory === category;
		const matchesStock = !requireStock || card.dataset.inStock === 'true';
		const matchesSale = !requireSale || card.dataset.onSale === 'true';
		const matchesPrice = priceLimit !== 'under-20000' || Number(card.dataset.productPrice) < 20000;
		card.hidden = !(matchesSearch && matchesCategory && matchesStock && matchesSale && matchesPrice);
	});
}

function sortProducts(sortOrder) {
	const grid = document.querySelector('.shop-grid');
	if (!grid || sortOrder === 'featured') return;
	const cards = [...grid.querySelectorAll('.product-card')];
	cards.sort((left, right) => {
		const difference = Number(left.dataset.productPrice) - Number(right.dataset.productPrice);
		return sortOrder === 'price-desc' ? -difference : difference;
	});
	grid.append(...cards);
}

document.addEventListener('click', (event) => {
	const addButton = event.target.closest('[data-add-cart]');
	if (addButton) {
		const cart = readCart();
		const existing = cart.find((item) => item.id === addButton.dataset.id);
		if (existing) existing.quantity = Number(existing.quantity) + Number(addButton.dataset.quantity || 1);
		else cart.push({
			id: addButton.dataset.id,
			title: addButton.dataset.title,
			price: Number(addButton.dataset.price),
			quantity: Number(addButton.dataset.quantity || 1),
			icon: addButton.dataset.icon || 'devices_other',
		});
		saveCart(cart);
		showToast(`${addButton.dataset.title} added to your cart`);
		return;
	}

	const cartAction = event.target.closest('[data-cart-action]');
	if (cartAction) {
		const cart = readCart();
		const item = cart.find((entry) => entry.id === cartAction.dataset.id);
		if (!item) return;
		if (cartAction.dataset.cartAction === 'remove') saveCart(cart.filter((entry) => entry.id !== item.id));
		else {
			item.quantity = Number(item.quantity) + (cartAction.dataset.cartAction === 'increase' ? 1 : -1);
			saveCart(cart.filter((entry) => item.quantity > 0 || entry.id !== item.id));
		}
		return;
	}

	const filter = event.target.closest('[data-category-filter]');
	if (filter) {
		document.querySelectorAll('[data-category-filter]').forEach((button) => button.classList.remove('selected'));
		filter.classList.add('selected');
		applyProductFilters();
	}
});

document.addEventListener('change', (event) => {
	if (event.target.matches('[data-stock-filter], [data-sale-filter], [data-price-filter]')) applyProductFilters();
	if (event.target.matches('.sort-select')) sortProducts(event.target.value);
});

document.addEventListener('submit', (event) => {
	if (!event.target.matches('[data-checkout-form]')) return;
	event.preventDefault();
	if (!event.target.reportValidity()) return;
	localStorage.removeItem(cartKey);
	renderCart();
	const notice = document.querySelector('[data-checkout-notice]');
	notice.textContent = 'Demo checkout submitted. This storefront is not connected to a live payment or order service.';
	notice.classList.add('visible');
	event.target.reset();
});

const params = new URLSearchParams(window.location.search);
if (params.has('q')) {
	const searchInput = document.querySelector('[name="q"]');
	if (searchInput) searchInput.value = params.get('q');
}
applyProductFilters();

document.querySelectorAll('[data-quantity-control]').forEach((control) => {
	const output = control.querySelector('output');
	control.addEventListener('click', (event) => {
		const button = event.target.closest('button');
		if (!button) return;
		const quantity = Math.max(1, Number(output.value || output.textContent) + Number(button.dataset.step));
		output.value = quantity;
		output.textContent = quantity;
		document.querySelectorAll('[data-product-add]').forEach((addButton) => addButton.dataset.quantity = quantity);
	});
});

renderCart();
