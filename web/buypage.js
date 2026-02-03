// buypage.js - COMPLETE FIXED VERSION WITH DEBUGGING

// ===================== DOM ELEMENTS =====================
const productsContainer = document.getElementById('products-container');
const cartItemsContainer = document.getElementById('cart-items');
const cartTotalDisplay = document.getElementById('cart-total');
const cartCountDisplay = document.getElementById('cart-count');
const cartCard = document.getElementById('cart-card');
const modal = document.getElementById('product-modal');
const modalBody = document.getElementById('modal-body');
const modalCloseBtn = document.getElementById('product-modal-close');

let cart = [];
let foods = [];

// ===================== INIT =====================
document.addEventListener('DOMContentLoaded', function () {
  initCart();
  loadProducts();
  initThemeToggle();
  initBurgerMenu();
  initModalClose();
  initCartUIButtons();
});

async function loadProducts() {
  try {
    const res = await fetch('api.php');
    const data = await res.json();
    console.log('PRODUCTS FROM API:', data);
    
    // DEBUG: Check first product structure
    if (data[0]) {
      console.log('FIRST PRODUCT KEYS:', Object.keys(data[0]));
      console.log('FIRST PRODUCT CATEGORY:', data[0].category);
    }
    
    foods = data;
    displayProducts(foods);
    
    // Initialize filters AFTER products load
    initAllergyFilters();
    initFilters();
  } catch (e) {
    console.error('API ERROR:', e);
    productsContainer.innerHTML = '<p>Erreur chargement produits</p>';
  }
}

// ===================== FILTERS =====================
function initAllergyFilters() {
  const allergyContainer = document.getElementById('allergy-filters');
  if (!allergyContainer || !foods.length) return;

  const allAllergens = new Set();
  foods.forEach(food => {
    if (food.allergy && Array.isArray(food.allergy)) {
      food.allergy.forEach(allergen => allAllergens.add(allergen));
    }
  });

  const uniqueAllergens = Array.from(allAllergens).sort();
  allergyContainer.innerHTML = '';

  uniqueAllergens.forEach(allergen => {
    const label = document.createElement('label');
    label.style.cssText = `
      display: flex; align-items: center; gap: 8px; 
      cursor: pointer; font-size: 13px; padding: 6px 0;
      color: var(--text-primary);
    `;

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.value = allergen;
    checkbox.id = `allergy-${allergen.toLowerCase().replace(/\s+/g, '-')}`;

    label.htmlFor = checkbox.id;
    label.appendChild(checkbox);

    const span = document.createElement('span');
    span.textContent = allergen;
    label.appendChild(span);

    allergyContainer.appendChild(label);
  });
}

function initFilters() {
  const searchInput = document.getElementById('search-input');
  const sortFilter = document.getElementById('sort-filter');
  const categoryFilter = document.getElementById('category-filter');
  const allergyContainer = document.getElementById('allergy-filters');

  // Populate categories with EXACT DB names + data
  if (categoryFilter && foods.length > 0) {
    populateCategories(categoryFilter);
  }

  if (searchInput) searchInput.addEventListener('input', applyFilters);
  if (sortFilter) sortFilter.addEventListener('change', applyFilters);
  if (categoryFilter) categoryFilter.addEventListener('change', applyFilters);
  if (allergyContainer) allergyContainer.addEventListener('change', applyFilters);
}

function populateCategories(selectElement) {
  // EXACT categories from your SQL categories table
  const dbCategories = ['Entrées', 'Plats principaux', 'Desserts'];
  
  selectElement.innerHTML = '<option value="all">Toutes les catégories</option>';
  
  dbCategories.forEach(category => {
    const option = document.createElement('option');
    option.value = category;
    option.textContent = category;
    selectElement.appendChild(option);
  });
}

