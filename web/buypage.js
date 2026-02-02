// DOM Elements - ADD THESE AT TOP
const productsContainer = document.getElementById('products-container');
const cartItemsContainer = document.getElementById('cart-items');
const cartTotalDisplay = document.getElementById('cart-total');
const cartCountDisplay = document.getElementById('cart-count');
const cartCard = document.getElementById('cart-card');
const modal = document.getElementById('product-modal');
const modalBody = document.getElementById('modal-body');

let cart = [];
let foods = [];

// ===================== INIT =====================
document.addEventListener('DOMContentLoaded', function() {
    initCart();
    loadProducts();
    initThemeToggle();
    initBurgerMenu();
});

async function loadProducts() {
    try {
        const res = await fetch('api.php');
        const data = await res.json();
        console.log('PRODUCTS FROM API:', data);
        foods = data;
        displayProducts(foods);
    } catch(e) {
        console.error('API ERROR:', e);
        productsContainer.innerHTML = '<p>Erreur chargement produits</p>';
    }
}

// ===================== CART =====================
// Cart toggle
document.getElementById('cart-btn')?.addEventListener('click', () => {
    cartCard.classList.toggle('visible');
});

// Close cart with X button
document.querySelector('.cart-close')?.addEventListener('click', () => {
    cartCard.classList.remove('visible');
});

async function initCart() {
    try {
        const stored = localStorage.getItem('gresto_cart') || sessionStorage.getItem("cart");
        if (stored) {
            cart = JSON.parse(stored);
        }
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
        const res = await fetch("api.php?action=get_cart", { credentials: "include" });
        const data = await res.json();
        if (data.cart && data.cart.length > 0) {
            if (confirm('Fusionner panier précédent ?')) {
                const serverCart = data.cart;
                const localItems = JSON.parse(localCart);
                localItems.forEach(localItem => {
                    const existing = cart.find(item => item.name === localItem.name);
                    if (existing) {
                        existing.quantity += localItem.quantity;
                    } else {
                        cart.push(localItem);
                    }
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
        await fetch("api.php?action=save_cart", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
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
    sessionStorage.setItem("cart", JSON.stringify(cart));
}

function updateCart() {
    cartItemsContainer.innerHTML = "";
    let total = 0;
    let itemsCount = 0;

    cart.forEach((item) => {
        const lineTotal = item.price * item.quantity;
        const li = document.createElement("li");
        li.innerHTML = `
            <span>${item.name} x${item.quantity}</span>
            <span>${lineTotal.toFixed(2)} TND</span>
        `;
        cartItemsContainer.appendChild(li);
        total += lineTotal;
        itemsCount += item.quantity;
    });

    cartTotalDisplay.textContent = total.toFixed(2) + " TND";
    cartCountDisplay.textContent = itemsCount;
}
function clearCart() {
    cart = [];
    saveCartToStorage();
    updateCart();
}

function checkout() {
    if (cart.length === 0) {
        alert('Votre panier est vide');
        return;
    }
    
    // Hide cart
    cartCard.classList.remove('visible');
    
    // Redirect to cart page with cart data
    const cartData = encodeURIComponent(JSON.stringify(cart));
    window.location.href = `cart?cart=${cartData}`;
}


function showCart() {
    cartCard.classList.add("visible");
}

// ===================== PRODUCTS =====================
function displayProducts(items) {
    productsContainer.innerHTML = "";

    if (!items || items.length === 0) {
        productsContainer.innerHTML = "<p>Aucun produit trouvé.</p>";
        return;
    }

    items.forEach((food) => {
        const allergyTags = food.allergy && food.allergy.length
            ? food.allergy.map((a) => {
                const key = a.toLowerCase();
                let extra = "";
                if (key.includes("gluten")) extra = " gluten";
                else if (key.includes("lactose")) extra = " lactose";
                else if (key.includes("noix") || key.includes("nut")) extra = " nut";
                return `<span class="tag${extra}">${a}</span>`;
            }).join(" ")
            : "";

        const card = document.createElement("div");
        card.className = "card";
        card.innerHTML = `
            <img src="${food.img}" alt="${food.name}" onerror="this.src='no-image.png'">
            <h3>${food.name}</h3>
            <p>${food.desc}</p>
            <div class="allergy-tags">${allergyTags}</div>
            <p><strong>${food.price.toFixed(2)} TND</strong></p>
            <button onclick="addToCart(${JSON.stringify(food).replace(/"/g, '&quot;')})">Ajouter</button>
        `;

        card.addEventListener("click", (e) => {
            if (!e.target.tagName === 'BUTTON') {
                openProductModal(food);
            }
        });

        productsContainer.appendChild(card);
    });
}

// ===================== MODAL =====================
function openProductModal(food) {
    const allergyTags = food.allergy && food.allergy.length
        ? food.allergy.map((a) => {
            const key = a.toLowerCase();
            let extra = "";
            if (key.includes("gluten")) extra = " gluten";
            else if (key.includes("lactose")) extra = " lactose";
            else if (key.includes("noix") || key.includes("nut")) extra = " nut";
            return `<span class="tag${extra}">${a}</span>`;
        }).join(" ")
        : "";

    modalBody.innerHTML = `
        <div class="card">
            <img src="${food.img}" alt="${food.name}" onerror="this.src='no-image.png'">
            <h3>${food.name}</h3>
            <p>${food.desc}</p>
            <div class="allergy-tags">${allergyTags}</div>
            <p><strong>${food.price.toFixed(2)} TND</strong></p>
            <button onclick="addToCart(${JSON.stringify(food).replace(/"/g, '&quot;')})">Ajouter au panier</button>
        </div>
    `;

    modal.classList.add("open");
}

// ===================== UI FUNCTIONS =====================
function initThemeToggle() {
    const toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-theme');
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
        });
        
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-theme');
        }
    }
}

function initBurgerMenu() {
    const burger = document.getElementById('burger-menu');
    const nav = document.getElementById('main-nav');
    if (burger && nav) {
        burger.addEventListener('click', () => {
            nav.classList.toggle('open');
        });
    }
}

// Close modal
document.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.classList.remove('open');
    }
});
