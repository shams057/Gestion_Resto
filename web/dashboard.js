// dashboard.js

// --- UI / Sidebar Logic ---
const burgerMenuBtn = document.getElementById("burger-menu-btn");
const sidebar = document.querySelector(".sidebar-modern");

if (burgerMenuBtn) {
  burgerMenuBtn.addEventListener("click", () => {
    sidebar.classList.toggle("sidebar-open");
    burgerMenuBtn.classList.toggle("active");
  });

  document.addEventListener("click", (e) => {
    if (!sidebar.contains(e.target) && !burgerMenuBtn.contains(e.target)) {
      sidebar.classList.remove("sidebar-open");
      burgerMenuBtn.classList.remove("active");
    }
  });
  sidebar.querySelectorAll(".nav-link").forEach((link) => {
    link.addEventListener("click", () => {
      sidebar.classList.remove("sidebar-open");
      burgerMenuBtn.classList.remove("active");
    });
  });
}

// --- Theme Logic ---
function initThemeToggle() {
  const themeToggle = document.getElementById("theme-toggle-nav");
  const html = document.documentElement;
  
  const savedTheme = localStorage.getItem("theme") || "light";
  if (savedTheme === "dark") {
    html.classList.add("dark-mode");
    if(themeToggle) themeToggle.textContent = "☀️";
  }

  if(themeToggle) {
    themeToggle.addEventListener("click", () => {
      html.classList.toggle("dark-mode");
      const isDark = html.classList.contains("dark-mode");
      localStorage.setItem("theme", isDark ? "dark" : "light");
      themeToggle.textContent = isDark ? "☀️" : "🌙";
    });
  }
}

// --- Data Fetching Logic ---

let currentRange = 'month';

// 1. Stats Cards (Revenue, Orders, Alerts)
async function loadStats() {
  try {
    const res = await fetch(`api_dashboard.php?action=getStats&range=${currentRange}`);
    if (res.status === 401) window.location.href = 'login';
    const data = await res.json();

    document.getElementById('stat-revenue').textContent = data.revenue.toFixed(2) + ' TND';
    document.getElementById('stat-orders').textContent = data.orders;
    document.getElementById('stat-alerts').textContent = data.alerts;
  } catch (e) {
    console.error("Stats load error:", e);
  }
}

// 2. Last 7 Days (List)
async function loadLast7Days() {
  try {
    const res = await fetch('api_dashboard.php?action=getLast7Days');
    const data = await res.json();
    const container = document.getElementById("orders-today");
    
    if (container) {
      container.innerHTML = "";
      if (data.length === 0) container.innerHTML = "<li>Aucune commande récente</li>";
      
      data.forEach(d => {
        const li = document.createElement("li");
        li.textContent = `${d.date}: ${d.count} comm. - ${parseFloat(d.revenue).toFixed(2)} TND`;
        container.appendChild(li);
      });
    }
  } catch (e) {
    console.error("Last 7 days error:", e);
  }
}

// 3. Popular Dishes
async function loadPopularDishes() {
  try {
    const res = await fetch('api_dashboard.php?action=getPopularDishes');
    const data = await res.json();
    const container = document.getElementById("popular-dishes");
    
    if (container) {
      container.innerHTML = "";
      if (data.length === 0) container.innerHTML = "<li>Aucune donnée</li>";

      data.forEach(p => {
        const li = document.createElement("li");
        li.textContent = `${p.nom} - ${p.sold} vendus`;
        container.appendChild(li);
      });
    }
  } catch (e) {
    console.error("Popular dishes error:", e);
  }
}

// 4. Recent Orders Table
async function loadRecentOrders() {
  try {
    const res = await fetch('api_dashboard.php?action=getRecentOrders');
    const data = await res.json();
    const tbody = document.getElementById("recent-orders-body");
    
    if (tbody) {
      tbody.innerHTML = "";
      data.forEach(r => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td>${new Date(r.created_at).toLocaleString()}</td>
            <td>${r.client_name || 'Client de passage'}</td>
            <td>${parseFloat(r.total).toFixed(2)} TND</td>
            <td>${r.serveur_nom || 'Web'}</td>
            <td style="font-size:0.9em; color:#666;">${r.items || ''}</td>
        `;
        tbody.appendChild(tr);
      });
    }
  } catch (e) {
    console.error("Recent orders error:", e);
  }
}

// 5. Low Star Alerts Table
async function loadLowStarAlerts() {
  try {
    const res = await fetch('api_dashboard.php?action=getLowStarAlerts');
    const data = await res.json();
    const tbody = document.getElementById('low-stars-body');
    
    if (tbody && data.alerts) {
      tbody.innerHTML = '';
      data.alerts.forEach(a => {
        const tr = document.createElement('tr');
        tr.className = 'low-star-row';
        tr.innerHTML = `
          <td>${new Date(a.created_at).toLocaleString()}</td>
          <td>${a.client_name}</td>
          <td>${a.plat_name}</td>
          <td><span class="low-star-badge">${a.rating}★</span></td>
          <td>${a.comment || ''}</td>
        `;
        tbody.appendChild(tr);
      });
    }
  } catch (e) {
    console.error('Low star alerts error', e);
  }
}

// --- Initialization ---
document.addEventListener('DOMContentLoaded', () => {
  initThemeToggle();

  // Load Initial Data
  loadStats();
  loadLast7Days();
  loadPopularDishes();
  loadRecentOrders();
  loadLowStarAlerts();

  // NEW: Modern Period Selector Logic
  const periodSelector = document.getElementById('period-selector');
  if (periodSelector) {
    const buttons = periodSelector.querySelectorAll('.period-btn');
    
    buttons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        // 1. Remove active class from all
        buttons.forEach(b => b.classList.remove('active'));
        
        // 2. Add active class to clicked
        e.target.classList.add('active');
        
        // 3. Update Global Range
        currentRange = e.target.getAttribute('data-value');
        
        // 4. Reload Stats (Top cards only, usually)
        loadStats();
      });
    });
  }
});