function applyFilters() {
  const searchTerm = document.getElementById('search-input')?.value.toLowerCase() || '';
  const sortValue = document.getElementById('sort-filter')?.value || 'none';
  const categoryValue = document.getElementById('category-filter')?.value || 'all';

  console.log('FILTER DEBUG - Category:', categoryValue); // DEBUG

  const selectedAllergens = Array.from(
    document.querySelectorAll('#allergy-filters input:checked')
  ).map(cb => cb.value);

  let filtered = foods.filter(food => {
    // Search filter
    const matchesSearch = !searchTerm || 
      food.name?.toLowerCase().includes(searchTerm) || 
      food.desc?.toLowerCase().includes(searchTerm);

    // FIXED Category filter - try multiple possible field names
    let matchesCategory = categoryValue === 'all';
    if (!matchesCategory) {
      // Try category name first (from API JOIN c.nom AS category)
      matchesCategory = food.category === categoryValue;
      
      // Fallback: try id_categorie numeric match (1=Entrées, 2=Plats principaux, 3=Desserts)
      if (!matchesCategory && food.id_categorie) {
        const catId = {
          'Entrées': 1,
          'Plats principaux': 2,
          'Desserts': 3
        }[categoryValue];
        matchesCategory = parseInt(food.id_categorie) === catId;
      }
    }

    // Allergen filter - EXCLUDE selected allergens
    const matchesAllergens = selectedAllergens.length === 0 || 
      !food.allergy?.some(a => selectedAllergens.includes(a));

    const result = matchesSearch && matchesCategory && matchesAllergens;
    
    // DEBUG first few items
    if (foods.indexOf(food) < 3) {
      console.log('FILTER DEBUG:', food.name, {
        search: matchesSearch,
        category: matchesCategory,
        allergens: matchesAllergens,
        result
      });
    }
    
    return result;
  });

  console.log('FILTERED RESULTS:', filtered.length, 'items'); // DEBUG

  // Apply sorting
  if (sortValue === 'price-asc') {
    filtered.sort((a, b) => a.price - b.price);
  } else if (sortValue === 'price-desc') {
    filtered.sort((a, b) => b.price - a.price);
  } else if (sortValue === 'popularity-desc') {
    filtered.sort((a, b) => b.price - a.price);
  }

  displayProducts(filtered);
}

// ===================== CART UI =====================
function initCartUIButtons() {
  document.getElementById('cart-btn')?.addEventListener('click', () => {
    cartCard.classList.toggle('visible');
  });

  document.querySelector('.cart-close')?.addEventListener('click', () => {
    cartCard.classList.remove('visible');
  });
}

// ===================== CART LOGIC =====================
async function initCart() {
  try {
    const stored = localStorage.getItem('gresto_cart') || sessionStorage.getItem('cart');
    if (stored) cart = JSON.parse(stored);
  } catch {
    cart = [];
  }

  if (sessionStorage.getItem('auth')) {
    await syncCartAfterLogin();
  }

  updateCart();
}

async function syncCartAfterLogin() {
  const localCart = localStorage.getItem('gresto_cart');
  if (!localCart) return;

  try {
    const res = await fetch('api.php?action=get_cart', { credentials: 'include' });
    const data = await res.json();

    if (data.cart && data.cart.length > 0) {
      if (confirm('Fusionner panier précédent ?')) {
        const localItems = JSON.parse(localCart);
        localItems.forEach((localItem) => {
          const existing = cart.find((item) => item.name === localItem.name);
          if (existing) existing.quantity += localItem.quantity;
          else cart.push(localItem);
        });
        localStorage.removeItem('gresto_cart');
        await saveCartToServer();
      } else {
        localStorage.removeItem('gresto_cart');
      }
    }
  } catch (e) {
    console.error('Sync cart error', e);
  }
}

async function saveCartToServer() {
  try {
    await fetch('api.php?action=save_cart', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ cart }),
    });
  } catch (e) {}
}

function addToCart(food) {
  const existing = cart.find((item) => item.name === food.name);
  if (existing) {
    existing.quantity += 1;
  } else {
    cart.push({
      name: food.name,
      price: food.price,
      quantity: 1,
    });
  }

  saveCartToStorage();
  if (sessionStorage.getItem('auth')) saveCartToServer();
  updateCart();
  showCart();
}

function saveCartToStorage() {
  localStorage.setItem('gresto_cart', JSON.stringify(cart));
  sessionStorage.setItem('cart', JSON.stringify(cart));
}

function updateCart() {
  cartItemsContainer.innerHTML = '';
  let total = 0;
  let itemsCount = 0;

  cart.forEach((item) => {
    const lineTotal = item.price * item.quantity;
    const li = document.createElement('li');
    li.innerHTML = `
      <span>${item.name} x${item.quantity}</span>
      <span>${lineTotal.toFixed(2)} TND</span>
    `;
    cartItemsContainer.appendChild(li);
    total += lineTotal;
    itemsCount += item.quantity;
  });

  cartTotalDisplay.textContent = total.toFixed(2) + ' TND';
  cartCountDisplay.textContent = itemsCount;
}

function clearCart() {
  cart = [];
  saveCartToStorage();
  updateCart();
  if (sessionStorage.getItem('auth')) saveCartToServer();
}

