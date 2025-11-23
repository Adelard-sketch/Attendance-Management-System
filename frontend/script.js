/**************************************
 * LOGIN & REGISTER FORM TOGGLE
 **************************************/
function toggleForms() {
  const loginForm = document.getElementById("loginForm");
  const registerForm = document.getElementById("registerForm");

  if (loginForm && registerForm) {
    if (loginForm.style.display === "none") {
      loginForm.style.display = "block";
      registerForm.style.display = "none";
    } else {
      loginForm.style.display = "none";
      registerForm.style.display = "block";
    }
  }
}

/**************************************
 * PAGE LOAD LOGIC
 **************************************/
document.addEventListener("DOMContentLoaded", () => {
  const registerForm = document.getElementById("registerForm");
  const loginForm = document.getElementById("loginForm");
  const dashboardContent = document.getElementById("dashboard-content");
  const navLinks = document.querySelectorAll("nav a[data-section]");

  /**************************************
   * REGISTER FORM HANDLING
   **************************************/
  if (registerForm) {
    registerForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(registerForm);
      
      // Get form values
      const fullname = formData.get("fullname").trim();
      const email = formData.get("email").trim();
      const username = formData.get("username").trim();
      const password = formData.get("password");
      const confirm = formData.get("confirm_password");
      const role = formData.get("role");
      
      // Client-side validation
      if (fullname.split(' ').length < 2) {
        alert("❌ Please enter your full name (first and last name)");
        return;
      }
      
      // Email validation
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        alert("❌ Please enter a valid email address");
        return;
      }
      
      // Username validation
      if (username.length < 3 || username.length > 20) {
        alert("❌ Username must be between 3 and 20 characters");
        return;
      }
      
      if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        alert("❌ Username can only contain letters, numbers, and underscores");
        return;
      }
      
      // Password validation
      if (password.length < 8) {
        alert("❌ Password must be at least 8 characters long");
        return;
      }
      
      if (!/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
        alert("❌ Password must contain both letters and numbers");
        return;
      }

      if (password !== confirm) {
        alert("❌ Passwords do not match!");
        return;
      }

      if (!role) {
        alert("❌ Please select your role type.");
        return;
      }

      try {
        const res = await fetch("http://localhost/Activity3/api/register.php", {
          method: "POST",
          body: formData,
        });
        const data = await res.json();

        if (data.success) {
          alert("✅ Registration successful! You can now log in.");
          window.location.href = "Login.html";
        } else {
          alert("❌ Registration failed: " + (data.message || "Unknown error"));
        }
      } catch (err) {
        alert("❌ Registration failed: " + err.message);
      }
    });
  }

  /**************************************
   * LOGIN FORM HANDLING
   **************************************/
  if (loginForm) {
    loginForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(loginForm);
      
      // Client-side validation
      const username = formData.get("username").trim();
      const password = formData.get("password");
      const userType = formData.get("userType");
      
      if (username.length < 3) {
        alert("❌ Username must be at least 3 characters long");
        return;
      }
      
      if (!userType) {
        alert("❌ Please select your role");
        return;
      }

      try {
        const res = await fetch("http://localhost/Activity3/api/Login.php", {
          method: "POST",
          body: formData,
          credentials: "include"
        });
        const data = await res.json();

        if (data.success) {
          localStorage.setItem("loggedInUser", data.username);
          localStorage.setItem("userType", data.role);
          localStorage.setItem("fullname", data.fullname || data.username);
          
          alert(`Welcome back, ${data.fullname || data.username}!`);
          
          // Redirect based on role
          if (data.role === 'student') {
            window.location.href = "StudentDashboard.html";
          } else if (data.role === 'lecturer' || data.role === 'intern') {
            window.location.href = "FacultyDashboard.html";
          } else {
            window.location.href = "Dashboard.html";
          }
        } else {
          alert("❌ Login failed: " + data.message);
        }
      } catch (err) {
        alert("❌ Login failed: " + err.message);
      }
    });
  }

  /**************************************
   * DASHBOARD HANDLING
   **************************************/
  if (dashboardContent) {
    const userType = localStorage.getItem("userType");
    const username = localStorage.getItem("loggedInUser");
    const header = document.querySelector("header h1");

    if (userType && header) {
      header.textContent = `Attendance Management System - ${capitalize(userType)} Dashboard (${username})`;
    }

    // Load default section
    loadSection("courses");

    navLinks.forEach((link) => {
      link.addEventListener("click", (e) => {
        e.preventDefault();
        const section = link.dataset.section;

        navLinks.forEach((l) => l.classList.remove("active"));
        link.classList.add("active");

        loadSection(section);
      });
    });
  }

  /**************************************
   * LOAD DASHBOARD DATA (API or JSON)
   **************************************/
  async function loadSection(section) {
    dashboardContent.innerHTML = `<h2 style="color:#3182bd;">Loading ${section}...</h2>`;
    let data = [];

    try {
      // Try to fetch from backend API
      const res = await fetch(`http://localhost/Activity3/api/dashboard.php?section=${section}`);
      if (res.ok) {
        data = await res.json();
      }

      // Fallback to JSON if API empty
      if (!data || data.length === 0) {
        const jsonRes = await fetch(`http://localhost/Activity3/frontend/${capitalize(section)}.json`);
        if (jsonRes.ok) data = await jsonRes.json();
      }

      renderTable(section, data);
    } catch (err) {
      dashboardContent.innerHTML = `<p style="color:red;">Failed to load ${section}: ${err.message}</p>`;
    }
  }

  /**************************************
   * TABLE BUILDER
   **************************************/
  function renderTable(section, items) {
    if (!Array.isArray(items) || items.length === 0) {
      dashboardContent.innerHTML = `<p>No ${section} data available.</p>`;
      return;
    }

    const headers = Object.keys(items[0]);
    let tableHTML = `
      <h2>${capitalize(section)}</h2>
      <table>
        <thead>
          <tr>${headers.map(h => `<th>${capitalize(h)}</th>`).join('')}</tr>
        </thead>
        <tbody>
          ${items.map(item => `<tr>${headers.map(h => {
            const val = item[h];
            // Properly handle objects
            return `<td>${typeof val === 'object' && val !== null ? JSON.stringify(val) : val ?? ""}</td>`;
          }).join('')}</tr>`).join('')}
        </tbody>
      </table>
    `;
    dashboardContent.innerHTML = tableHTML;
  }

  /**************************************
   * HELPER - CAPITALIZE
   **************************************/
  function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }
});
