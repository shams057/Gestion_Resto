let cart = [];
let foods = [];

// ===================== INIT CART (DB + fallback) =====================

async function initCart() {
    try {
        const res = await fetch('api.php?action=get_cart', {
            credentials: 'include'
        });
        if (res.ok) {
            const data = await res.json();
            if (data && Array.isArray(data.cart)) {
                cart = data.cart;
                sessionStorage.setItem('cart', JSON.stringify(cart));
            }
        }
    } catch (e) {
        console.error('Erreur chargement panier DB', e);
        // fallback to sessionStorage
        try {
            const stored = sessionStorage.getItem('cart');
            cart = stored ? JSON.parse(stored) : [];
        } catch {
            cart = [];
        }
    }

    updateCart(); // reflect DB cart in sidebar + badge
}

initCart();

// ===================== DOM ELEMENTS =====================

const productsContainer = document.getElementById('products');
const searchInput = document.getElementById('search-input');
const categoryFilter = document.getElementById('category-filter');
const allergyContainer = document.getElementById('allergy-filters');
const cartBtn = document.getElementById('cart-btn');
const cartCard = document.getElementById('cart-card');
const cartItemsContainer = document.getElementById('cart-items');
const cartTotalDisplay = document.getElementById('cart-total');
const cartCountDisplay = document.getElementById('cart-count');
const buyBtn = document.getElementById('buy-btn');
const sortFilter = document.getElementById('sort-filter');
const modal = document.getElementById('product-modal');
const modalBody = document.getElementById('product-modal-body');
const modalClose = document.getElementById('product-modal-close');

// ===================== DISPLAY PRODUCTS =====================

function displayProducts(items) {
    productsContainer.innerHTML = '';

    if (!items || items.length === 0) {
        productsContainer.innerHTML = '<p>No items found.</p>';
        return;
    }

    items.forEach(food => {
        const allergyTags = food.allergy.length
            ? food.allergy.map(a => `<span class="tag">${a}</span>`).join(' ')
            : '';

        const card = document.createElement('div');
        card.className = 'card';
        card.innerHTML = `
    <img src="${food.img}" alt="${food.name}">
    <h3>${food.name}</h3>
    <p>${food.desc}</p>
    <div class="allergy-tags">${allergyTags}</div>
    <p><strong>${food.price.toFixed(2)} TND</strong></p>
    <button>Add to Cart</button>
  `;

        // click animation + open modal
        card.addEventListener('click', () => {
            // restart animation if it's already applied
            card.classList.remove('card-clicked');
            void card.offsetWidth; // force reflow so animation restarts
            card.classList.add('card-clicked');

            openProductModal(food);
        });

        const addBtn = card.querySelector('button');
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            addToCart(food);
        });

        productsContainer.appendChild(card);
    });
}

// ===================== CART FUNCTIONS =====================

function addToCart(food) {
    const existing = cart.find(item => item.name === food.name);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({
            name: food.name,
            price: food.price,
            quantity: 1
        });
    }

    // keep browser cart
    sessionStorage.setItem('cart', JSON.stringify(cart));

    updateCart();
    showCart();

    // persist cart in DB for logged-in clients
    fetch('api.php?action=save_cart', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ cart })
    }).catch(() => { });
}

function updateCart() {
    cartItemsContainer.innerHTML = '';

    let total = 0;
    let itemsCount = 0;

    cart.forEach(item => {
        const lineTotal = item.price * item.quantity;
        const li = document.createElement('li');
        li.textContent = `${item.name} x${item.quantity} - ${lineTotal.toFixed(2)} TND`;
        cartItemsContainer.appendChild(li);

        total += lineTotal;
        itemsCount += item.quantity;
    });

    cartTotalDisplay.textContent = total.toFixed(2) + ' TND';
    cartCountDisplay.textContent = itemsCount;
}

function showCart() {
    cartCard.classList.add('visible');
}

// ===================== MODAL + REVIEW =====================

