// dashboard.js

const ordersContainer = document.getElementById("orders-today");
const popularContainer = document.getElementById("popular-dishes");

if (ordersContainer && popularContainer) {
    fetch('api_dashboard.php')
        .then(res => {
            if (res.status === 401) {
                // Not authorized -> go back to login
                window.location.href = 'login.php';
                return Promise.reject(new Error('Unauthorized'));
            }
            return res.json();
        })
        .then(data => {
            if (!data) return;

            // Orders today
            ordersContainer.innerHTML = "";
            (data.orders || []).forEach(o => {
                const li = document.createElement("li");
                li.textContent = `${o.date}: ${o.nb_commandes} commandes - ${o.total_revenu} TND`;
                ordersContainer.appendChild(li);
            });

            // Popular dishes
            popularContainer.innerHTML = "";
            (data.popular || []).forEach(p => {
                const li = document.createElement("li");
                li.textContent = `${p.nom} - ${p.quantite_vendue} vendus`;
                popularContainer.appendChild(li);
            });
        })
        .catch(err => {
            console.error('Dashboard fetch error:', err);
        });
} else {
    console.warn('Dashboard containers not found in DOM');
}
