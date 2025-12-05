function loadDetails(name, ingredients, price, waiter) {
    document.getElementById("modalTitle").innerText = name;
    document.getElementById("modalIngredients").innerText = ingredients;
    document.getElementById("modalPrice").innerText = price;
    document.getElementById("modalWaiter").innerText = waiter;
}
let cart = [];

const foods = [];
for(let i=1;i<=50;i++){
    foods.push({
        name:`Food ${i}`,
        category:["fruits","vegetables","meat","snacks","drinks"][Math.floor(Math.random()*5)],
        price: Number((Math.random()*10+2).toFixed(2)),
        allergy:["gluten-free","lactose-free","nut-free","none"][Math.floor(Math.random()*4)].split(","),
        img:`https://picsum.photos/seed/food${i}/300/200`,
        desc:`Delicious Food Item #${i}`
    });
}

const productsContainer = document.getElementById("products");
const searchInput = document.getElementById("search-input");
const categoryFilter = document.getElementById("category-filter");
const allergyCheckboxes = document.querySelectorAll(".allergy-checkbox");
const cartBtn = document.getElementById("cart-btn");
const cartCard = document.getElementById("cart-card");
const cartItemsContainer = document.getElementById("cart-items");
const cartTotalDisplay = document.getElementById("cart-total");
const cartCountDisplay = document.getElementById("cart-count");
const buyBtn = document.getElementById("buy-btn");

// Display Products
function displayProducts(items){
    productsContainer.innerHTML="";
    items.forEach(food=>{
        const card=document.createElement("div");
        card.className="card";
        card.innerHTML=`
            <img src="${food.img}" alt="${food.name}">
            <h3>${food.name}</h3>
            <p>${food.desc}</p>
            <p><strong>${food.price} TND</strong></p>
            <button>Add to Cart</button>
        `;
        const btn=card.querySelector("button");
        btn.addEventListener("click",()=>{
            addToCart(food);
        });
        productsContainer.appendChild(card);
    });
}

// Add to cart
function addToCart(food){
    cart.push(food);
    updateCart();
    showCart();
}

function updateCart(){
    cartItemsContainer.innerHTML="";
    let total=0;
    cart.forEach((item,i)=>{
        const li=document.createElement("li");
        li.textContent=`${item.name} - ${item.price} TND`;
        cartItemsContainer.appendChild(li);
        total+=item.price;
    });
    cartTotalDisplay.textContent=total.toFixed(2)+" TND";
    cartCountDisplay.textContent=cart.length;
}

// Show/Hide Cart
function showCart(){
    cartCard.classList.add("visible");
}

// Filters
function filterProducts(){
    const text=searchInput.value.toLowerCase();
    const category=categoryFilter.value;
    const selectedAllergies=Array.from(allergyCheckboxes).filter(cb=>cb.checked).map(cb=>cb.value);

    const filtered=foods.filter(food=>{
        const matchesText=food.name.toLowerCase().includes(text);
        const matchesCategory=category==="all"||food.category===category;
        const matchesAllergy=selectedAllergies.length===0 || selectedAllergies.every(a=>food.allergy.includes(a));
        return matchesText && matchesCategory && matchesAllergy;
    });
    displayProducts(filtered);
}

// Event Listeners
searchInput.addEventListener("input",filterProducts);
categoryFilter.addEventListener("change",filterProducts);
allergyCheckboxes.forEach(cb=>cb.addEventListener("change",filterProducts));
cartBtn.addEventListener("click",()=>cartCard.classList.toggle("visible"));
buyBtn.addEventListener("click",()=>{ alert("Thank you for your purchase!"); cart=[]; updateCart(); cartCard.classList.remove("visible"); });

// Initial render
displayProducts(foods);