function openProductModal(food) {
    const allergyTags = food.allergy.length
        ? food.allergy.map(a => `<span class="tag">${a}</span>`).join(' ')
        : '';

    modalBody.innerHTML = `
    <div class="card">
      <img src="${food.img}" alt="${food.name}">
      <h3>${food.name}</h3>
      <p>${food.desc}</p>
      <div class="allergy-tags">${allergyTags}</div>
      <p><strong>${food.price.toFixed(2)} TND</strong></p>
      <button id="modal-add-cart">Add to Cart</button>

      <hr style="margin:15px 0;">

      <div>
        <h4>Laisser un avis</h4>
        <label for="review-rating">Note (1-5, optionnelle)</label><br>
        <select id="review-rating">
          <option value="">Aucune</option>
          <option value="1">1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">5</option>
        </select>

        <br><br>
        <textarea id="review-comment" rows="3" style="width:100%;" placeholder="Écrivez votre avis..."></textarea>
        <br>
        <button id="review-submit">Envoyer l'avis</button>
      </div>
    </div>
  `;

    modal.classList.add('open');

    document.getElementById('modal-add-cart')
        .addEventListener('click', () => addToCart(food));

    document.getElementById('review-submit')
        .addEventListener('click', async () => {
            const ratingVal = document.getElementById('review-rating').value;
            const comment = document.getElementById('review-comment').value.trim();
            const rating = ratingVal ? parseInt(ratingVal, 10) : null;

            if (!comment) {
                alert('Veuillez écrire un avis.');
                return;
            }

            try {
                const res = await fetch('api.php?action=save_review', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        id_plat: food.id,
                        rating,
                        comment
                    })
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    alert(data.error || "Erreur lors de l'envoi de l'avis.");
                    return;
                }
                alert('Merci pour votre avis !');
                modal.classList.remove('open');
            } catch (e) {
                console.error('Review error', e);
                alert('Erreur réseau lors de lenvoi de lavis.');
            }
        });
}

// ===================== FILTERING =====================

function filterProducts() {
    const text = searchInput.value.toLowerCase();
    const category = categoryFilter.value;
    const selectedAllergies = Array.from(
        document.querySelectorAll('.allergy-checkbox:checked')
    ).map(cb => cb.value);

    let filtered = foods.filter(food => {
        const matchesText = food.name.toLowerCase().includes(text);
        const matchesCategory = category === 'all' || food.category === category;
        const matchesAllergy =
            selectedAllergies.length === 0 ||
            selectedAllergies.some(a => food.allergy.includes(a));
        return matchesText && matchesCategory && matchesAllergy;
    });

    if (sortFilter) {
        switch (sortFilter.value) {
            case 'price-asc':
                filtered = filtered.slice().sort((a, b) => a.price - b.price);
                break;
            case 'price-desc':
                filtered = filtered.slice().sort((a, b) => b.price - a.price);
                break;
            case 'popularity-desc':
                filtered = filtered
                    .slice()
                    .sort((a, b) => (b.popularity || 0) - (a.popularity || 0));
                break;
        }
    }

    displayProducts(filtered);
}

if (sortFilter) {
    sortFilter.addEventListener('change', filterProducts);
}

// ===================== SETUP FILTERS =====================

function setupFilters(data) {
    const categories = [...new Set(data.map(f => f.category))];

    // ---- Categories ----
    categories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat;
        opt.textContent = cat;
        categoryFilter.appendChild(opt);
    });

    // ---- Allergies ----
    const allergies = [...new Set(data.flatMap(f => f.allergy))];

    allergies.forEach(a => {
        const id = `allergy-${a}`;
        const div = document.createElement('div');
        div.innerHTML = `
      <input type="checkbox" class="allergy-checkbox" value="${a}" id="${id}">
      <label for="${id}">${a}</label>
    `;
        allergyContainer.appendChild(div);
    });

    document
        .querySelectorAll('.allergy-checkbox')
        .forEach(cb => cb.addEventListener('change', filterProducts));
}

// ===================== FETCH DATABASE DATA =====================

fetch('api.php')
    .then(res => res.json())
    .then(data => {
        foods = data.map(f => ({
            id: f.id,
            name: f.nom,
            desc: f.description || '',
            price: parseFloat(f.prix),
            category: f.category,
            img: f.image_url || 'https://via.placeholder.com/300x200',
            allergy: Array.isArray(f.allergies)
                ? f.allergies
                : (f.allergies ? f.allergies.split(',').map(a => a.trim()) : []),
            popularity: Number(f.popularity) || 0
        }));

        setupFilters(foods);
        displayProducts(foods);
    })
    .catch(err => console.error('Fetch Error:', err));

// ===================== UI EVENTS =====================

searchInput.addEventListener('input', filterProducts);
categoryFilter.addEventListener('change', filterProducts);

cartBtn.addEventListener('click', () => {
    cartCard.classList.toggle('visible');
});

// Now just go to cart page, do not call API directly
buyBtn.addEventListener('click', () => {
    if (cart.length === 0) {
        alert('Votre panier est vide.');
        return;
    }

    sessionStorage.setItem('cart', JSON.stringify(cart));
    window.location.href = 'cart'; // /web/cart.php via router
});

// Modal close handlers

if (modalClose) {
    modalClose.addEventListener('click', () => {
        modal.classList.remove('open');
    });
}

if (modal) {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('open');
        }
    });
}
