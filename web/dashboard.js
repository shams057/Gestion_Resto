// Example: Fetch daily revenue and popular dishes
const ordersContainer = document.getElementById("orders-today");
const popularContainer = document.getElementById("popular-dishes");

fetch('api_dashboard.php')
    .then(res => res.json())
    .then(data => {
        // Orders today
        ordersContainer.innerHTML = "";
        data.orders.forEach(o => {
            const li = document.createElement("li");
            li.textContent = `${o.date}: ${o.nb_commandes} commandes - ${o.total_revenu} TND`;
            ordersContainer.appendChild(li);
        });

        // Popular dishes
        popularContainer.innerHTML = "";
        data.popular.forEach(p => {
            const li = document.createElement("li");
            li.textContent = `${p.nom} - ${p.quantite_vendue} vendus`;
            popularContainer.appendChild(li);
        });
    });