function checkout() {
  if (cart.length === 0) {
    alert('Votre panier est vide');
    return;
  }

  cartCard.classList.remove('visible');
  const cartData = encodeURIComponent(JSON.stringify(cart));
  window.location.href = `cart?cart=${cartData}`;
}

function showCart() {
  cartCard.classList.add('visible');
}

// ===================== PRODUCTS =====================
function displayProducts(items) {
  productsContainer.innerHTML = '';

  if (!items || items.length === 0) {
    productsContainer.innerHTML = '<p>Aucun produit trouvé.</p>';
    return;
  }

  items.forEach((food) => {
    const allergyTags = food.allergy && Array.isArray(food.allergy)
      ? food.allergy
          .map((a) => {
            const key = String(a).toLowerCase();
            let extra = '';
            if (key.includes('gluten')) extra = ' gluten';
            else if (key.includes('lactose')) extra = ' lactose';
            else if (key.includes('noix') || key.includes('nut')) extra = ' nut';
            return `<span class="tag${extra}">${a}</span>`;
          })
          .join(' ')
      : '';

    const categoryTag = (food.category || food.id_categorie) ? 
      `<div class="category-tag">${food.category || `Cat ${food.id_categorie}`}</div>` : '';

    const card = document.createElement('div');
    card.className = 'card';

    const img = document.createElement('img');
    img.src = food.img || 'no-image.png';
    img.alt = food.name;
    img.onerror = () => { img.src = 'no-image.png'; };

    const h3 = document.createElement('h3');
    h3.textContent = food.name;

    const pDesc = document.createElement('p');
    pDesc.textContent = food.desc || '';

    const tags = document.createElement('div');
    tags.className = 'allergy-tags';
    tags.innerHTML = categoryTag + allergyTags;

    const price = document.createElement('p');
    price.innerHTML = `<strong>${Number(food.price).toFixed(2)} TND</strong>`;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = 'Ajouter';
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      addToCart(food);
    });

    card.appendChild(img);
    card.appendChild(h3);
    card.appendChild(pDesc);
    card.appendChild(tags);
    card.appendChild(price);
    card.appendChild(btn);

    card.addEventListener('click', (e) => {
      if (!e.target.closest('button')) openProductModal(food);
    });

    productsContainer.appendChild(card);
  });
}

// ===================== MODAL =====================
function openProductModal(food) {
  const allergyTags = food.allergy && Array.isArray(food.allergy)
    ? food.allergy
        .map((a) => {
          const key = String(a).toLowerCase();
          let extra = '';
          if (key.includes('gluten')) extra = ' gluten';
          else if (key.includes('lactose')) extra = ' lactose';
          else if (key.includes('noix') || key.includes('nut')) extra = ' nut';
          return `<span class="tag${extra}">${a}</span>`;
        })
        .join(' ')
    : '';

  const categoryTag = (food.category || food.id_categorie) ? 
    `<div class="category-tag">${food.category || `Cat ${food.id_categorie}`}</div>` : '';

  modalBody.innerHTML = `
    <div class="card">
      <img src="${food.img || 'no-image.png'}" alt="${food.name}" onerror="this.src='no-image.png'">
      <h3>${food.name}</h3>
      ${categoryTag}
      <p>${food.desc || ''}</p>
      <div class="allergy-tags">${allergyTags}</div>
      <p><strong>${Number(food.price).toFixed(2)} TND</strong></p>
      <button id="modal-add-btn" type="button">Ajouter au panier</button>
    </div>
  `;

  modal.classList.add('open');

  document.getElementById('modal-add-btn')?.addEventListener('click', () => {
    addToCart(food);
  });
}

function initModalClose() {
  modalCloseBtn?.addEventListener('click', () => {
    modal.classList.remove('open');
  });

  document.addEventListener('click', (e) => {
    if (e.target === modal) modal.classList.remove('open');
  });
}

// ===================== UI FUNCTIONS =====================
function initThemeToggle() {
  const toggle = document.getElementById('theme-toggle');
  if (!toggle) return;

  const saved = localStorage.getItem('theme');
  if (saved === 'dark') document.documentElement.classList.add('dark-mode');

  toggle.addEventListener('click', () => {
    const isDark = document.documentElement.classList.toggle('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
  });
}

function initBurgerMenu() {
  const burger = document.getElementById('burger-menu');
  const nav = document.getElementById('main-nav');
  if (!burger || !nav) return;

  burger.addEventListener('click', () => {
    nav.classList.toggle('open');
  });
}
