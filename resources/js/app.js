import './bootstrap';

function formatPrice(value) {
	return `Rs. ${Number(value).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function updateCartCount(count) {
	const quantity = Math.max(0, Number(count) || 0);
	document.querySelectorAll('[data-cart-count]').forEach((node) => {
		node.textContent = quantity;
		node.hidden = quantity === 0;
	});
}

function showToast(message) {
	const toast = document.querySelector('[data-toast]');
	if (!toast) return;
	toast.querySelector('[data-toast-message]').textContent = message;
	toast.classList.add('visible');
	window.clearTimeout(showToast.timeout);
	showToast.timeout = window.setTimeout(() => toast.classList.remove('visible'), 2800);
}

async function submitCartForm(form, event, mutation = false) {
	const submitter = event.submitter;
	const data = new FormData(form);
	if (submitter?.name) data.append(submitter.name, submitter.value);

	const methodField = form.querySelector('input[name="_method"]');
	const method = methodField?.value || form.method || 'POST';

	try {
		const response = await fetch(form.action, {
			method: method.toUpperCase(),
			body: data,
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Accept': 'application/json',
			},
		});
		const payload = await response.json().catch(() => ({}));

		if (!response.ok) {
			const firstError = Object.values(payload.errors || {}).flat()[0];
			showToast(firstError || payload.message || 'Could not update your cart. Please try again.');
			return;
		}

		if (payload.cart_count !== undefined) updateCartCount(payload.cart_count);

		if (mutation) {
			window.location.reload();
			return;
		}

		if (submitter?.name === 'buy_now') {
			window.location.assign(form.dataset.cartUrl || '/cart');
			return;
		}

		showToast(payload.message || `Cart updated. Subtotal ${formatPrice(payload.subtotal || 0)}.`);
	} catch {
		showToast('Network error. Your cart was not changed. Please try again.');
	}
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
		const matchesPrice = !priceLimit || Number(card.dataset.productPrice) <= Number(priceLimit);
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

document.addEventListener('submit', (event) => {
	const addForm = event.target.closest('[data-cart-add-form]');
	if (addForm && window.fetch) {
		event.preventDefault();
		void submitCartForm(addForm, event);
		return;
	}

	const mutationForm = event.target.closest('[data-cart-mutation-form]');
	if (mutationForm && window.fetch) {
		event.preventDefault();
		void submitCartForm(mutationForm, event, true);
	}
});

document.addEventListener('click', (event) => {
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

document.querySelectorAll('[data-quantity-control]').forEach((control) => {
	const output = control.querySelector('output');
	const input = control.closest('form')?.querySelector('[data-quantity-input]');
	const maxQuantity = Number(control.dataset.maxQuantity) || Number.MAX_SAFE_INTEGER;

	control.addEventListener('click', (event) => {
		const button = event.target.closest('button[data-step]');
		if (!button || !output) return;
		const current = Number(output.value || output.textContent) || 1;
		const next = Math.min(maxQuantity, Math.max(1, current + Number(button.dataset.step)));
		output.value = next;
		output.textContent = next;
		if (input) input.value = next;
	});
});

const params = new URLSearchParams(window.location.search);
if (params.has('q')) {
	const searchInput = document.querySelector('[name="q"]');
	if (searchInput) searchInput.value = params.get('q');
}
applyProductFilters();
