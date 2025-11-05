/*login and register**/
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

/*page load*/
document.addEventListener("DOMContentLoaded", () => {
  const registerForm = document.getElementById("registerForm");
  const loginForm = document.getElementById("loginForm");

  if (registerForm) {
    registerForm.addEventListener("submit", (e) => {
      e.preventDefault();

      const formData = new FormData(registerForm);
      const fullname = formData.get("fullname");
      const email = formData.get("email");
      const username = formData.get("username");
      const role = formData.get("role");
      const password = formData.get("password");
      const confirm = formData.get("confirm_password");

      if (password !== confirm) {
        alert("Passwords do not match!");
        return;
      }

      if (!role) {
        alert("Please select your role type.");
        return;
      }

      // Save user data to localStorage (mock database)
      const userData = { fullname, email, username, role, password };
      localStorage.setItem("registeredUser", JSON.stringify(userData));

      alert("✅ Registration successful! You can now log in.");
      window.location.href = "Login.html";
    });
  }

 
  if (loginForm) {
    loginForm.addEventListener("submit", (e) => {
      e.preventDefault();

      const username = document.getElementById("username")?.value.trim();
      const password = document.getElementById("password")?.value.trim();
      const userType = document.getElementById("userType")?.value;

      if (!username || !password || !userType) {
        alert("Please fill in all fields, including user type.");
        return;
      }

      // Retrieve stored user
      const storedUser = JSON.parse(localStorage.getItem("registeredUser"));

      if (
        !storedUser ||
        storedUser.username !== username ||
        storedUser.password !== password ||
        storedUser.role !== userType
      ) {
        alert("❌ Invalid credentials or role mismatch.");
        return;
      }

      // Save session info
      localStorage.setItem("loggedInUser", username);
      localStorage.setItem("userType", userType);

      alert(`Welcome back, ${username}!`);
      window.location.href = "Dashboard.html";
    });
  }

  /*dashboard*/
  const dashboardContent = document.getElementById("dashboard-content");
  const navLinks = document.querySelectorAll("nav a[data-section]");

  if (dashboardContent) {
    const userType = localStorage.getItem("userType");
    const username = localStorage.getItem("loggedInUser");
    const header = document.querySelector("header h1");

    if (userType && header) {
      header.textContent = `Attendance Management System - ${capitalize(userType)} Dashboard (${username})`;
    }

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

  /*load*/
  function loadSection(section) {
    dashboardContent.innerHTML = `<h2 style="color:#3182bd;">Loading ${section}...</h2>`;

    fetch("Courses.json")
      .then((res) => {
        if (!res.ok) throw new Error("Failed to load dashboard data");
        return res.json();
      })
      .then((data) => {
        if (!data[section]) throw new Error(`No data found for ${section}`);
        renderTable(section, data[section]);
      })
      .catch((err) => {
        dashboardContent.innerHTML = `<p style="color:red;">${err.message}</p>`;
      });
  }

  /*table*/
  function renderTable(section, items) {
    if (!Array.isArray(items) || items.length === 0) {
      dashboardContent.innerHTML = `<p>No ${section} data available.</p>`;
      return;
    }

    const headers = Object.keys(items[0]);
    let tableHTML = `
      <h2>${capitalize(section)}</h2>
      <table>
        <thead><tr>${headers.map((h) => `<th>${capitalize(h)}</th>`).join("")}</tr></thead>
        <tbody>
          ${items
            .map(
              (item) =>
                `<tr>${headers.map((h) => `<td>${item[h]}</td>`).join("")}</tr>`
            )
            .join("")}
        </tbody>
      </table>
    `;

    dashboardContent.innerHTML = tableHTML;
  }


  function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }
});
