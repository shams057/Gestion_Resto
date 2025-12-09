let cart = [];
let foods = [];

const productsContainer = document.getElementById("products");
const searchInput = document.getElementById("search-input");
const categoryFilter = document.getElementById("category-filter");
const allergyContainer = document.getElementById("allergy-filters");
const cartBtn = document.getElementById("cart-btn");
const cartCard = document.getElementById("cart-card");
const cartItemsContainer = document.getElementById("cart-items");
const cartTotalDisplay = document.getElementById("cart-total");
const cartCountDisplay = document.getElementById("cart-count");
const buyBtn = document.getElementById("buy-btn");

// ===================== DISPLAY PRODUCTS =====================
function displayProducts(items) {

    productsContainer.innerHTML = "";

    if (items.length === 0) {
        productsContainer.innerHTML = "<p>No items found.</p>";
        return;
    }

    items.forEach(food => {
        const allergyTags = food.allergy.length
            ? food.allergy.map(a => `<span class="tag">${a}</span>`).join("")
            : "";

        const card = document.createElement("div");
        card.className = "card";
        card.innerHTML = `
            <img src="${food.img}" alt="${food.name}">
            <h3>${food.name}</h3>
            <p>${food.desc}</p>
            <div class="allergy-tags">${allergyTags}</div>
            <p><strong>${food.price.toFixed(2)} TND</strong></p>
            <button>Add to Cart</button>
        `;
        card.querySelector("button").addEventListener("click", () => addToCart(food));
        productsContainer.appendChild(card);
    });
}


// ===================== CART FUNCTIONS =====================
function addToCart(food) {
    cart.push(food);
    updateCart();
    showCart();
}

function updateCart() {
    cartItemsContainer.innerHTML = "";
    let total = 0;
    cart.forEach(item => {
        const li = document.createElement("li");
        li.textContent = `${item.name} - ${item.price.toFixed(2)} TND`;
        cartItemsContainer.appendChild(li);
        total += item.price;
    });
    cartTotalDisplay.textContent = total.toFixed(2) + " TND";
    cartCountDisplay.textContent = cart.length;
}

function showCart() {
    cartCard.classList.add("visible");
}


// ===================== FILTERING =====================
function filterProducts() {
    const text = searchInput.value.toLowerCase();
    const category = categoryFilter.value;
    const selectedAllergies = Array.from(
        document.querySelectorAll(".allergy-checkbox:checked")
    ).map(cb => cb.value);

    const filtered = foods.filter(food => {
        const matchesText = food.name.toLowerCase().includes(text);
        const matchesCategory = category === "all" || food.category === category;

        // Food must HAVE at least one of the selected allergens
        const matchesAllergy =
            selectedAllergies.length === 0 ||
            selectedAllergies.some(a => food.allergy.includes(a));

        return matchesText && matchesCategory && matchesAllergy;
    });

    displayProducts(filtered);
}
// ===================== SETUP FILTERS =====================
function setupFilters(data) {

    const categories = [...new Set(data.map(f => f.category))];

    // ---- Categories ----
    categories.forEach(cat => {
        const opt = document.createElement("option");
        opt.value = cat;
        opt.textContent = cat;
        categoryFilter.appendChild(opt);
    });

    // ---- Allergies ----
    const allergies = [...new Set(data.flatMap(f => f.allergy))];

    allergies.forEach(a => {
        const id = `allergy-${a}`;
        const div = document.createElement("div");
        div.innerHTML = `
            <input type="checkbox" class="allergy-checkbox" value="${a}" id="${id}">
            <label for="${id}">${a}</label>
        `;
        allergyContainer.appendChild(div);
    });

    document.querySelectorAll(".allergy-checkbox")
        .forEach(cb => cb.addEventListener("change", filterProducts));
}


// ===================== FETCH DATABASE DATA =====================
fetch("api.php")
    .then(res => res.json())
    .then(data => {

        foods = data.map(f => ({
            name: f.nom,
            desc: f.description,
            price: parseFloat(f.prix),
            category: f.category,
            img: f.image_url || "https://via.placeholder.com/300x200",
            allergy: Array.isArray(f.allergies) ? f.allergies : []
        }));

        setupFilters(foods);
        displayProducts(foods);
    })
    .catch(err => console.error("Fetch Error:", err));


// ===================== UI EVENTS =====================
searchInput.addEventListener("input", filterProducts);
categoryFilter.addEventListener("change", filterProducts);

cartBtn.addEventListener("click", () =>
    cartCard.classList.toggle("visible")
);

buyBtn.addEventListener("click", () => {
    alert("Order Confirmed!");
    cart = [];
    updateCart();
    cartCard.classList.remove("visible");
});