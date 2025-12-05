function loadDetails(name, ingredients, price, waiter) {
    document.getElementById("modalTitle").innerText = name;
    document.getElementById("modalIngredients").innerText = ingredients;
    document.getElementById("modalPrice").innerText = price;
    document.getElementById("modalWaiter").innerText = waiter;
}