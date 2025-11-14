// auth.js — shared authentication logic

// Redirect user to login page if not logged in
function checkAuth() {
  if (!window.location.href.includes("login.html") && 
      !window.location.href.includes("signup.html")) {

    if (localStorage.getItem("loggedIn") !== "true") {
      window.location.href = "login.html";
    }
  }
}

// Logout function
function logout() {
  localStorage.removeItem("loggedIn");
  localStorage.removeItem("currentUser");
  window.location.href = "login.html";
}

// Attach logout handler globally
document.addEventListener("DOMContentLoaded", () => {
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", (e) => {
      e.preventDefault();
      logout();
    });
  }

  checkAuth();
});
// Show/hide nav links based on login
document.addEventListener("DOMContentLoaded", () => {
  const loggedIn = localStorage.getItem("loggedIn") === "true";

  document.querySelectorAll(".auth-loggedin").forEach(el => {
    el.style.display = loggedIn ? "inline" : "none";
  });

  document.querySelectorAll(".auth-loggedout").forEach(el => {
    el.style.display = loggedIn ? "none" : "inline";
  });
});

