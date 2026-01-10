// dashboard.js

const burgerMenuBtn = document.getElementById("burger-menu-btn")
const sidebar = document.querySelector(".sidebar-modern")

if (burgerMenuBtn) {
  burgerMenuBtn.addEventListener("click", () => {
    sidebar.classList.toggle("sidebar-open")
    burgerMenuBtn.classList.toggle("active")
  })

  // Close sidebar when a nav link is clicked
  const navLinks = sidebar.querySelectorAll(".nav-link")
  navLinks.forEach((link) => {
    link.addEventListener("click", () => {
      sidebar.classList.remove("sidebar-open")
      burgerMenuBtn.classList.remove("active")
    })
  })

  // Close sidebar when clicking outside
  document.addEventListener("click", (e) => {
    if (!sidebar.contains(e.target) && !burgerMenuBtn.contains(e.target)) {
      sidebar.classList.remove("sidebar-open")
      burgerMenuBtn.classList.remove("active")
    }
  })
}

const statRevenue = document.getElementById("stat-revenue")
const statOrders = document.getElementById("stat-orders")
const statAlerts = document.getElementById("stat-alerts")
const statServer = document.getElementById("stat-server")

const ordersContainer = document.getElementById("orders-today")
const popularContainer = document.getElementById("popular-dishes")
const recentTbody = document.getElementById("recent-orders-body")

function initThemeToggle() {
  const themeToggle = document.getElementById("theme-toggle-nav")
  const html = document.documentElement

  // Check localStorage for saved theme
  const savedTheme = localStorage.getItem("theme") || "light"
  if (savedTheme === "dark") {
    html.classList.add("dark-mode")
    themeToggle.textContent = "☀️"
  }

  themeToggle.addEventListener("click", () => {
    html.classList.toggle("dark-mode")
    const isDark = html.classList.contains("dark-mode")
    localStorage.setItem("theme", isDark ? "dark" : "light")
    themeToggle.textContent = isDark ? "☀️" : "🌙"
  })
}

initThemeToggle()

fetch("api_dashboard.php")
  .then((res) => {
    if (res.status === 401) {
      window.location.href = "login"
      return Promise.reject(new Error("Unauthorized"))
    }
    return res.json()
  })
  .then((data) => {
    if (!data) return

    if (data.stats) {
      if (statRevenue) {
        const total = data.stats.total_revenue ?? 0
        statRevenue.textContent = total.toFixed(2) + " TND"
      }
      if (statOrders) {
        statOrders.textContent = data.stats.orders ?? "0"
      }
      if (statAlerts) {
        statAlerts.textContent = data.stats.alerts ?? "0"
      }
      if (statServer) {
        statServer.textContent = data.stats.server_status ?? "—"
      }
    }

    if (ordersContainer) {
      ordersContainer.innerHTML = ""
      ;(data.orders || []).forEach((o) => {
        const li = document.createElement("li")
        li.textContent = `${o.date}: ${o.nb_commandes} commandes - ${o.total_revenu} TND`
        ordersContainer.appendChild(li)
      })
    }

    if (popularContainer) {
      popularContainer.innerHTML = ""
      ;(data.popular || []).forEach((p) => {
        const li = document.createElement("li")
        li.textContent = `${p.nom} - ${p.quantite_vendue} vendus`
        popularContainer.appendChild(li)
      })
    }

    if (recentTbody) {
      recentTbody.innerHTML = ""
      ;(data.recent_orders || []).forEach((r) => {
        const tr = document.createElement("tr")

        const tdDate = document.createElement("td")
        const tdClient = document.createElement("td")
        const tdTotal = document.createElement("td")
        const tdServ = document.createElement("td")
        const tdItems = document.createElement("td")

        tdDate.textContent = r.date_commande
        tdClient.textContent = r.client_nom
        tdTotal.textContent = `${r.total} TND`
        tdServ.textContent = r.serveur_nom || ""
        tdItems.textContent = (r.items || []).join(", ")

        tr.appendChild(tdDate)
        tr.appendChild(tdClient)
        tr.appendChild(tdTotal)
        tr.appendChild(tdServ)
        tr.appendChild(tdItems)

        recentTbody.appendChild(tr)
      })
    }
  })
  .catch((err) => {
    console.error("Dashboard fetch error:", err)
  })
