// dashboard.js

// Elements for stats cards
const statRevenue = document.getElementById("stat-revenue");
const statOrders  = document.getElementById("stat-orders");
const statAlerts  = document.getElementById("stat-alerts");
const statServer  = document.getElementById("stat-server");

// Elements for lists and table
const ordersContainer  = document.getElementById("orders-today");
const popularContainer = document.getElementById("popular-dishes");
const recentTbody      = document.getElementById("recent-orders-body");

fetch("api_dashboard.php")
    .then(res => {
        if (res.status === 401) {
            window.location.href = "login.php";
            return Promise.reject(new Error("Unauthorized"));
        }
        return res.json();
    })
    .then(data => {
        if (!data) return;

        // ===== STATS CARDS =====
        if (data.stats) {
            if (statRevenue) {
                const total = data.stats.total_revenue ?? 0;
                statRevenue.textContent = total.toFixed(2) + " TND";
            }
            if (statOrders) {
                statOrders.textContent = data.stats.orders ?? "0";
            }
            if (statAlerts) {
                statAlerts.textContent = data.stats.alerts ?? "0";
            }
            if (statServer) {
                statServer.textContent = data.stats.server_status ?? "—";
            }
        }

        // ===== ORDERS PER DAY =====
        if (ordersContainer) {
            ordersContainer.innerHTML = "";
            (data.orders || []).forEach(o => {
                const li = document.createElement("li");
                li.textContent = `${o.date}: ${o.nb_commandes} commandes - ${o.total_revenu} TND`;
                ordersContainer.appendChild(li);
            });
        }

        // ===== POPULAR DISHES =====
        if (popularContainer) {
            popularContainer.innerHTML = "";
            (data.popular || []).forEach(p => {
                const li = document.createElement("li");
                li.textContent = `${p.nom} - ${p.quantite_vendue} vendus`;
                popularContainer.appendChild(li);
            });
        }

        // ===== RECENT ORDERS TABLE =====
        if (recentTbody) {
            recentTbody.innerHTML = "";
            (data.recent_orders || []).forEach(r => {
                const tr = document.createElement("tr");

                const tdDate   = document.createElement("td");
                const tdClient = document.createElement("td");
                const tdTotal  = document.createElement("td");
                const tdServ   = document.createElement("td");
                const tdItems  = document.createElement("td");

                tdDate.textContent   = r.date_commande;
                tdClient.textContent = r.client_nom;
                tdTotal.textContent  = `${r.total} TND`;
                tdServ.textContent   = r.serveur_nom || "";
                tdItems.textContent  = (r.items || []).join(", ");

                tr.appendChild(tdDate);
                tr.appendChild(tdClient);
                tr.appendChild(tdTotal);
                tr.appendChild(tdServ);
                tr.appendChild(tdItems);

                recentTbody.appendChild(tr);
            });
        }
    })
    .catch(err => {
        console.error("Dashboard fetch error:", err);
    });
